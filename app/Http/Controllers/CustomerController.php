<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/customers/page', [
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:customers,code',
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
        ]);

        Customer::create($validated);

        return redirect()->back()->with('success', __('messages.customer.created'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:customers,code,' . $customer->id,
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
            'is_active' => 'boolean',
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
