<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\QualityInspection;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QualityInspectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('quality/inspections/page', [
            'inspections' => QualityInspection::with(['product:id,code,name', 'inspector:id,name', 'inspectable'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    /**
     * Records the Final inspection for a production order that's
     * currently pending QC (blueprint §11-12) and immediately completes
     * it via ProductionOrderService::completeAfterQc() — pass/fail
     * quantities from the inspection drive how much stock is received
     * as finished goods vs scrapped.
     */
    public function storeFinal(Request $request, ProductionOrder $productionOrder, QualityInspectionService $inspectionService, \App\Services\Manufacturing\ProductionOrderService $orderService): RedirectResponse
    {
        $validated = $request->validate([
            'quantity_passed' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (! $productionOrder->isPendingQc()) {
            return redirect()->back()->with('error', __('messages.quality.order_not_pending_qc'));
        }

        $totalInspected = (float) $productionOrder->produced_quantity + (float) $productionOrder->rejected_quantity;
        $totalInspected = $totalInspected > 0 ? $totalInspected : (float) $productionOrder->planned_quantity;
        $passed = (float) $validated['quantity_passed'];

        if ($passed > $totalInspected) {
            return redirect()->back()->withErrors(['quantity_passed' => __('messages.quality.passed_exceeds_inspected')]);
        }

        $inspectionService->inspect(
            'final',
            $productionOrder,
            $productionOrder->product,
            $totalInspected,
            $passed,
            $validated['notes'] ?? null,
        );

        $orderService->completeAfterQc($productionOrder, $passed, $totalInspected - $passed);

        return redirect()->back()->with('success', __('messages.quality.final_inspection_recorded'));
    }
}
