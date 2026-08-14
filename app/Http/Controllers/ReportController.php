<?php

namespace App\Http\Controllers;

use App\Exports\DailyLogsExport;
use App\Exports\MinutesExport;
use App\Exports\ProjectsExport;
use App\Exports\TicketsExport;
use App\Models\DailyLog;
use App\Models\Minute;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private function canViewReports(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->hasPermissionTo('reports.view'));
    }

    public function index(Request $request): Response
    {
        abort_unless($this->canViewReports(), 403);

        $filters = $request->only(['date_from', 'date_to', 'project_id', 'user_id', 'status', 'priority', 'tab']);

        // Default ke bulan berjalan
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->endOfMonth()->format('Y-m-d');

        $filters['date_from'] = $dateFrom;
        $filters['date_to']   = $dateTo;

        $projectId = $filters['project_id'] ?? null;
        $userId    = $filters['user_id'] ?? null;
        $status    = $filters['status'] ?? null;
        $priority  = $filters['priority'] ?? null;

        // ── Summary cards ──────────────────────────────────────────────────
        $summary = [
            'projects_done'   => Project::where('status', 'completed')
                ->whereDate('updated_at', '>=', $dateFrom)
                ->whereDate('updated_at', '<=', $dateTo)
                ->count(),
            'tickets_done'    => Ticket::where('status', 'done')
                ->whereDate('updated_at', '>=', $dateFrom)
                ->whereDate('updated_at', '<=', $dateTo)
                ->count(),
            'daily_logs'      => DailyLog::whereDate('log_date', '>=', $dateFrom)
                ->whereDate('log_date', '<=', $dateTo)
                ->count(),
            'decisions_total' => Minute::whereDate('meeting_date', '>=', $dateFrom)
                ->whereDate('meeting_date', '<=', $dateTo)
                ->get()
                ->sum(fn($m) => count($m->decisions ?? [])),
        ];

        // ── Projects ───────────────────────────────────────────────────────
        $projects = Project::with(['team:id,name', 'client:id,nama', 'projectManager:id,name'])
            ->withCount([
                'tickets',
                'tickets as tickets_done_count' => fn($q) => $q->where('status', 'done'),
            ])
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy('name')
            ->paginate(50, ['*'], 'projects_page')
            ->withQueryString();

        // ── Tickets ────────────────────────────────────────────────────────
        $tickets = Ticket::with(['project:id,name', 'assignedUser:id,name', 'reporter:id,name', 'taskType:id,nama'])
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($priority, fn($q, $p) => $q->where('priority', $p))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy('created_at', 'desc')
            ->paginate(50, ['*'], 'tickets_page')
            ->withQueryString();

        // ── Daily Logs ─────────────────────────────────────────────────────
        $dailyLogs = DailyLog::with(['user:id,name', 'ticket:id,ticket_number,title'])
            ->when($userId, fn($q, $u) => $q->where('user_id', $u))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('log_date', '>=', $d))
            ->when($dateTo, fn($q, $d) => $q->whereDate('log_date', '<=', $d))
            ->orderBy('log_date', 'desc')
            ->paginate(50, ['*'], 'logs_page')
            ->withQueryString();

        // ── Minutes ────────────────────────────────────────────────────────
        $minutes = Minute::with(['project:id,name', 'creator:id,name'])
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('meeting_date', '>=', $d))
            ->when($dateTo, fn($q, $d) => $q->whereDate('meeting_date', '<=', $d))
            ->orderBy('meeting_date', 'desc')
            ->paginate(50, ['*'], 'minutes_page')
            ->withQueryString();

        return Inertia::render('reports/page', [
            'summary'   => $summary,
            'projects'  => $projects,
            'tickets'   => $tickets,
            'dailyLogs' => $dailyLogs,
            'minutes'   => $minutes,
            'filters'   => $filters,
            'users'     => User::select('id', 'name')->orderBy('name')->get(),
            'projectList' => Project::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function exportProjects(Request $request): BinaryFileResponse
    {
        abort_unless($this->canViewReports(), 403);

        $filters = $request->only(['date_from', 'date_to', 'status']);
        $filename = 'laporan-proyek-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new ProjectsExport($filters), $filename);
    }

    public function exportTickets(Request $request): BinaryFileResponse
    {
        abort_unless($this->canViewReports(), 403);

        $filters = $request->only(['date_from', 'date_to', 'project_id', 'status', 'priority']);
        $filename = 'laporan-tiket-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new TicketsExport($filters), $filename);
    }

    public function exportDailyLogs(Request $request): BinaryFileResponse
    {
        abort_unless($this->canViewReports(), 403);

        $filters = $request->only(['date_from', 'date_to', 'user_id']);
        $filename = 'laporan-catatan-harian-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new DailyLogsExport($filters), $filename);
    }

    public function exportMinutes(Request $request): BinaryFileResponse
    {
        abort_unless($this->canViewReports(), 403);

        $filters = $request->only(['date_from', 'date_to', 'project_id']);
        $filename = 'laporan-notulensi-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new MinutesExport($filters), $filename);
    }
}
