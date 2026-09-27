<?php

namespace App\Http\Controllers;

use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/unit-of-measures/page', [
            'unitOfMeasures' => UnitOfMeasure::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('unit_of_measures', 'code')],
            'name' => 'required|string|max:255',
        ]);

        UnitOfMeasure::create($validated);

        return redirect()->back()->with('success', __('messages.uom.created'));
    }

    public function update(Request $request, UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->tenantUnique('unit_of_measures', 'code', $unitOfMeasure->id)],
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $unitOfMeasure->update($validated);

        return redirect()->back()->with('success', __('messages.uom.updated'));
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        if ($unitOfMeasure->products()->exists()) {
            return redirect()->back()->with('error', __('messages.uom.cannot_delete_in_use'));
        }

        $unitOfMeasure->delete();

        return redirect()->back()->with('success', __('messages.uom.deleted'));
    }
}
