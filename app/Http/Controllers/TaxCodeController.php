<?php

namespace App\Http\Controllers;

use App\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxCodeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/tax-codes/page', [
            'taxCodes' => TaxCode::with('account:id,code,name')->orderBy('code')->get(),
            'accounts' => \App\Models\Account::where('is_postable', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('tax_codes', 'code')],
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'account_id' => ['nullable', $this->tenantExists('accounts')],
        ]);

        TaxCode::create($validated);

        return redirect()->back()->with('success', __('messages.tax_code.created'));
    }

    public function update(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('tax_codes', 'code', $taxCode->id)],
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'account_id' => ['nullable', $this->tenantExists('accounts')],
            'is_active' => 'boolean',
        ]);

        $taxCode->update($validated);

        return redirect()->back()->with('success', __('messages.tax_code.updated'));
    }

    public function destroy(TaxCode $taxCode): RedirectResponse
    {
        $taxCode->delete();

        return redirect()->back()->with('success', __('messages.tax_code.deleted'));
    }
}
