<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ClientRequestQuota;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * All statuses in the canonical order used by the board UI, so charts always
     * render every column even when a client has zero tickets in it.
     */
    private const STATUSES = [
        'todo', 'pending', 'inprogress', 'qa-ready',
        'qa-test', 'review', 'not-appropriate', 'done',
    ];

    private const PRIORITIES = ['highest', 'high', 'medium', 'low', 'lowest'];

    /**
     * Client-scoped dashboard: every aggregation is filtered to the logged-in
     * client's own tickets — no global/staff data ever leaks here.
     */
    public function index(Request $request): Response
    {
        $clientId = Auth::guard('client')->id();

        $base = fn () => Ticket::query()->where('client_id', $clientId);

        // backlog is folded into todo to match the board's column set.
        $statusCounts = (clone $base())
            ->selectRaw("CASE WHEN status = 'backlog' THEN 'todo' ELSE status END as status, count(*) as count")
            ->groupBy(DB::raw("CASE WHEN status = 'backlog' THEN 'todo' ELSE status END"))
            ->pluck('count', 'status');

        $byStatus = collect(self::STATUSES)->map(fn ($status) => [
            'status' => $status,
            'count' => (int) ($statusCounts[$status] ?? 0),
        ])->values();

        $priorityCounts = (clone $base())
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $byPriority = collect(self::PRIORITIES)->map(fn ($priority) => [
            'priority' => $priority,
            'count' => (int) ($priorityCounts[$priority] ?? 0),
        ])->values();

        return Inertia::render('client-portal/dashboard', [
            'stats' => [
                'total' => (clone $base())->count(),
                'inprogress' => (clone $base())->where('status', 'inprogress')->count(),
                'done' => (clone $base())->where('status', 'done')->count(),
                'attention' => (clone $base())->whereIn('status', ['not-appropriate', 'pending'])->count(),
            ],
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'trend' => $this->trend($clientId),
            'quota' => ClientRequestQuota::payloadFor(Auth::guard('client')->user()),
        ]);
    }

    /**
     * Last-30-days daily counts of created vs completed tickets for this client.
     * Returns a dense series (zero-filled) so the line chart has no gaps.
     */
    private function trend(int $clientId): array
    {
        $from = now()->subDays(29)->startOfDay();

        $created = Ticket::where('client_id', $clientId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $done = Ticket::where('client_id', $clientId)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $from)
            ->selectRaw('DATE(resolved_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $series = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'created' => (int) ($created[$day] ?? 0),
                'done' => (int) ($done[$day] ?? 0),
            ];
        }

        return $series;
    }
}
