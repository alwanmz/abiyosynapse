<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanyCurrency;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(CurrentCompany $currentCompany): Response
    {
        return Inertia::render('master/customers/page', [
            'customers' => Customer::orderBy('name')->get(),
            'baseCurrency' => $currentCompany->get()?->currency ?? 'IDR',
            'currencies' => CompanyCurrency::with('currency:code,name')
                ->where('is_active', true)
                ->orderBy('currency_code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', $this->tenantUnique('customers', 'code')],
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
        ]);

        $validated['currency_code'] ??= app(CurrentCompany::class)->get()?->currency ?? 'IDR';

        Customer::create($validated);

        return redirect()->back()->with('success', __('messages.customer.created'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', $this->tenantUnique('customers', 'code', $customer->id)],
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
            'is_active' => 'boolean',
            'currency_code' => 'nullable|string|size:3|exists:currencies,code',
        ]);

        $customer->update($validated);

        return redirect()->back()->with('success', __('messages.customer.updated'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->back()->with('success', __('messages.customer.deleted'));
    }
}
