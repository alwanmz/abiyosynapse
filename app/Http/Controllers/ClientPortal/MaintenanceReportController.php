<?php

namespace App\Http\Controllers\ClientPortal;

use App\Exports\MaintenanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceReportController extends Controller
{
    /**
     * Published maintenance reports belonging to the authenticated client only.
     */
    public function index(Request $request): Response
    {
        $reports = MaintenanceReport::query()
            ->where('client_id', Auth::guard('client')->id())
            ->where('status', 'published')
            ->with(['project:id,name'])
            ->withCount('items')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MaintenanceReport $report) => [
                'id' => $report->id,
                'report_number' => $report->report_number,
                'title' => $report->title,
                'project' => $report->project,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'summary' => $report->summary,
                'items_count' => (int) $report->items_count,
                'published_at' => $report->published_at,
            ]);

        return Inertia::render('client-portal/maintenance-reports/index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Self-service spreadsheet download. Guarded by both the ownership check and
     * the published status so drafts never leak.
     */
    public function exportXlsx(MaintenanceReport $maintenanceReport): BinaryFileResponse
    {
        $this->authorizeClientOwnership($maintenanceReport);

        return Excel::download(
            new MaintenanceReportExport($maintenanceReport),
            $maintenanceReport->exportFilename('xlsx')
        );
    }

    /**
     * Abort with 404 unless the report belongs to the authenticated client and
     * has been published. Never rely on route-model binding alone — this
     * prevents IDOR across clients.
     */
    private function authorizeClientOwnership(MaintenanceReport $report): void
    {
        abort_unless(
            (int) $report->client_id === (int) Auth::guard('client')->id() && $report->isPublished(),
            404
        );
    }
}
