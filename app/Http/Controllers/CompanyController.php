<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $validated['trial_ends_at'] = now()->addDays(7);

        $user = $request->user();

        $company = Company::create($validated);

        $superAdminRoleId = Role::where('name', 'super_admin')->value('id');

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $superAdminRoleId,
            'is_default' => ! $user->companies()->where('company_id', '!=', $company->id)->exists(),
            'joined_at' => now(),
        ]);

        if (! $user->current_company_id) {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }

        return redirect()->route('dashboard')->with('success', __('messages.company.created'));
    }

    public function edit(Company $company)
    {
        return Inertia::render('companies/edit', ['company' => $company]);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
            'is_active' => 'boolean',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')->with('success', __('messages.company.updated'));
    }

    /**
     * Delete a company. Guarded against removing a user's only company
     * (they'd be left without any company context) and against dependent
     * business data — no transactional modules exist yet, so
     * hasDependentData() always returns false today, but the check stays
     * in place so future modules (Inventory, GL, etc.) only need to
     * extend that one method to be protected here.
     */
    public function destroy(Request $request, Company $company)
    {
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

        CompanyUser::where('company_id', $company->id)->delete();

        User::whereIn('id', $memberUserIds)
            ->where('current_company_id', $company->id)
            ->each(function (User $user) use ($company) {
                $fallback = $user->companies()->where('companies.id', '!=', $company->id)->first();
                $user->forceFill(['current_company_id' => $fallback?->id])->save();
            });

        $company->delete();

        return redirect()->route('companies.index')->with('success', __('messages.company.deleted'));
    }

    /**
     * Whether this company has business data that would be lost if it
     * were deleted. Always false today — no transactional modules exist
     * yet — but kept as a single extension point for when they do
     * (Inventory movements, GL entries, Purchase/Sales documents, etc.).
     */
    protected function hasDependentData(Company $company): bool
    {
        return false;
    }
}
