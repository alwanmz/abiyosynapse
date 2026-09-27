<?php

namespace App\Http\Controllers;

use App\Models\NonConformanceReport;
use App\Services\Quality\QualityInspectionService;
use App\Services\Quality\QualityReleaseService;
use App\Services\Manufacturing\ReworkProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NonConformanceReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('quality/ncrs/page', [
            'ncrs' => NonConformanceReport::with(['inspection.product:id,code,name', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function disposition(Request $request, NonConformanceReport $ncr, QualityReleaseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'disposition' => 'required|in:rework,scrap,return,use_as_is',
            'disposition_notes' => 'nullable|string',
        ]);

        try {
            $service->disposition($ncr, $validated['disposition'], $validated['disposition_notes'] ?? null, $request->user());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ncr.disposition_recorded'));
    }

    public function createRework(NonConformanceReport $ncr, ReworkProductionOrderService $service, Request $request): RedirectResponse
    {
        try {
            $service->createFromNcr($ncr, $request->user());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ncr.rework_order_created'));
    }

    public function correctiveAction(Request $request, NonConformanceReport $ncr, QualityInspectionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'corrective_action' => 'required|string',
        ]);

        try {
            $service->applyCorrectiveAction($ncr, $validated['corrective_action']);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ncr.corrective_action_recorded'));
    }

    public function close(NonConformanceReport $ncr, QualityInspectionService $service): RedirectResponse
    {
        try {
            $service->close($ncr);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.ncr.closed'));
    }
}
