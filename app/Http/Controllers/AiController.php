<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientActionLog;
use App\Models\CompanySetting;
use App\Models\DailyLog;
use App\Models\Minute;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\Role;
use App\Models\TaskType;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusLog;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin HTTP layer over App\Services\AiService.
 *
 * Every AI-powered feature in the frontend talks to these endpoints
 * (auth-only) so we never expose the DeepSeek/Groq keys to the browser and
 * we keep one place to add rate-limiting, auditing, etc.
 */
class AiController extends Controller
{
    public function __construct(protected AiService $ai)
    {
    }

    /**
     * POST /ai/check-similar-ticket
     */
    public function checkSimilarTicket(Request $request, \App\Services\AiTicketCopilotService $copilot): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'min:5'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $result = $copilot->findSimilarSolution($validated['description'], $validated['client_id'] ?? null);

        return response()->json($result);
    }

    /**
     * POST /ai/maintenance-report-draft — draft summary + work items from
     * tickets marked "done" for a client/project within a period.
     */
    public function maintenanceReportDraft(Request $request, \App\Services\AiMaintenanceReportService $drafter): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        return $this->safe(fn () => [
            'draft' => $drafter->draftFromDoneTickets(
                $validated['client_id'],
                $validated['project_id'] ?? null,
                $validated['period_start'],
                $validated['period_end'],
            ),
        ]);
    }

    /**
     * POST /ai/transcribe — multipart with `audio` file (webm/mp3/wav/m4a).
     */
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            // video/webm is intentional: PHP's finfo detects WebM audio as video/webm
            'audio' => ['required', 'file', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp3,audio/mpeg,audio/wav,audio/x-wav,audio/m4a,audio/mp4,video/mp4,audio/aac', 'max:20480'],
            'language' => ['nullable', 'string', 'max:16'],
        ]);

        return $this->safe(fn () => [
            'text' => $this->ai->transcribeAudio(
                $request->file('audio'),
                $request->input('language', 'id-ID'),
            ),
        ]);
    }

    /**
     * POST /ai/polish — clean up raw notes into professional prose.
     */
    public function polish(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:8000'],
            'context' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->safe(fn () => [
            'text' => $this->ai->polishText(
                $request->string('text')->toString(),
                $request->input('context'),
            ),
        ]);
    }

    /**
     * POST /ai/summarize — short professional summary.
     */
    public function summarize(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'max_sentences' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return $this->safe(fn () => [
            'text' => $this->ai->summarize(
                $request->string('text')->toString(),
                (int) $request->input('max_sentences', 3),
            ),
        ]);
    }

    /**
     * POST /ai/suggest-category — pick a slug from the allowed list.
     */
    public function suggestCategory(Request $request): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:4000'],
            'allowed' => ['required', 'array', 'min:1', 'max:50'],
            'allowed.*' => ['string', 'max:64'],
        ]);

        return $this->safe(fn () => [
            'category' => $this->ai->suggestCategory(
                $request->string('description')->toString(),
                $request->input('allowed', []),
            ),
        ]);
    }

    /**
     * POST /ai/chat-context — answer questions from current PM context.
     */
    public function chatContext(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:4000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        return $this->safe(function () use ($request) {
            $history = collect($request->input('history', []))
                ->filter(fn ($message) => is_array($message))
                ->map(fn ($message) => [
                    'role' => (string) ($message['role'] ?? 'user'),
                    'content' => Str::limit(trim((string) ($message['content'] ?? '')), 2000, ''),
                ])
                ->filter(fn ($message) => in_array($message['role'], ['user', 'assistant'], true) && $message['content'] !== '')
                ->values()
                ->all();

            return [
                'answer' => $this->ai->answerProjectQuestion(
                    $request->string('question')->toString(),
                    $this->buildChatContext($request),
                    $history,
                ),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * POST /ai/extract-decisions — extract decisions/action items from meeting text.
     */
    public function extractDecisions(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'min:10', 'max:20000'],
        ]);

        return $this->safe(fn () => [
            'decisions' => $this->ai->extractDecisions(
                $request->string('text')->toString(),
            ),
        ]);
    }

    /**
     * POST /ai/draft-ticket — turn a voice transcript into a safe ticket draft.
     */
    public function draftTicket(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $request->validate([
            'transcript' => ['required', 'string', 'min:5', 'max:8000'],
            'selected_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'selected_client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        return $this->safe(function () use ($request) {
            $selectedProjectId = $request->filled('selected_project_id')
                ? (int) $request->input('selected_project_id')
                : null;

            $selectedClientId = $request->filled('selected_client_id')
                ? (int) $request->input('selected_client_id')
                : null;

            $projects = Project::with(['team.users:id,name', 'client:id,kode,nama'])
                ->select('id', 'name', 'status', 'team_id', 'client_id')
                ->orderBy('name')
                ->get();

            $clients = Client::select('id', 'kode', 'nama', 'deskripsi', 'is_active')
                ->where('is_active', true)
                ->orderBy('nama')
                ->get();

            $allUsers = User::select('id', 'name')->orderBy('name')->get();

            $timelines = ProjectTimeline::select('id', 'project_id', 'title', 'type', 'status')
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('created_at', 'desc')
                ->get();

            $taskTypes = TaskType::select('id', 'nama')->orderBy('nama')->get();

            $context = [
                'selected_project_id' => $selectedProjectId,
                'selected_client_id' => $selectedClientId,
                'clients' => $clients->map(fn (Client $client) => [
                    'id' => (int) $client->id,
                    'code' => $client->kode,
                    'name' => $client->nama,
                    'description' => $this->compactValue($client->deskripsi, 80),
                ])->values()->all(),
                'projects' => $projects->map(function (Project $project) {
                    $members = $project->team
                        ? $project->team->users->map(fn ($user) => [
                            'id' => (int) $user->id,
                            'name' => $user->name,
                        ])->values()->all()
                        : [];

                    return [
                        'id' => (int) $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'client_id' => $project->client_id ? (int) $project->client_id : null,
                        'client' => $project->client ? [
                            'id' => (int) $project->client->id,
                            'code' => $project->client->kode,
                            'name' => $project->client->nama,
                        ] : null,
                        'members' => $members,
                    ];
                })->values()->all(),
                'timelines' => $timelines->map(fn (ProjectTimeline $timeline) => [
                    'id' => (int) $timeline->id,
                    'project_id' => (int) $timeline->project_id,
                    'title' => $timeline->title,
                    'type' => $timeline->type,
                    'status' => $timeline->status,
                ])->values()->all(),
                'task_types' => $taskTypes->map(fn (TaskType $taskType) => [
                    'id' => (int) $taskType->id,
                    'name' => $taskType->nama,
                ])->values()->all(),
                'all_users' => $allUsers->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                ])->values()->all(),
            ];

            $rawDraft = $this->ai->draftTicket(
                $request->string('transcript')->toString(),
                $context,
            );

            return [
                'draft' => $this->sanitizeTicketDraft(
                    $rawDraft,
                    $clients,
                    $projects,
                    $timelines,
                    $taskTypes,
                    $allUsers,
                    $selectedClientId,
                    $selectedProjectId,
                ),
            ];
        });
    }

    /**
     * Build a compact read-only PM snapshot for the floating AI chat.
     *
     * @return array<string, mixed>
     */
    protected function buildChatContext(Request $request): array
    {
        $user = $request->user();
        $now = now();
        $since = $now->copy()->subDays(6)->toDateString();
        $lastWeek = $now->copy()->subDays(6)->toDateString();

        $canViewPeople = $this->hasAnyPermission($user, [
            'manage-users',
            'users.manage',
            'manage-daily-logs',
            'daily-logs.manage',
        ]);
        $canViewTeamLogs = $this->hasAnyPermission($user, ['manage-daily-logs', 'daily-logs.manage']);
        $canViewClients = $this->hasAnyPermission($user, ['manage-clients', 'clients.manage', 'clients.view']);
        $canViewCompany = $this->hasAnyPermission($user, ['manage-company', 'companies.manage']);
        $canViewRoles = $this->hasAnyPermission($user, ['manage-roles', 'roles.manage']);

        $ticketScope = Ticket::query();
        $this->applyTicketVisibility($ticketScope, $user);

        $openTicketsBase = (clone $ticketScope)->whereNotIn('status', ['done']);

        $ticketStatusCounts = (clone $ticketScope)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $ticketPriorityCounts = (clone $ticketScope)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $ticketTypeCounts = (clone $ticketScope)
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $projectTicketStats = (clone $ticketScope)
            ->select(
                'project_id',
                DB::raw('count(*) as total_count'),
                DB::raw("sum(case when status = 'done' then 1 else 0 end) as done_count"),
                DB::raw("sum(case when status != 'done' then 1 else 0 end) as open_count"),
            )
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $company = $canViewCompany ? CompanySetting::current() : null;

        $clients = $canViewClients
            ? Client::query()
                ->select('id', 'kode', 'nama', 'kontak', 'is_active', 'updated_at', 'created_at')
                ->withCount(['projects'])
                ->orderByDesc('is_active')
                ->orderBy('nama')
                ->limit(15)
                ->get()
                ->map(fn (Client $client) => [
                    'id' => (int) $client->id,
                    'code' => $client->kode,
                    'name' => $client->nama,
                    'contact' => $this->compactValue($client->kontak),
                    'is_active' => (bool) $client->is_active,
                    'projects_count' => (int) $client->projects_count,
                    'updated_at' => $client->updated_at?->toDateTimeString(),
                ])
                ->values()
            : collect();

        $projects = Project::query()
            ->with(['client:id,kode,nama,is_active', 'team:id,name', 'projectManager:id,name', 'creator:id,name'])
            ->select('id', 'name', 'description', 'status', 'client_id', 'team_id', 'project_manager_id', 'created_by', 'kode_project', 'jenis_pekerjaan', 'marketing_internal', 'start_date', 'end_date', 'tgl_mulai_kontrak', 'tgl_selesai_kontrak', 'tgl_implementasi', 'tgl_selesai_implementasi', 'updated_at')
            ->withCount(['timelines'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (Project $project) use ($projectTicketStats) {
                $ticketStats = $projectTicketStats->get($project->id);

                return [
                    'id' => (int) $project->id,
                    'name' => $project->name,
                    'code' => $project->kode_project,
                    'status' => $project->status,
                    'description' => $this->compactValue($project->description, 120),
                    'client' => $project->client ? [
                        'name' => $project->client->nama,
                        'code' => $project->client->kode,
                        'is_active' => (bool) $project->client->is_active,
                    ] : null,
                    'team' => $project->team?->name,
                    'project_manager' => $project->projectManager?->name,
                    'created_by' => $project->creator?->name,
                    'job_type' => $project->jenis_pekerjaan,
                    'marketing_internal' => $project->marketing_internal,
                    'timelines_count' => (int) $project->timelines_count,
                    'tickets_total' => (int) ($ticketStats?->total_count ?? 0),
                    'tickets_open' => (int) ($ticketStats?->open_count ?? 0),
                    'tickets_done' => (int) ($ticketStats?->done_count ?? 0),
                    'start_date' => $project->start_date?->toDateString(),
                    'end_date' => $project->end_date?->toDateString(),
                    'contract_start_date' => $project->tgl_mulai_kontrak?->toDateString(),
                    'contract_end_date' => $project->tgl_selesai_kontrak?->toDateString(),
                    'implementation_start_date' => $project->tgl_implementasi?->toDateString(),
                    'implementation_end_date' => $project->tgl_selesai_implementasi?->toDateString(),
                    'updated_at' => $project->updated_at?->toDateTimeString(),
                ];
            })
            ->values();

        $timelines = ProjectTimeline::query()
            ->with(['project:id,name,client_id,team_id', 'project.client:id,kode,nama', 'project.team:id,name', 'creator:id,name'])
            ->select('id', 'project_id', 'type', 'phase', 'title', 'description', 'start_date', 'end_date', 'status', 'sprint_number', 'deliverables', 'created_by', 'updated_at', 'created_at')
            ->withCount([
                'tickets' => fn ($query) => $this->applyTicketVisibility($query, $user),
                'tickets as done_tickets_count' => fn ($query) => $this->applyTicketVisibility($query, $user)->where('status', 'done'),
                'tickets as open_tickets_count' => fn ($query) => $this->applyTicketVisibility($query, $user)->whereNotIn('status', ['done']),
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (ProjectTimeline $timeline) => [
                'id' => (int) $timeline->id,
                'project' => $timeline->project?->name,
                'client' => $timeline->project?->client?->nama,
                'team' => $timeline->project?->team?->name,
                'type' => $timeline->type,
                'phase' => $timeline->phase,
                'title' => $timeline->title,
                'description' => $this->compactValue($timeline->description, 100),
                'status' => $timeline->status,
                'sprint_number' => $timeline->sprint_number,
                'deliverables' => collect($timeline->deliverables ?? [])->take(8)->values(),
                'tickets_total' => (int) $timeline->tickets_count,
                'tickets_open' => (int) $timeline->open_tickets_count,
                'tickets_done' => (int) $timeline->done_tickets_count,
                'start_date' => $timeline->start_date?->toDateString(),
                'end_date' => $timeline->end_date?->toDateString(),
                'created_by' => $timeline->creator?->name,
                'updated_at' => $timeline->updated_at?->toDateTimeString(),
            ])
            ->values();

        $tickets = (clone $openTicketsBase)
            ->with(['client:id,kode,nama', 'project:id,name,client_id', 'project.client:id,kode,nama', 'timeline:id,title,type', 'taskType:id,nama', 'assignedUser:id,name', 'reporter:id,name', 'approver:id,name', 'delegatedUser:id,name'])
            ->select('id', 'client_id', 'project_id', 'timeline_id', 'task_type_id', 'reporter_id', 'assigned_to', 'approved_by', 'delegated_to', 'ticket_number', 'title', 'description', 'type', 'request_type', 'priority', 'status', 'due_date', 'estimated_hours', 'actual_hours', 'story_points', 'tags', 'approved_at', 'rejected_at', 'delegated_at', 'updated_at', 'created_at')
            ->orderByRaw("case priority when 'highest' then 1 when 'high' then 2 when 'medium' then 3 when 'low' then 4 else 5 end")
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => (int) $ticket->id,
                'number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'description' => $this->compactValue($ticket->description, 120),
                'project' => $ticket->project?->name,
                'client' => $ticket->client?->nama ?? $ticket->project?->client?->nama,
                'timeline' => $ticket->timeline?->title,
                'task_type' => $ticket->taskType?->nama,
                'assignee' => $ticket->assignedUser?->name,
                'reporter' => $ticket->reporter?->name,
                'approved_by' => $ticket->approver?->name,
                'delegated_to' => $ticket->delegatedUser?->name,
                'type' => $ticket->type,
                'request_type' => $ticket->request_type,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'story_points' => $ticket->story_points,
                'estimated_hours' => $ticket->estimated_hours,
                'actual_hours' => $ticket->actual_hours,
                'due_date' => $ticket->due_date?->toDateString(),
                'tags' => $ticket->tags,
                'approved_at' => $ticket->approved_at?->toDateTimeString(),
                'updated_at' => $ticket->updated_at?->toDateTimeString(),
            ])
            ->values();

        $teamsQuery = Team::query()
            ->with(['projectManager:id,name', 'users:id,name,role_id', 'users.role:id,name,display_name'])
            ->select('id', 'name', 'description', 'color', 'project_manager_id', 'updated_at', 'created_at')
            ->withCount(['users'])
            ->orderBy('name')
            ->limit(8);

        if (! $canViewPeople) {
            $teamsQuery->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
        }

        $teams = $teamsQuery
            ->get()
            ->map(fn (Team $team) => [
                'id' => (int) $team->id,
                'name' => $team->name,
                'description' => $this->compactValue($team->description, 160),
                'project_manager' => $team->projectManager?->name,
                'members_count' => (int) $team->users_count,
                'members' => $canViewPeople
                    ? $team->users->take(8)->map(fn (User $member) => [
                        'name' => $member->name,
                        'role' => $member->role?->display_name ?? $member->role?->name,
                    ])->values()
                    : [],
                'updated_at' => $team->updated_at?->toDateTimeString(),
            ])
            ->values();

        $timeByUser = TimeLog::query()
            ->select('user_id', DB::raw('coalesce(sum(duration_seconds), 0) as total_seconds'))
            ->whereHas('ticket', fn ($query) => $this->applyTicketVisibility($query, $user))
            ->where('start_time', '>=', $lastWeek)
            ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
            ->groupBy('user_id')
            ->pluck('total_seconds', 'user_id');

        $dailyLogsByUser = DailyLog::query()
            ->select('user_id', DB::raw('count(*) as entries'), DB::raw('coalesce(sum(duration_minutes), 0) as total_minutes'))
            ->whereDate('log_date', '>=', $lastWeek)
            ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $peopleQuery = User::query()
            ->select('id', 'name', 'email', 'role_id', 'created_at')
            ->with(['role:id,name,display_name', 'teams:id,name'])
            ->withCount([
                'assignedTickets as open_tickets_count' => fn ($query) => $this->applyTicketVisibility($query, $user)->whereNotIn('status', ['done']),
                'assignedTickets as done_tickets_count' => fn ($query) => $this->applyTicketVisibility($query, $user)->where('status', 'done'),
            ])
            ->orderByDesc('open_tickets_count')
            ->limit(8);

        if (! $canViewPeople) {
            $peopleQuery->where('id', $user->id);
        }

        $people = $peopleQuery
            ->get()
            ->map(function (User $person) use ($timeByUser, $dailyLogsByUser, $canViewPeople) {
                $daily = $dailyLogsByUser->get($person->id);

                return [
                    'id' => (int) $person->id,
                    'name' => $person->name,
                    'email' => $canViewPeople ? $person->email : null,
                    'role' => $person->role?->display_name ?? $person->role?->name,
                    'teams' => $person->teams->pluck('name')->values(),
                    'open_tickets' => (int) $person->open_tickets_count,
                    'done_tickets' => (int) $person->done_tickets_count,
                    'daily_logs_last_7_days' => (int) ($daily?->entries ?? 0),
                    'daily_log_minutes_last_7_days' => (int) ($daily?->total_minutes ?? 0),
                    'time_tracked_seconds_last_7_days' => (int) ($timeByUser->get($person->id) ?? 0),
                    'created_at' => $person->created_at?->toDateTimeString(),
                ];
            })
            ->values();

        $taskTypes = TaskType::query()
            ->select('id', 'nama')
            ->withCount(['tickets' => fn ($query) => $this->applyTicketVisibility($query, $user)])
            ->orderBy('nama')
            ->get()
            ->map(fn (TaskType $type) => [
                'id' => (int) $type->id,
                'name' => $type->nama,
                'tickets_count' => (int) $type->tickets_count,
            ])
            ->values();

        $roles = $canViewRoles
            ? Role::query()
                ->select('id', 'name', 'display_name', 'description')
                ->withCount(['users'])
                ->orderBy('display_name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $this->compactValue($role->description, 160),
                    'users_count' => (int) $role->users_count,
                ])
                ->values()
            : collect();

        $dailyLogs = DailyLog::query()
            ->with(['user:id,name', 'ticket:id,ticket_number,title,project_id', 'ticket.project:id,name'])
            ->select('id', 'user_id', 'ticket_id', 'log_number', 'log_date', 'category', 'description', 'mood', 'energy_level', 'duration_minutes', 'tags', 'is_automated', 'created_at')
            ->whereDate('log_date', '>=', $since)
            ->when(! $canViewTeamLogs, fn ($query) => $query->where('user_id', $user->id))
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (DailyLog $log) => [
                'id' => (int) $log->id,
                'number' => $log->log_number,
                'date' => $log->log_date?->toDateString(),
                'user' => $log->user?->name,
                'ticket' => $log->ticket ? [
                    'number' => $log->ticket->ticket_number,
                    'title' => $log->ticket->title,
                    'project' => $log->ticket->project?->name,
                ] : null,
                'category' => $log->category,
                'mood' => $log->mood,
                'energy_level' => $log->energy_level,
                'duration_minutes' => $log->duration_minutes,
                'tags' => $log->tags,
                'is_automated' => (bool) $log->is_automated,
                'description' => $this->compactValue($log->description, 120),
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->values();

        $statusChanges = TicketStatusLog::query()
            ->with(['ticket:id,ticket_number,title,project_id', 'ticket.project:id,name', 'user:id,name'])
            ->select('id', 'ticket_id', 'user_id', 'from_status', 'to_status', 'story_points', 'changed_at')
            ->whereHas('ticket', fn ($query) => $this->applyTicketVisibility($query, $user))
            ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
            ->latest('changed_at')
            ->limit(8)
            ->get()
            ->map(fn (TicketStatusLog $log) => [
                'id' => (int) $log->id,
                'at' => $log->changed_at?->toDateTimeString(),
                'user' => $log->user?->name,
                'ticket' => $log->ticket ? [
                    'number' => $log->ticket->ticket_number,
                    'title' => $log->ticket->title,
                    'project' => $log->ticket->project?->name,
                ] : null,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'story_points' => $log->story_points,
            ])
            ->values();

        $ticketComments = TicketComment::query()
            ->with(['ticket:id,ticket_number,title,project_id', 'ticket.project:id,name', 'user:id,name'])
            ->select('id', 'ticket_id', 'user_id', 'comment', 'type', 'is_internal', 'created_at')
            ->whereHas('ticket', fn ($query) => $this->applyTicketVisibility($query, $user))
            ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (TicketComment $comment) => [
                'id' => (int) $comment->id,
                'at' => $comment->created_at?->toDateTimeString(),
                'user' => $comment->user?->name,
                'ticket' => $comment->ticket ? [
                    'number' => $comment->ticket->ticket_number,
                    'title' => $comment->ticket->title,
                    'project' => $comment->ticket->project?->name,
                ] : null,
                'type' => $comment->type,
                'is_internal' => (bool) $comment->is_internal,
                'comment' => $this->compactValue($comment->comment, 120),
            ])
            ->values();

        $timeLogs = TimeLog::query()
            ->with(['ticket:id,ticket_number,title,project_id', 'ticket.project:id,name', 'user:id,name'])
            ->select('id', 'ticket_id', 'user_id', 'start_time', 'end_time', 'duration_seconds', 'note', 'created_at')
            ->whereHas('ticket', fn ($query) => $this->applyTicketVisibility($query, $user))
            ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
            ->latest('start_time')
            ->limit(6)
            ->get()
            ->map(fn (TimeLog $log) => [
                'id' => (int) $log->id,
                'start_time' => $log->start_time?->toDateTimeString(),
                'end_time' => $log->end_time?->toDateTimeString(),
                'user' => $log->user?->name,
                'ticket' => $log->ticket ? [
                    'number' => $log->ticket->ticket_number,
                    'title' => $log->ticket->title,
                    'project' => $log->ticket->project?->name,
                ] : null,
                'duration_seconds' => $log->duration_seconds,
                'note' => $this->compactValue($log->note, 100),
            ])
            ->values();

        $clientActionLogs = $canViewClients
            ? ClientActionLog::query()
                ->with(['client:id,kode,nama', 'user:id,name'])
                ->select('id', 'client_id', 'user_id', 'action', 'old_data', 'new_data', 'created_at')
                ->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (ClientActionLog $log) => [
                    'id' => (int) $log->id,
                    'at' => $log->created_at?->toDateTimeString(),
                    'client' => $log->client ? [
                        'name' => $log->client->nama,
                        'code' => $log->client->kode,
                    ] : null,
                    'user' => $log->user?->name,
                    'action' => $log->action,
                    'changes' => $this->compactChanges($log->old_data, $log->new_data),
                ])
                ->values()
            : collect();

        $notifications = $user->notifications()
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? 'unknown',
                'title' => $notification->data['title'] ?? '',
                'message' => $this->compactValue($notification->data['message'] ?? '', 100),
                'actor' => $notification->data['updated_by']
                    ?? $notification->data['added_by']
                    ?? $notification->data['removed_by']
                    ?? $notification->data['assigned_by']
                    ?? 'System',
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toDateTimeString(),
            ])
            ->values();

        $activeClients = $clients->where('is_active', true)->values();
        $inactiveClients = $clients->where('is_active', false)->values();

        return [
            'generated_at' => $now->toIso8601String(),
            'coverage_note' => 'Snapshot ringkas lintas modul. Daftar panjang dibatasi; angka overview memakai query count dari database.',
            'viewer' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'role' => $user->role?->display_name ?? $user->role?->name,
                'visibility' => [
                    'people' => $canViewPeople ? 'team' : 'self',
                    'daily_logs' => $canViewTeamLogs ? 'team' : 'self',
                    'clients' => $canViewClients ? 'available' : 'restricted',
                    'company' => $canViewCompany ? 'available' : 'restricted',
                    'roles' => $canViewRoles ? 'available' : 'restricted',
                    'notifications' => 'viewer_only',
                ],
            ],
            'module_catalog' => [
                ['key' => 'dashboard', 'name' => 'Dasbor', 'available' => true, 'coverage' => 'overview counts, my tasks, recent activity'],
                ['key' => 'analytics', 'name' => 'Analitik', 'available' => true, 'coverage' => 'ticket/project/person workload summaries'],
                ['key' => 'company', 'name' => 'Perusahaan', 'available' => $canViewCompany, 'coverage' => 'company profile'],
                ['key' => 'clients', 'name' => 'Klien', 'available' => $canViewClients, 'coverage' => 'active/inactive clients, project counts, client action logs'],
                ['key' => 'teams', 'name' => 'Tim', 'available' => true, 'coverage' => 'team list, PM, members when allowed'],
                ['key' => 'users', 'name' => 'Pengguna', 'available' => $canViewPeople, 'coverage' => 'role, teams, workload, logs, tracked time'],
                ['key' => 'roles', 'name' => 'Hak Akses', 'available' => $canViewRoles, 'coverage' => 'role list and user counts'],
                ['key' => 'projects', 'name' => 'Proyek', 'available' => true, 'coverage' => 'project metadata, client/team/PM, ticket and timeline counts'],
                ['key' => 'timelines', 'name' => 'Linimasa', 'available' => true, 'coverage' => 'timeline phase, dates, status, ticket progress'],
                ['key' => 'tickets', 'name' => 'Tiket', 'available' => true, 'coverage' => 'open tickets, status/priority/type summaries, comments/status/time activities'],
                ['key' => 'task_types', 'name' => 'Jenis Tugas', 'available' => true, 'coverage' => 'task type list and ticket counts'],
                ['key' => 'daily_logs', 'name' => 'Catatan Harian', 'available' => true, 'coverage' => 'recent personal/team logs based on permission'],
                ['key' => 'notifications', 'name' => 'Notifikasi', 'available' => true, 'coverage' => 'current viewer notifications only'],
                ['key' => 'minutes', 'name' => 'Notulensi', 'available' => true, 'coverage' => 'meeting minutes, decisions, action items — last 30 days'],
                ['key' => 'reports', 'name' => 'Laporan', 'available' => true, 'coverage' => 'completed projects list, exportable reports for projects/tickets/daily logs/minutes'],
            ],
            'overview' => [
                'companies_configured' => $company ? 1 : 0,
                'clients_total' => $canViewClients ? Client::count() : null,
                'clients_active' => $canViewClients ? Client::where('is_active', true)->count() : null,
                'clients_inactive' => $canViewClients ? Client::where('is_active', false)->count() : null,
                'teams_total' => Team::count(),
                'users_total' => $canViewPeople ? User::count() : null,
                'projects_total' => Project::count(),
                'projects_active' => Project::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'timelines_total' => ProjectTimeline::count(),
                'timelines_open' => ProjectTimeline::whereIn('status', ['pending', 'in_progress', 'delayed'])->count(),
                'tickets_total' => (clone $ticketScope)->count(),
                'tickets_open' => (clone $openTicketsBase)->count(),
                'tickets_due_soon' => (clone $openTicketsBase)->whereNotNull('due_date')->whereDate('due_date', '<=', $now->copy()->addDays(7)->toDateString())->count(),
                'tickets_overdue' => (clone $openTicketsBase)->whereNotNull('due_date')->whereDate('due_date', '<', $now->toDateString())->count(),
                'daily_logs_last_7_days' => DailyLog::query()->whereDate('log_date', '>=', $lastWeek)->when(! $canViewTeamLogs, fn ($query) => $query->where('user_id', $user->id))->count(),
                'time_tracked_seconds_last_7_days' => (int) TimeLog::query()->whereHas('ticket', fn ($query) => $this->applyTicketVisibility($query, $user))->where('start_time', '>=', $lastWeek)->when(! $canViewPeople, fn ($query) => $query->where('user_id', $user->id))->sum('duration_seconds'),
                'notifications_unread' => $user->unreadNotifications()->count(),
                'tickets_by_status' => $ticketStatusCounts,
                'tickets_by_priority' => $ticketPriorityCounts,
                'tickets_by_type' => $ticketTypeCounts,
            ],
            'company' => $company ? [
                'code' => $company->kode,
                'name' => $company->nama_perusahaan,
                'phone' => $company->telp,
                'email' => $company->email,
                'address' => $this->compactValue($company->alamat, 180),
            ] : null,
            'clients' => [
                'active' => $activeClients,
                'inactive' => $inactiveClients,
                'limited_to' => $canViewClients ? 50 : 0,
            ],
            'teams' => $teams,
            'people_workload' => $people,
            'roles' => $roles,
            'task_types' => $taskTypes,
            'recent_projects' => $projects,
            'recent_timelines' => $timelines,
            'important_open_tickets' => $tickets,
            'recent_daily_logs' => $dailyLogs,
            'recent_ticket_status_changes' => $statusChanges,
            'recent_ticket_comments' => $ticketComments,
            'recent_time_logs' => $timeLogs,
            'recent_client_action_logs' => $clientActionLogs,
            'notifications' => $notifications,
            'recent_minutes' => Minute::with(['project:id,name', 'creator:id,name'])
                ->where('meeting_date', '>=', now()->subDays(30)->toDateString())
                ->latest('meeting_date')
                ->limit(5)
                ->get()
                ->map(fn (Minute $m) => [
                    'id' => (int) $m->id,
                    'title' => $m->title,
                    'meeting_date' => $m->meeting_date?->toDateString(),
                    'project' => $m->project?->name,
                    'creator' => $m->creator?->name,
                    'attendees' => collect($m->attendees ?? [])->pluck('name')->values(),
                    'summary' => $this->compactValue($m->summary, 200),
                    'decisions_count' => count($m->decisions ?? []),
                    'decisions' => collect($m->decisions ?? [])->take(3)->map(fn ($d) => [
                        'text' => $this->compactValue($d['text'] ?? '', 100),
                        'owner_name' => $d['owner_name'] ?? null,
                        'due_date' => $d['due_date'] ?? null,
                    ])->values(),
                ])
                ->values(),
        ];
    }

    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function applyTicketVisibility($query, User $user)
    {
        if ($user->role && $user->role->name === 'programmer') {
            $query->whereNotNull('approved_at');
        }

        return $query;
    }

    protected function compactValue(mixed $value, int $limit = 120): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Str::limit(trim($value), $limit, '');
        }

        return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', $limit, '');
    }

    /**
     * @return array<string, array{from:mixed, to:mixed}>
     */
    protected function compactChanges(?array $oldData, ?array $newData): array
    {
        $oldData ??= [];
        $newData ??= [];

        return collect(array_unique(array_merge(array_keys($oldData), array_keys($newData))))
            ->take(8)
            ->mapWithKeys(fn ($key) => [
                (string) $key => [
                    'from' => $this->compactValue($oldData[$key] ?? null, 80),
                    'to' => $this->compactValue($newData[$key] ?? null, 80),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function sanitizeTicketDraft(array $raw, $clients, $projects, $timelines, $taskTypes, $allUsers, ?int $selectedClientId, ?int $selectedProjectId): array
    {
        $warnings = collect($raw["warnings"] ?? [])
            ->filter(fn ($warning) => is_string($warning) && trim($warning) !== "")
            ->map(fn ($warning) => Str::limit(trim($warning), 160, ""))
            ->reject(fn ($warning) => preg_match("/\b(project_id|assigned_to)\b.*tidak yakin/i", $warning)
                || preg_match("/\bproyek\b.*(tidak yakin|belum bisa dipastikan)/i", $warning)
                || preg_match("/\b(assignee|pelaksana|ditugaskan)\b.*tidak yakin/i", $warning))
            ->values();

        $validClientIds = $clients->pluck("id")->map(fn ($id) => (int) $id)->all();
        $rawClientId = $this->validId($raw["client_id"] ?? null, $validClientIds);

        $validProjectIds = $projects->pluck("id")->map(fn ($id) => (int) $id)->all();
        $rawProjectWasProvided = array_key_exists("project_id", $raw) && $raw["project_id"] !== null && $raw["project_id"] !== "";
        $rawProjectId = $this->validId($raw["project_id"] ?? null, $validProjectIds);
        $projectId = $selectedProjectId ?: $rawProjectId;
        $project = $projectId ? $projects->firstWhere("id", $projectId) : null;
        $projectClientId = $project?->client_id ? (int) $project->client_id : null;
        $clientId = $selectedClientId ?: $rawClientId ?: $projectClientId;

        if ($selectedClientId && $rawClientId && $rawClientId !== $selectedClientId) {
            $warnings->push("AI menyebut client lain, tetapi draft memakai client yang sedang dipilih.");
        }

        if ($selectedProjectId && $rawProjectId && $rawProjectId !== $selectedProjectId) {
            $warnings->push("AI menyebut proyek lain, tetapi draft memakai proyek yang sedang dipilih.");
        }

        if ($rawProjectWasProvided && ! $rawProjectId) {
            $warnings->push("Proyek dari AI tidak ada di konteks valid.");
        }

        if ($project && $projectClientId) {
            if ($clientId && $projectClientId !== $clientId) {
                if ($selectedProjectId) {
                    $clientId = $projectClientId;
                    $warnings->push("Proyek yang dipilih memakai client berbeda, draft mengikuti client proyek.");
                } else {
                    $projectId = null;
                    $project = null;
                    $projectClientId = null;
                    $warnings->push("Proyek dari AI tidak sesuai dengan client yang valid.");
                }
            } elseif (! $clientId) {
                $clientId = $projectClientId;
            }
        }

        if (! $clientId) {
            $warnings->push("Client belum bisa dipastikan dari transkrip.");
        }

        $rawTimelineWasProvided = array_key_exists("timeline_id", $raw) && $raw["timeline_id"] !== null && $raw["timeline_id"] !== "";
        $validTimelineIds = $projectId
            ? $timelines->where("project_id", $projectId)->pluck("id")->map(fn ($id) => (int) $id)->all()
            : [];
        $timelineId = $this->validId($raw["timeline_id"] ?? null, $validTimelineIds);
        if ($rawTimelineWasProvided && ! $timelineId) {
            $warnings->push($projectId
                ? "Timeline dari AI tidak cocok dengan proyek yang valid."
                : "Timeline dari AI diabaikan karena proyek belum dipilih.");
        }

        $taskTypeId = $this->validId(
            $raw["task_type_id"] ?? null,
            $taskTypes->pluck("id")->map(fn ($id) => (int) $id)->all(),
        );

        $teamUserIds = $project?->team?->users?->pluck("id")->map(fn ($id) => (int) $id)->all() ?? [];
        $allUserIds = $allUsers->pluck("id")->map(fn ($id) => (int) $id)->all();
        $assigneeScopeIds = $project ? $teamUserIds : $allUserIds;
        $rawAssigneeWasProvided = array_key_exists("assigned_to", $raw) && $raw["assigned_to"] !== null && $raw["assigned_to"] !== "";
        $assignedTo = $this->validId($raw["assigned_to"] ?? null, $assigneeScopeIds);
        if ($rawAssigneeWasProvided && ! $assignedTo) {
            $warnings->push($project
                ? "Pelaksana dari AI tidak ada di tim proyek yang valid."
                : "Pelaksana dari AI tidak ada di daftar user valid.");
        }

        $ticketTypes = ["bug", "feature", "task", "improvement", "documentation"];
        $priorities = ["highest", "high", "medium", "low", "lowest"];
        $statuses = ["todo", "pending", "inprogress", "qa-ready", "qa-test", "review", "done"];
        $requestTypes = ["berbayar", "gratis"];
        $storyPoints = [1, 2, 3, 5, 8, 13, 21];

        $type = in_array($raw["type"] ?? null, $ticketTypes, true) ? $raw["type"] : "task";
        $priority = in_array($raw["priority"] ?? null, $priorities, true) ? $raw["priority"] : "low";
        $status = in_array($raw["status"] ?? null, $statuses, true) ? $raw["status"] : "todo";
        $requestType = in_array($raw["request_type"] ?? null, $requestTypes, true) ? $raw["request_type"] : null;

        if ($requestType === "berbayar") {
            $status = "pending";
        } elseif (in_array($status, ["pending", "backlog"], true)) {
            $status = "todo";
        }

        $dueDate = $this->cleanText($raw["due_date"] ?? null, 10);
        if ($dueDate && ! preg_match("/^\d{4}-\d{2}-\d{2}$/", $dueDate)) {
            $dueDate = null;
            $warnings->push("Tanggal tenggat dari AI tidak memakai format valid.");
        }

        $estimatedHours = is_numeric($raw["estimated_hours"] ?? null)
            ? max(0, (float) $raw["estimated_hours"])
            : null;

        $rawStoryPoints = is_numeric($raw["story_points"] ?? null) ? (int) $raw["story_points"] : null;
        $storyPoint = in_array($rawStoryPoints, $storyPoints, true) ? $rawStoryPoints : null;

        $tags = collect(is_array($raw["tags"] ?? null) ? $raw["tags"] : [])
            ->filter(fn ($tag) => is_scalar($tag) && trim((string) $tag) !== "")
            ->map(fn ($tag) => Str::slug(trim((string) $tag)))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return [
            "title" => $this->cleanText($raw["title"] ?? null, 90) ?: "Tiket dari voice",
            "description" => $this->cleanText($raw["description"] ?? null, 4000),
            "client_id" => $clientId,
            "project_id" => $projectId,
            "timeline_id" => $timelineId,
            "type" => $type,
            "task_type_id" => $taskTypeId,
            "request_type" => $requestType,
            "priority" => $priority,
            "status" => $status,
            "assigned_to" => $assignedTo,
            "due_date" => $dueDate,
            "estimated_hours" => $estimatedHours,
            "story_points" => $storyPoint,
            "tags" => $tags,
            "warnings" => $warnings->unique()->values()->all(),
        ];
    }

    protected function validId($value, array $validIds): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return in_array($id, $validIds, true) ? $id : null;
    }

    protected function cleanText($value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    /**
     * Wrap an AI call so the frontend always gets a uniform JSON shape and
     * a friendly Indonesian error message when something goes wrong.
     */
    protected function safe(\Closure $fn): JsonResponse
    {
        if (! $this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'AI belum diaktifkan: DEEPSEEK_API_KEY belum di-set di server.',
            ], 503);
        }

        try {
            return response()->json(['ok' => true] + $fn());
        } catch (RuntimeException $e) {
            Log::warning('AI call failed', ['msg' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
