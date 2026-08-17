<?php

namespace App\Http\Controllers;

use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkCenterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('manufacturing/work-centers/page', [
            'workCenters' => WorkCenter::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        WorkCenter::create($validated);

        return redirect()->back()->with('success', __('messages.work_center.created'));
    }

    public function update(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $validated = $this->validated($request, $workCenter->id);

        $workCenter->update($validated);

        return redirect()->back()->with('success', __('messages.work_center.updated'));
    }

    public function destroy(WorkCenter $workCenter): RedirectResponse
    {
        if ($workCenter->routingOperations()->exists()) {
            return redirect()->back()->with('error', __('messages.work_center.cannot_delete_in_use'));
        }

        $workCenter->delete();

        return redirect()->back()->with('success', __('messages.work_center.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:20|unique:work_centers,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name' => 'required|string|max:255',
            'capacity_per_day_minutes' => 'required|numeric|min:0',
            'cost_rate_per_minute' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);
    }
}
