<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CompanyCurrency;
use App\Models\Currency;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\TenantAuthorizationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = $request->user()->companies()
            ->withPivot(['role_id', 'is_default'])
            ->get();

        return Inertia::render('companies/page', ['companies' => $companies]);
    }

    public function create()
    {
        return Inertia::render('companies/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50|unique:companies,code',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
        ]);

        $validated['code'] ??= Str::slug($validated['name']) . '-' . Str::lower(Str::random(4));
        $validated['currency'] = strtoupper($validated['currency']);
        Currency::where('code', $validated['currency'])->where('is_active', true)->firstOrFail();
        $validated['trial_ends_at'] = now()->addDays(7);

        $user = $request->user();
        $starterPlan = SubscriptionPlan::where('code', 'starter')
            ->where('is_active', true)
            ->first();
        abort_if($starterPlan === null, 500, 'The starter subscription plan is not configured.');

        DB::transaction(function () use (&$company, $validated, $user, $starterPlan): void {
            $company = Company::create([...$validated, 'owner_id' => $user->id]);
            CompanyCurrency::create([
                'company_id' => $company->id,
                'currency_code' => $company->currency,
                'is_active' => true,
                'is_base' => true,
            ]);

            $superAdminRoleId = Role::whereNull('company_id')
                ->where('name', 'super_admin')
                ->value('id');

            abort_if($superAdminRoleId === null, 500, 'The default administrator role is not configured.');

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $superAdminRoleId,
                'is_default' => ! $user->companies()->where('company_id', '!=', $company->id)->exists(),
                'joined_at' => now(),
            ]);

            $company->subscription()->firstOrCreate(
                    [],
                [
                    'subscription_plan_id' => $starterPlan->id,
                    'status' => 'trialing',
                    'starts_at' => now(),
                    'trial_ends_at' => $company->trial_ends_at,
                    'current_period_start' => now(),
                ],
            );

            if (! $user->current_company_id) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }
        });

        return redirect()->route('dashboard')->with('success', __('messages.company.created'));
    }

    public function edit(Company $company, TenantAuthorizationService $authorization)
    {
        $authorization->ensure(request()->user(), $company, 'companies.edit', 'companies.manage');

        return Inertia::render('companies/edit', ['company' => $company]);
    }

    public function update(Request $request, Company $company, TenantAuthorizationService $authorization)
    {
        $authorization->ensure($request->user(), $company, 'companies.edit', 'companies.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
            'is_active' => 'boolean',
        ]);

        $validated['currency'] = strtoupper($validated['currency']);

        if ($validated['currency'] !== $company->currency
            && DB::table('journal_entries')->where('company_id', $company->id)->exists()) {
            return redirect()->back()->with('error', __('messages.company.currency_locked'));
        }

        Currency::where('code', $validated['currency'])->where('is_active', true)->firstOrFail();

        DB::transaction(function () use ($company, $validated): void {
            CompanyCurrency::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('currency_code', '!=', $validated['currency'])
                ->update(['is_base' => false]);
            $company->update($validated);
            CompanyCurrency::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'currency_code' => $company->currency],
                ['is_active' => true, 'is_base' => true],
            );
        });

        return redirect()->route('companies.index')->with('success', __('messages.company.updated'));
    }

    /**
     * Delete a company. Guarded against removing a user's only company
     * (they'd be left without any company context) and against dependent
     * business data. Companies with business history must be archived so
     * audit and reporting data remain recoverable.
     */
    public function destroy(Request $request, Company $company, TenantAuthorizationService $authorization)
    {
        $authorization->ensure($request->user(), $company, 'companies.delete', 'companies.manage');

        if ($this->hasDependentData($company)) {
            return redirect()->route('companies.index')->with('error', __('messages.company.has_dependent_data'));
        }

        $memberUserIds = CompanyUser::where('company_id', $company->id)->pluck('user_id');

        $usersWithNoOtherCompany = User::whereIn('id', $memberUserIds)
            ->whereDoesntHave('companies', fn ($query) => $query->where('companies.id', '!=', $company->id))
            ->pluck('id');

        if ($usersWithNoOtherCompany->contains($request->user()->id)) {
            return redirect()->route('companies.index')->with('error', __('messages.company.cannot_delete_only_company'));
        }

        DB::transaction(function () use ($company, $memberUserIds): void {
            CompanyUser::where('company_id', $company->id)->delete();

            User::whereIn('id', $memberUserIds)
                ->where('current_company_id', $company->id)
                ->each(function (User $user) use ($company): void {
                    $fallback = $user->companies()->where('companies.id', '!=', $company->id)->first();
                    $user->forceFill(['current_company_id' => $fallback?->id])->save();
                });

            $company->delete();
        });

        return redirect()->route('companies.index')->with('success', __('messages.company.deleted'));
    }

    /**
     * Whether this company has data that must be retained for audit/history.
     * Company deletion is intentionally limited to an empty tenant; active
     * tenants with any master or transactional data must be archived instead.
     */
    protected function hasDependentData(Company $company): bool
    {
        $protectedTables = [
            'accounts', 'journal_entries', 'products', 'warehouses', 'suppliers', 'customers',
            'tax_codes', 'unit_of_measures', 'bank_accounts', 'cash_transactions',
            'bank_reconciliations', 'purchase_requests', 'purchase_orders', 'goods_receipts',
            'supplier_invoices', 'sales_orders', 'delivery_orders', 'sales_invoices',
            'sales_returns', 'ar_receipts', 'ap_payments', 'fixed_assets', 'production_orders',
            'boms', 'routings', 'work_centers', 'quality_inspections', 'non_conformance_reports',
            'maintenance_equipment', 'maintenance_work_orders', 'maintenance_schedules',
            'maintenance_readings', 'ai_documents', 'audit_logs', 'report_export_runs',
        ];

        foreach ($protectedTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')
                && DB::table($table)->where('company_id', $company->id)->exists()) {
                return true;
            }
        }

        return false;
    }

}
