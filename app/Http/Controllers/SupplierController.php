<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/suppliers/page', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:suppliers,code',
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
        ]);

        Supplier::create($validated);

        return redirect()->back()->with('success', __('messages.supplier.created'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:suppliers,code,' . $supplier->id,
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_term_days' => 'required|integer|min:0|max:365',
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);

        return redirect()->back()->with('success', __('messages.supplier.updated'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->back()->with('success', __('messages.supplier.deleted'));
    }
}
