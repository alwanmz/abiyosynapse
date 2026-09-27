<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/warehouses/page', [
            'warehouses' => Warehouse::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('warehouses', 'code')],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        Warehouse::create($validated);

        return redirect()->back()->with('success', __('messages.warehouse.created'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('warehouses', 'code', $warehouse->id)],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);

        return redirect()->back()->with('success', __('messages.warehouse.updated'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->products()->exists()) {
            return redirect()->back()->with('error', __('messages.warehouse.cannot_delete_in_use'));
        }

        $warehouse->delete();

        return redirect()->back()->with('success', __('messages.warehouse.deleted'));
    }
}
