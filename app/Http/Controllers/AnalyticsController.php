<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DailyLog;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\AiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;

class AnalyticsController extends Controller
{
    private function canViewAnalytics(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->hasPermissionTo('analytics.view'));
    }

    /**
     * Get burndown chart data for a specific sprint/timeline.
     */
    public function burndownData(Request $request)
    {
        $timelineId = $request->query('timeline_id');
        if (!$timelineId) {
            return response()->json(['error' => 'timeline_id is required'], 400);
        }

        $timeline = ProjectTimeline::findOrFail($timelineId);
        $startDate = $timeline->start_date ? Carbon::parse($timeline->start_date)->startOfDay() : $timeline->created_at->startOfDay();
        $endDate = $timeline->end_date ? Carbon::parse($timeline->end_date)->endOfDay() : $startDate->copy()->addDays(14)->endOfDay();
        
        // Days in sprint
        $days = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $days[] = $current->format('Y-m-d');
            $current->addDay();
        }

        // Total story points in this timeline
        $totalPoints = Ticket::where('timeline_id', $timelineId)->sum('story_points') ?? 0;
        
        // Logs of tickets finished in this timeline
        $finishedLogs = TicketStatusLog::whereIn('ticket_id', function($query) use ($timelineId) {
                $query->select('id')->from('tickets')->where('timeline_id', $timelineId);
            })
            ->where('to_status', 'done')
            ->orderBy('changed_at')
            ->get();

        $data = [];
        $remainingActual = $totalPoints;
        $totalDays = count($days) - 1;
        if ($totalDays <= 0) $totalDays = 1;

        foreach ($days as $index => $day) {
            $dayDate = Carbon::parse($day);
            
            // Subtract points of tickets finished ON this day
            $pointsFinishedThisDay = $finishedLogs->filter(function($log) use ($dayDate) {
                return $log->changed_at->isSameDay($dayDate);
            })->sum('story_points');
            
            $remainingActual -= $pointsFinishedThisDay;

            // Ideal calculation
            $ideal = max(0, $totalPoints - ($totalPoints / $totalDays) * $index);

            $data[] = [
                'day' => $dayDate->format('d M'),
                'actual' => round($remainingActual, 1),
                'ideal' => round($ideal, 1),
                'fullDate' => $day,
            ];
        }

        return response()->json([
            'timeline' => $timeline->title,
            'totalPoints' => $totalPoints,
            'series' => $data,
        ]);
    }

    public function index()
    {
        $stats = $this->getDashboardStats();

        return Inertia::render('dashboard', [
            'dashboardStats' => $stats,
        ]);
    }

    /**
     * JSON endpoint for realtime polling of dashboard stats.
     */
    public function dashboardStats()
    {
        return response()->json($this->getDashboardStats());
    }

    /**
     * Collect all lightweight stats needed for the main dashboard.
     */
    private function getDashboardStats(): array
    {
        $totalTickets    = Ticket::count();
        $activeProjects  = Project::whereNotIn('status', ['completed', 'cancelled'])->count();
        $completedTickets = Ticket::where('status', 'done')->count();
        $totalUsers      = User::count();

        // Tickets by status (for sprint summary)
        $ticketsByStatus = Ticket::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $inProgress = $ticketsByStatus->get('inprogress', 0)
            + $ticketsByStatus->get('not-appropriate', 0)
            + $ticketsByStatus->get('qa-ready', 0)
            + $ticketsByStatus->get('qa-test', 0)
            + $ticketsByStatus->get('review', 0);
        $pending    = $ticketsByStatus->get('pending', 0) + $ticketsByStatus->get('backlog', 0) + $ticketsByStatus->get('todo', 0);

        // My tasks (tickets assigned to auth user)
        // Priority ordering compatible with PostgreSQL and MySQL
        $priorityOrder = "CASE priority WHEN 'highest' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 WHEN 'lowest' THEN 5 ELSE 6 END";
        $myTasks = Ticket::with(['project:id,name'])
            ->handledByUser(auth()->id())
            ->whereNotIn('status', ['done'])
            ->select('id', 'ticket_number', 'title', 'project_id', 'priority', 'status')
            ->orderByRaw($priorityOrder)
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id'       => $t->ticket_number,
                'title'    => $t->title,
                'project'  => $t->project?->name,
                'priority' => $t->priority,
                'status'   => $t->status,
            ]);

        // Active projects with basic progress info
        $recentProjects = Project::with(['team:id,name,color'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->select('id', 'name', 'status', 'team_id', 'end_date')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($project) {
                $total     = Ticket::where('project_id', $project->id)->count();
                $done      = Ticket::where('project_id', $project->id)->where('status', 'done')->count();
                $progress  = $total > 0 ? round(($done / $total) * 100) : 0;
                $active    = $total - $done;

                return [
                    'id'            => $project->id,
                    'name'          => $project->name,
                    'status'        => $project->status,
                    'team'          => $project->team?->name,
                    'teamColor'     => $project->team?->color,
                    'progress'      => $progress,
                    'activeTickets' => $active,
                    'dueDate'       => $project->end_date?->format('Y-m-d'),
                ];
            });

        // Upcoming deadlines — tickets with due_date in the next 7 days
        $upcomingDeadlines = Ticket::with(['project:id,name'])
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['done'])
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->orderBy('due_date')
            ->select('id', 'ticket_number', 'title', 'project_id', 'priority', 'due_date')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'task'     => $t->title,
                'project'  => $t->project?->name,
                'dueDate'  => $t->due_date->format('Y-m-d'),
                'priority' => $t->priority,
            ]);

        // Recent ticket activity (last 10 updates)
        $recentActivity = Ticket::with(['assignedUser:id,name,avatar_path', 'reporter:id,name,avatar_path'])
            ->select('id', 'ticket_number', 'title', 'status', 'assigned_to', 'reporter_id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(function ($t) {
                $activityUser = $t->assignedUser ?? $t->reporter;

                return [
                    'user' => $activityUser?->name ?? 'System',
                    'userAvatarUrl' => $activityUser?->avatar_url,
                    'action' => 'updated ticket',
                    'target' => $t->ticket_number,
                    'time' => $t->updated_at->diffForHumans(),
                ];
            });

        // ---- PROJECT INFO (mockup hal. 3) ----
        $today = now();
        $clientActive   = Client::where('is_active', true)->count();
        $clientInactive = Client::where('is_active', false)->count();
        $totalProjects  = Project::count();
        $mouExpired = Project::whereNotNull('tgl_selesai_kontrak')
            ->whereDate('tgl_selesai_kontrak', '<', $today)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $mouExpiringSoon = Project::whereNotNull('tgl_selesai_kontrak')
            ->whereDate('tgl_selesai_kontrak', '>=', $today)
            ->whereDate('tgl_selesai_kontrak', '<=', $today->copy()->addDays(30))
            ->count();

        // ---- TASK INFO (mockup hal. 3) ----
        $ticketsByPriority = Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')->pluck('count', 'priority');
        $requestBerbayar = Ticket::where('request_type', 'berbayar')->count();
        $requestGratis   = Ticket::where('request_type', 'gratis')->count();
        $requestRejected = Ticket::whereNotNull('rejected_at')->count();
        $requestApproved = Ticket::whereNotNull('approved_at')->count();
        $requestOnProgress = Ticket::whereIn('status', ['inprogress', 'qa-test', 'review', 'not-appropriate'])->count();

        // ---- INDIKATOR ----
        $selesaiTepatWaktu = Ticket::where('status', 'done')
            ->whereNotNull('due_date')
            ->whereColumn('updated_at', '<=', 'due_date')
            ->count();
        $selesaiTidakTepatWaktu = Ticket::where('status', 'done')
            ->whereNotNull('due_date')
            ->whereColumn('updated_at', '>', 'due_date')
            ->count();
        $belumDirespon = Ticket::whereIn('status', ['backlog', 'todo'])
            ->withoutAnyAssignee()
            ->count();

        // ---- TASK INFO BY PERSONIL ----
        // Count per user across pelaksana (assigned_to) + delegated assignees
        // (ticket_user pivot); most work here is tracked via delegation.
        $taskByPersonil = User::select('users.id', 'users.name')->get()
            ->map(fn($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'request' => Ticket::handledByUser($u->id)->where('type', '!=', 'bug')->count(),
                'bug'     => Ticket::handledByUser($u->id)->where('type', 'bug')->count(),
                'done'    => Ticket::handledByUser($u->id)->where('status', 'done')->count(),
            ])
            ->filter(fn($u) => $u['request'] > 0 || $u['bug'] > 0)
            ->sortByDesc('request')
            ->take(15)
            ->values();

        return [
            'stats' => [
                'activeProjects'   => $activeProjects,
                'totalTickets'     => $totalTickets,
                'completedTickets' => $completedTickets,
                'teamMembers'      => $totalUsers,
                'completionRate'   => $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100) : 0,
            ],
            'projectInfo' => [
                'clientActive'    => $clientActive,
                'clientInactive'  => $clientInactive,
                'totalProjects'   => $totalProjects,
                'mouExpired'      => $mouExpired,
                'mouExpiringSoon' => $mouExpiringSoon,
            ],
            'taskInfo' => [
                'totalTask'       => $totalTickets,
                'high'            => (int) $ticketsByPriority->get('highest', 0) + (int) $ticketsByPriority->get('high', 0),
                'medium'          => (int) $ticketsByPriority->get('medium', 0),
                'low'             => (int) $ticketsByPriority->get('low', 0) + (int) $ticketsByPriority->get('lowest', 0),
                'requestBerbayar' => $requestBerbayar,
                'requestGratis'   => $requestGratis,
                'requestApproved' => $requestApproved,
                'requestRejected' => $requestRejected,
                'requestOnProgress' => $requestOnProgress,
            ],
            'indikator' => [
                'selesaiTepatWaktu'      => $selesaiTepatWaktu,
                'selesaiTidakTepatWaktu' => $selesaiTidakTepatWaktu,
                'belumDirespon'          => $belumDirespon,
            ],
            'taskByPersonil' => $taskByPersonil,
            'sprintSummary' => [
                'completed'  => (int) $completedTickets,
                'inProgress' => (int) $inProgress,
                'pending'    => (int) $pending,
            ],
            'myTasks'           => $myTasks,
            'recentProjects'    => $recentProjects,
            'upcomingDeadlines' => $upcomingDeadlines,
            'recentActivity'    => $recentActivity,
            'lastUpdated'       => now()->toISOString(),
        ];
    }

    public function analyticsIndex()
    {
        abort_unless($this->canViewAnalytics(), 403);

        $analytics = [
            'overview'  => $this->getOverviewStats(),
            'tickets'   => $this->getTicketAnalytics(),
            'teams'     => $this->getTeamPerformance(),
            'timelines' => $this->getTimelineAnalytics(),
            'users'     => $this->getUserProductivity(),
        ];

        return Inertia::render('analytics/page', [
            'analytics' => $analytics,
        ]);
    }

    private function getOverviewStats()
    {
        $totalTickets = Ticket::count();
        $activeProjects = Project::count();
        $totalTeams = Team::count();
        $totalUsers = User::count();
        
        $completedThisMonth = Ticket::where('status', 'done')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $driver = DB::getDriverName();
        $query = Ticket::where('status', 'done')
            ->whereNotNull('updated_at')
            ->whereNotNull('created_at');

        if ($driver === 'sqlite') {
            $avgCompletionTime = $query->selectRaw('AVG((julianday(updated_at) - julianday(created_at))) as avg_days')->value('avg_days');
        } elseif ($driver === 'pgsql') {
            $avgCompletionTime = $query->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days')->value('avg_days');
        } else {
            // Fallback for MySQL or others
            $avgCompletionTime = $query->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')->value('avg_days');
        }

        return [
            'totalTickets' => $totalTickets,
            'activeProjects' => $activeProjects,
            'totalTeams' => $totalTeams,
            'totalUsers' => $totalUsers,
            'completedThisMonth' => $completedThisMonth,
            'avgCompletionTime' => round($avgCompletionTime ?? 0, 1),
        ];
    }

    private function getTicketAnalytics()
    {
        $byStatus = Ticket::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn($item) => [
                'status' => ucfirst(str_replace('_', ' ', $item->status)),
                'count' => $item->count,
            ]);

        $byPriority = Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(fn($item) => [
                'priority' => ucfirst($item->priority),
                'count' => $item->count,
            ]);

        $byType = Ticket::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->map(fn($item) => [
                'type' => ucfirst($item->type),
                'count' => $item->count,
            ]);

        // Count tickets per user across pelaksana + delegated assignees.
        $byAssignee = User::select('id', 'name')->get()
            ->map(fn($user) => [
                'user' => $user->name,
                'count' => Ticket::handledByUser($user->id)->count(),
            ])
            ->filter(fn($item) => $item['count'] > 0)
            ->sortByDesc('count')
            ->take(10)
            ->values();

        $completionTrend = Ticket::where('status', 'done')
            ->where('updated_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(updated_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        return [
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'byType' => $byType,
            'byAssignee' => $byAssignee,
            'completionTrend' => $completionTrend,
        ];
    }

    private function getTeamPerformance()
    {
        $teams = Team::with('users:id')->withCount('users')->get();

        $workload = $teams->map(function ($team) {
            $userIds = $team->users->pluck('id')->all();
            $ticketCount = $userIds === [] ? 0 : Ticket::handledByAnyUser($userIds)->count();

            return [
                'team' => $team->name,
                'tickets' => $ticketCount,
                'members' => $team->users_count,
            ];
        });

        $completionRate = $teams->map(function ($team) {
            $userIds = $team->users->pluck('id')->all();
            $totalTickets = $userIds === [] ? 0 : Ticket::handledByAnyUser($userIds)->count();
            $completedTickets = $userIds === [] ? 0 : Ticket::handledByAnyUser($userIds)
                ->where('status', 'done')
                ->count();

            $rate = $totalTickets > 0 ? ($completedTickets / $totalTickets) * 100 : 0;

            return [
                'team' => $team->name,
                'rate' => round($rate, 1),
                'completed' => $completedTickets,
                'total' => $totalTickets,
            ];
        });

        return [
            'workload' => $workload,
            'completionRate' => $completionRate,
        ];
    }

    private function getTimelineAnalytics()
    {
        $byStatus = ProjectTimeline::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn($item) => [
                'status' => ucfirst($item->status),
                'count' => $item->count,
            ]);

        $progress = ProjectTimeline::with('tickets')
            ->get()
            ->map(function ($timeline) {
                $totalTickets = $timeline->tickets->count();
                $completedTickets = $timeline->tickets->where('status', 'done')->count();
                $progressPercentage = $totalTickets > 0 ? ($completedTickets / $totalTickets) * 100 : 0;

                return [
                    'timeline' => $timeline->title,
                    'progress' => round($progressPercentage, 1),
                    'completed' => $completedTickets,
                    'total' => $totalTickets,
                ];
            });

        return [
            'byStatus' => $byStatus,
            'progress' => $progress,
        ];
    }

    private function getUserProductivity()
    {
        $users = User::with('role:id,name,display_name')->get();

        // Count per user across BOTH pelaksana (assigned_to) and delegated
        // assignees (ticket_user pivot) so delegated work isn't reported as 0.
        $userActivity = $users
            ->map(function ($user) {
                $assigned   = Ticket::handledByUser($user->id)->count();
                $completed  = Ticket::handledByUser($user->id)->where('status', 'done')->count();
                $inProgress = Ticket::handledByUser($user->id)
                    ->whereIn('status', ['inprogress', 'not-appropriate'])
                    ->count();

                return [
                    'user' => $user->name,
                    'role' => $user->role->display_name ?? 'No Role',
                    'roleName' => $user->role->name ?? null,
                    'assigned' => $assigned,
                    'completed' => $completed,
                    'inProgress' => $inProgress,
                ];
            })
            ->sortByDesc('completed')
            ->values();

        $topContributors = $userActivity
            ->filter(fn($u) => $u['completed'] > 0)
            ->take(10)
            ->map(fn($u) => [
                'user' => $u['user'],
                'completed' => $u['completed'],
            ])
            ->values();

        $byRole = $users
            ->groupBy('role.display_name')
            ->map(fn($grouped, $role) => [
                'role' => $role ?: 'No Role',
                'count' => $grouped->count(),
            ])
            ->values();

        return [
            'topContributors' => $topContributors,
            'byRole' => $byRole,
            'userActivity' => $userActivity,
        ];
    }
    public function generateAIAnalysis(AiService $ai): JsonResponse
    {
        abort_unless($this->canViewAnalytics(), 403);

        $aiData = [
            'overview' => $this->getOverviewStats(),
            'tickets'  => $this->getTicketAnalytics(),
            'teams'    => $this->getTeamPerformance(),
            'users'    => $this->compactUserPayload($this->getUserProductivity()),
        ];

        if (! $ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'AI belum diaktifkan: GEMINI_API_KEY belum di-set di server.',
            ], 503);
        }

        $task = "Anda adalah Project Manager AI yang berpengalaman. "
            . "Analisis data manajemen proyek di bawah ini dan berikan insight kunci.\n\n"
            . "Fokus pada:\n"
            . "1. Kesehatan proyek secara umum.\n"
            . "2. Tim/individu dengan performa terbaik.\n"
            . "3. Bottleneck kritikal (tiket prioritas tinggi, timeline meleset).\n"
            . "4. Rekomendasi perbaikan yang konkret dan dapat ditindaklanjuti.\n\n"
            . "Format jawaban dalam Markdown profesional dan ringkas.";

        try {
            $analysis = $ai->analyze($task, json_encode($aiData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (RuntimeException $e) {
            Log::warning('Analytics AI analysis failed', ['msg' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'analysis' => $analysis,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Generate a weekly digest of team wellness from Daily Logs.
     *
     * Aggregates the last 7 days of daily logs into a single payload
     * (mood histogram, energy avg, top categories/tags, total minutes
     * logged, contributors) and asks Gemini to produce a short, Indonesian
     * narrative the PM can paste into a status email or standup.
     */
    public function weeklyDigest(AiService $ai): JsonResponse
    {
        abort_unless($this->canViewAnalytics(), 403);

        if (! $ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'AI belum diaktifkan: GEMINI_API_KEY belum di-set di server.',
            ], 503);
        }

        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $logs = DailyLog::with('user:id,name')
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Belum ada catatan harian di 7 hari terakhir untuk diringkas.',
            ], 422);
        }

        // Mood histogram (slug -> count). Slugs map to emoji/label catalog
        // on the frontend, no need to ship that here.
        $moodHistogram = $logs->whereNotNull('mood')
            ->groupBy('mood')
            ->map->count();

        $energyValues = $logs->pluck('energy_level')->filter(fn ($v) => $v !== null);
        $avgEnergy = $energyValues->isNotEmpty()
            ? round($energyValues->avg(), 1)
            : null;

        $byCategory = $logs->whereNotNull('category')
            ->groupBy('category')
            ->map->count()
            ->sortDesc();

        $totalMinutes = (int) $logs->whereNotNull('duration_minutes')->sum('duration_minutes');

        $topTags = $logs
            ->flatMap(fn ($log) => is_array($log->tags) ? $log->tags : [])
            ->countBy()
            ->sortDesc()
            ->take(8);

        $topContributors = $logs
            ->groupBy('user_id')
            ->map(fn ($items) => [
                'name' => $items->first()->user?->name ?? 'Unknown',
                'entries' => $items->count(),
                'minutes' => (int) $items->whereNotNull('duration_minutes')->sum('duration_minutes'),
            ])
            ->sortByDesc('entries')
            ->values()
            ->take(10);

        $payload = [
            'window' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => 7,
            ],
            'totals' => [
                'logs' => $logs->count(),
                'unique_authors' => $logs->pluck('user_id')->unique()->count(),
                'total_minutes' => $totalMinutes,
                'avg_energy_level' => $avgEnergy,
            ],
            'mood_histogram' => $moodHistogram,
            'category_histogram' => $byCategory,
            'top_tags' => $topTags,
            'top_contributors' => $topContributors,
        ];

        $task = "Anda adalah AI HR/PM untuk aplikasi manajemen proyek. "
            . "Berdasarkan ringkasan catatan harian tim selama 7 hari terakhir di bawah ini, buat digest mingguan dalam Bahasa Indonesia "
            . "dengan struktur Markdown berikut:\n\n"
            . "## Ringkasan Mingguan\n(narasi 2-3 kalimat)\n\n"
            . "### Mood & Energi Tim\n(insight singkat dari mood_histogram & avg_energy_level)\n\n"
            . "### Sorotan Aktivitas\n(insight dari category_histogram, top_tags, total_minutes)\n\n"
            . "### Kontributor Aktif\n(sebut 2-3 nama teratas dari top_contributors, jangan sebut angka detik/menit jika kosong)\n\n"
            . "### Rekomendasi\n(2-4 bullet rekomendasi konkret untuk PM, mis. soal beban kerja, mood drop, dll)\n\n"
            . "Hindari basa-basi, jangan ulang data mentah apa adanya, dan jangan sebut nama field JSON.";

        try {
            $digest = $ai->analyze($task, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (RuntimeException $e) {
            Log::warning('Daily logs weekly digest failed', ['msg' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'digest' => $digest,
            'stats' => $payload,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Trim the user productivity payload before sending to the LLM —
     * keeps the prompt cheap on the free tier without losing signal.
     *
     * @param  array<string, mixed>  $users
     * @return array<string, mixed>
     */
    private function compactUserPayload(array $users): array
    {
        $activity = $users['userActivity'];
        $activityArray = is_array($activity) ? $activity : $activity->toArray();

        return [
            'topContributors' => $users['topContributors'],
            'byRole' => $users['byRole'],
            'userActivity' => array_slice($activityArray, 0, 20),
        ];
    }
}
