<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
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

        return redirect()->route('dashboard')->with('success', 'Perusahaan berhasil dibuat.');
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

        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil diperbarui.');
    }
}
