<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\TaskType;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAttachmentStorage;
use App\Services\TicketNumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct(private readonly TicketNumberingService $ticketNumbering)
    {
    }

    public function index(Request $request)
    {
        $query = Ticket::with([
            'client:id,kode,nama,deskripsi,is_active',
            'project:id,name,status,client_id,team_id',
            'project.client:id,kode,nama',
            'timeline:id,project_id,title,type',
            'taskType:id,nama',
            'reporter:id,name,avatar_path',
            'assignedUser:id,name,avatar_path',
            'assignees:id,name,avatar_path',
            'approver:id,name,avatar_path',
            'rejecter:id,name,avatar_path',
            'delegatedUser:id,name,avatar_path',
            'comments.user:id,name,avatar_path',
            'comments.client:id,nama',
            'comments.reactions',
        ])->withCount(['comments as comments_count']);

        $user = $request->user();
        $this->applyTicketVisibility($query, $user);
        $this->applyTicketView($query, $request->input('view', 'active'));

        if ($request->filled('client_id') && $request->client_id !== 'all') {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id') && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($search, $operator) {
                $q->where('title', $operator, "%{$search}%")
                    ->orWhere('ticket_number', $operator, "%{$search}%")
                    ->orWhere('description', $operator, "%{$search}%")
                    ->orWhereHas('client', fn (Builder $client) => $client
                        ->where('nama', $operator, "%{$search}%")
                        ->orWhere('kode', $operator, "%{$search}%"))
                    ->orWhereHas('project', fn (Builder $project) => $project
                        ->where('name', $operator, "%{$search}%"))
                    ->orWhereHas('reporter', fn (Builder $reporter) => $reporter
                        ->where('name', $operator, "%{$search}%"))
                    ->orWhereHas('assignedUser', fn (Builder $assigned) => $assigned
                        ->where('name', $operator, "%{$search}%"))
                    ->orWhereHas('delegatedUser', fn (Builder $delegated) => $delegated
                        ->where('name', $operator, "%{$search}%"))
                    ->orWhereHas('assignees', fn (Builder $assignee) => $assignee
                        ->where('name', $operator, "%{$search}%"))
                    ->orWhereHas('approver', fn (Builder $approver) => $approver
                        ->where('name', $operator, "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        $projects = Project::with(['team.users:id,name,avatar_path', 'client:id,kode,nama'])
            ->select('id', 'name', 'status', 'team_id', 'client_id')
            ->orderBy('name')
            ->get();

        $clients = Client::select('id', 'kode', 'nama', 'deskripsi', 'is_active')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        $timelines = ProjectTimeline::select('id', 'project_id', 'title', 'type', 'status')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();

        $allUsers = Cache::remember('users.for-tickets', 600, function () {
            return User::with('role:id,name,display_name')
                ->select('id', 'name', 'role_id', 'avatar_path')
                ->orderBy('name')
                ->get();
        });

        $taskTypes = TaskType::select('id', 'nama')->orderBy('nama')->get();

        return Inertia::render('tickets/page', [
            'tickets' => $tickets,
            'projects' => $projects,
            'clients' => $clients,
            'timelines' => $timelines,
            'allUsers' => $allUsers,
            'taskTypes' => $taskTypes,
            'filters' => [
                'client_id' => $request->client_id ?? 'all',
                'project_id' => $request->project_id ?? 'all',
                'search' => $request->search ?? '',
                'view' => $request->input('view', 'active'),
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $this->normalizeNullableInputs($request);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'timeline_id' => 'nullable|exists:project_timelines,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:bug,feature,task,improvement,documentation',
            'task_type_id' => 'nullable|exists:task_types,id',
            'request_type' => 'nullable|in:berbayar,gratis',
            'priority' => 'required|in:highest,high,medium,low,lowest',
            'status' => 'nullable|in:todo,pending,inprogress,qa-ready,qa-test,review,not-appropriate,done',
            'assigned_to' => 'nullable|exists:users,id',
            'delegated_to' => 'nullable|exists:users,id',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'review_notes' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'story_points' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => $this->attachmentValidationRule(),
        ]);

        $this->validateTicketRelations($validated);

        $files = $this->attachmentFiles($request);
        unset($validated['attachments']);

        $assignees = $validated['assignees'] ?? null;
        unset($validated['assignees']);

        $validated['reporter_id'] = $request->user()->id;
        $validated['status'] = $this->initialStatusFor($validated);

        $ticket = DB::transaction(function () use ($validated, $assignees) {
            $client = Client::lockForUpdate()->findOrFail($validated['client_id']);
            $project = ! empty($validated['project_id'])
                ? Project::lockForUpdate()->findOrFail($validated['project_id'])
                : null;

            $validated['ticket_number'] = $this->nextTicketNumber($client, $project);

            $ticket = Ticket::create($validated);
            $ticket->logStatusChange($ticket->status, null, $validated['reporter_id']);
            $this->syncAssignees($ticket, $assignees);

            return $ticket;
        });

        if ($files !== []) {
            $ticket->update(['attachments' => $this->storeTicketAttachments($ticket, $files)]);
        }

        return redirect()->back()->with('success', 'Ticket created successfully');
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $this->normalizeNullableInputs($request);

        $validated = $request->validate([
            'client_id' => 'sometimes|required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'timeline_id' => 'nullable|exists:project_timelines,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:bug,feature,task,improvement,documentation',
            'task_type_id' => 'nullable|exists:task_types,id',
            'request_type' => 'nullable|in:berbayar,gratis',
            'priority' => 'sometimes|in:highest,high,medium,low,lowest',
            'status' => 'sometimes|in:todo,pending,inprogress,qa-ready,qa-test,review,not-appropriate,done',
            'assigned_to' => 'nullable|exists:users,id',
            'delegated_to' => 'nullable|exists:users,id',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'review_notes' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'story_points' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => $this->attachmentValidationRule(),
        ]);

        $this->validateTicketRelations($validated, $ticket);

        // Only touch the assignee pivot when the edit form explicitly submits it
        // (partial updates like attachment uploads must leave assignees untouched).
        // Empty arrays vanish in multipart bodies, so a flag signals an intentional sync.
        $assignees = $request->boolean('sync_assignees') ? ($validated['assignees'] ?? []) : null;
        unset($validated['assignees']);

        $targetRequestType = array_key_exists('request_type', $validated)
            ? $validated['request_type']
            : $ticket->request_type;

        $currentUser = $request->user();

        if (isset($validated['status']) && $validated['status'] !== $ticket->status) {
            $this->guardStatusMove($ticket, $validated['status'], $targetRequestType, $currentUser);
        }

        $this->applyApprovalState($validated, $ticket, $targetRequestType, $currentUser);

        if (isset($validated['status']) && $validated['status'] !== $ticket->status && $validated['status'] === 'done') {
            $validated['resolved_at'] = now();
            $validated['closed_at'] = now();
        }

        $files = $this->attachmentFiles($request);
        unset($validated['attachments']);

        if ($files !== []) {
            $validated['attachments'] = array_merge(
                $ticket->attachments ?? [],
                $this->storeTicketAttachments($ticket, $files),
            );
        }

        $oldStatus = $ticket->status;
        $ticket->update($validated);
        $this->syncAssignees($ticket, $assignees);

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $ticket->logStatusChange($validated['status'], $oldStatus);

            if ($validated['status'] === 'not-appropriate') {
                $this->logReviewNote($ticket, $validated['review_notes'] ?? null, $currentUser?->id);
            }

            try {
                \App\Events\TicketStatusUpdated::dispatch($ticket, $oldStatus, $validated['status'], $currentUser?->id);
            } catch (\Throwable $e) {
                // Best-effort broadcasting
            }
        }

        return redirect()->back()->with('success', 'Ticket updated successfully');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        $this->deleteTicketAttachments($ticket);
        $ticket->delete();

        return redirect()->back()->with('success', 'Tiket berhasil dihapus.');
    }

    public function move(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => 'required|in:todo,pending,inprogress,qa-ready,qa-test,review,not-appropriate,done',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $this->guardStatusMove($ticket, $validated['status'], null, $request->user());

        $oldStatus = $ticket->status;
        if ($oldStatus !== $validated['status']) {
            if ($validated['status'] === 'done') {
                $validated['resolved_at'] = now();
                $validated['closed_at'] = now();
            }

            $ticket->update($validated);
            $ticket->logStatusChange($validated['status'], $oldStatus);

            if ($validated['status'] === 'not-appropriate') {
                $this->logReviewNote($ticket, $validated['review_notes'] ?? null, $request->user()->id);
            }
        }

        return redirect()->back()->with('success', 'Ticket status updated');
    }

    public function approve(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->hasPermissionTo('tickets.approve'), 403);

        // Tiket berbayar butuh approval; tiket apa pun yang sedang berstatus
        // "Menunggu Approval" (pending) juga harus bisa disetujui agar tidak
        // tersangkut tanpa jalan keluar.
        if (! $ticket->requiresApproval() && $ticket->status !== 'pending') {
            return redirect()->back()->with('success', 'Tiket non-berbayar tidak memerlukan approval.');
        }

        $ticket->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'status' => $ticket->status === 'pending' ? 'todo' : $ticket->status,
        ]);

        return redirect()->back()->with('success', 'Ticket approved');
    }

    public function reject(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->hasPermissionTo('tickets.approve'), 403);

        if (! $ticket->requiresApproval() && $ticket->status !== 'pending') {
            return redirect()->back()->withErrors([
                'approval' => 'Tiket non-berbayar tidak perlu approval atau penolakan.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $ticket->update([
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => null,
            'approved_at' => null,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Ticket rejected');
    }

    public function delegate(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->hasPermissionTo('tickets.approve'), 403);

        $validated = $request->validate([
            'delegated_to' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'delegated_to' => $validated['delegated_to'],
            'delegated_at' => now(),
            'assigned_to' => $validated['delegated_to'],
        ]);

        return redirect()->back()->with('success', 'Ticket delegated');
    }

    public function archive(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        if ($ticket->status !== 'done') {
            throw ValidationException::withMessages([
                'archive' => 'Hanya tiket yang sudah selesai yang bisa diarsipkan.',
            ]);
        }

        $ticket->update(['archived_at' => now()]);

        return redirect()->back()->with('success', 'Tiket diarsipkan');
    }

    public function unarchive(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $ticket->update(['archived_at' => null]);

        return redirect()->back()->with('success', 'Tiket dikembalikan dari arsip');
    }

    /**
     * Sync the penanggung jawab / delegasi tugas for a ticket.
     * A null value means "leave unchanged".
     */
    private function syncAssignees(Ticket $ticket, ?array $userIds): void
    {
        if ($userIds === null) {
            return;
        }

        $ticket->assignees()->sync(
            collect(array_unique($userIds))
                ->mapWithKeys(fn ($id) => [$id => ['role' => 'delegate']])
                ->all()
        );
    }

    /**
     * Record who/when a ticket was flagged "Belum Sesuai" (and the review note)
     * as an internal comment so it shows up in the ticket history timeline.
     */
    private function logReviewNote(Ticket $ticket, ?string $reviewNote, ?int $userId = null): void
    {
        $note = trim((string) $reviewNote);
        $body = $note !== ''
            ? "Ditandai Belum Sesuai (hasil QC belum sesuai):\n{$note}"
            : 'Ditandai Belum Sesuai (hasil QC belum sesuai).';

        $ticket->comments()->create([
            'user_id' => $userId ?? auth()->id(),
            'comment' => $body,
            'type' => 'status_change',
            'is_internal' => true,
        ]);
    }

    private function applyTicketVisibility(Builder $query, User $user): void
    {
        // All authenticated users can see all tickets.
    }

    private function applyTicketView(Builder $query, string $view): void
    {
        $cutoff = now()->subDays(30);

        if ($view === 'archive') {
            $query->where(function (Builder $q) use ($cutoff) {
                $q->whereNotNull('archived_at')
                    ->orWhereNotNull('rejected_at')
                    ->orWhere(function (Builder $done) use ($cutoff) {
                        $done->where('status', 'done')
                            ->where(function (Builder $old) use ($cutoff) {
                                $old->where(function (Builder $withClosed) use ($cutoff) {
                                    $withClosed->whereNotNull('closed_at')
                                        ->where('closed_at', '<', $cutoff);
                                })->orWhere(function (Builder $withoutClosed) use ($cutoff) {
                                    $withoutClosed->whereNull('closed_at')
                                        ->where('updated_at', '<', $cutoff);
                                });
                            });
                    });
            });

            return;
        }

        $query->whereNull('archived_at')
            ->whereNull('rejected_at')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('status', '!=', 'done')
                    ->orWhere(function (Builder $done) use ($cutoff) {
                        $done->where('status', 'done')
                            ->where(function (Builder $recent) use ($cutoff) {
                                $recent->where(function (Builder $withClosed) use ($cutoff) {
                                    $withClosed->whereNotNull('closed_at')
                                        ->where('closed_at', '>=', $cutoff);
                                })->orWhere(function (Builder $withoutClosed) use ($cutoff) {
                                    $withoutClosed->whereNull('closed_at')
                                        ->where('updated_at', '>=', $cutoff);
                                });
                            });
                    });
            });
    }

    private function normalizeNullableInputs(Request $request): void
    {
        $nullableFields = [
            'project_id',
            'timeline_id',
            'task_type_id',
            'assigned_to',
            'delegated_to',
            'due_date',
            'estimated_hours',
            'actual_hours',
            'story_points',
            'request_type',
        ];

        $normalized = [];
        foreach ($nullableFields as $field) {
            if ($request->has($field) && in_array($request->input($field), ['', 'none', 'null'], true)) {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function validateTicketRelations(array $validated, ?Ticket $ticket = null): void
    {
        $clientId = array_key_exists('client_id', $validated) ? $validated['client_id'] : $ticket?->client_id;
        $projectId = array_key_exists('project_id', $validated) ? $validated['project_id'] : $ticket?->project_id;
        $timelineId = array_key_exists('timeline_id', $validated) ? $validated['timeline_id'] : $ticket?->timeline_id;

        if (! $clientId) {
            throw ValidationException::withMessages([
                'client_id' => 'Client wajib dipilih.',
            ]);
        }

        if ($projectId) {
            $project = Project::select('id', 'client_id')->find($projectId);
            if ($project && $project->client_id && (int) $project->client_id !== (int) $clientId) {
                throw ValidationException::withMessages([
                    'project_id' => 'Proyek harus sesuai dengan client yang dipilih.',
                ]);
            }
        }

        if ($timelineId) {
            if (! $projectId) {
                throw ValidationException::withMessages([
                    'timeline_id' => 'Timeline hanya bisa dipilih jika tiket punya proyek.',
                ]);
            }

            $timeline = ProjectTimeline::select('id', 'project_id')->find($timelineId);
            if ($timeline && (int) $timeline->project_id !== (int) $projectId) {
                throw ValidationException::withMessages([
                    'timeline_id' => 'Timeline harus berasal dari proyek yang dipilih.',
                ]);
            }
        }
    }

    private function initialStatusFor(array $validated): string
    {
        return $this->ticketNumbering->initialStatusFor($validated);
    }

    private function applyApprovalState(array &$validated, Ticket $ticket, ?string $targetRequestType, ?User $user = null): void
    {
        // Super admin/admin bypasses the approval gate entirely.
        if ($user?->isAdmin()) {
            return;
        }

        if ($targetRequestType === 'berbayar') {
            if (empty($ticket->approved_at)) {
                $validated['status'] = $validated['status'] ?? 'pending';
            }

            return;
        }

        $validated['approved_by'] = null;
        $validated['approved_at'] = null;
        $validated['rejected_by'] = null;
        $validated['rejected_at'] = null;
        $validated['rejection_reason'] = null;

        if (($validated['status'] ?? $ticket->status) === 'pending') {
            $validated['status'] = 'todo';
        }
    }

    private function guardStatusMove(Ticket $ticket, string $status, ?string $requestType = null, ?User $user = null): void
    {
        if ($status === $ticket->status) {
            return;
        }

        // Tiket yang sudah selesai bersifat final — tidak boleh dipindah ke status
        // lain (berlaku untuk semua user, termasuk admin), biar tidak ada celah.
        if ($ticket->status === 'done') {
            throw ValidationException::withMessages([
                'status' => 'Tiket sudah selesai dan tidak bisa dipindah lagi.',
            ]);
        }

        // Integritas data: tiket tidak boleh keluar dari "To Do" sebelum ada
        // penanggung jawab (Delegasi Tugas). Berlaku untuk semua user.
        if ($ticket->status === 'todo' && $ticket->assignees()->doesntExist()) {
            throw ValidationException::withMessages([
                'status' => 'Isi Delegasi Tugas dulu sebelum memindahkan tiket dari To Do.',
            ]);
        }

        // "Menunggu Approval" (pending) khusus tiket berbayar — tiket non-berbayar
        // tidak butuh approval, jadi tidak boleh masuk kolom ini. Berlaku untuk
        // semua user (termasuk admin) supaya tiket tidak tersangkut tanpa alur
        // approval yang benar, seperti kasus PMC-0005.
        if ($status === 'pending' && (($requestType ?? $ticket->request_type) !== 'berbayar')) {
            throw ValidationException::withMessages([
                'status' => 'Hanya tiket berbayar yang bisa masuk "Menunggu Approval".',
            ]);
        }

        // Super admin/admin bypasses all approval gates.
        if ($user?->isAdmin()) {
            return;
        }

        if (! empty($ticket->rejected_at) && empty($ticket->approved_at)) {
            throw ValidationException::withMessages([
                'status' => 'Tiket ini sudah ditolak dan masuk arsip. Review ulang dulu sebelum dipindah.',
            ]);
        }

        $needsApproval = ($requestType ?? $ticket->request_type) === 'berbayar';
        if ($status !== 'pending' && $needsApproval && empty($ticket->approved_at)) {
            throw ValidationException::withMessages([
                'status' => 'Tiket berbayar harus disetujui terlebih dahulu sebelum bisa dipindah status.',
            ]);
        }
    }

    private function nextTicketNumber(Client $client, ?Project $project): string
    {
        return $this->ticketNumbering->nextTicketNumber($client, $project);
    }

    private function attachmentFiles(Request $request): array
    {
        $files = $request->file('attachments', []);

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    private function attachmentValidationRule(): string
    {
        return TicketAttachmentStorage::validationRule();
    }

    private function storeTicketAttachments(Ticket $ticket, array $files): array
    {
        return TicketAttachmentStorage::store($ticket, $files);
    }

    private function deleteTicketAttachments(Ticket $ticket): void
    {
        foreach ($ticket->attachments ?? [] as $attachment) {
            if (empty($attachment['path'])) {
                continue;
            }

            try {
                Storage::disk($attachment['disk'] ?? 'local')->delete($attachment['path']);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete ticket attachment', [
                    'ticket_id' => $ticket->id,
                    'path' => $attachment['path'],
                    'msg' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleteAttachment(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'filename' => 'nullable|string',
            'index' => 'nullable|integer',
        ]);

        $attachments = $ticket->attachments ?? [];
        $targetIndex = null;

        if (array_key_exists('index', $validated) && $validated['index'] !== null && isset($attachments[$validated['index']])) {
            $targetIndex = (int) $validated['index'];
        } elseif (!empty($validated['filename'])) {
            $filename = basename($validated['filename']);
            foreach ($attachments as $i => $att) {
                if (($att['name'] ?? '') === $filename || basename($att['path'] ?? '') === $filename) {
                    $targetIndex = $i;
                    break;
                }
            }
        }

        if ($targetIndex === null || !isset($attachments[$targetIndex])) {
            return redirect()->back()->withErrors(['attachment' => 'Lampiran tidak ditemukan.']);
        }

        $target = $attachments[$targetIndex];

        if (!empty($target['path'])) {
            try {
                Storage::disk($target['disk'] ?? 'public')->delete($target['path']);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete single ticket attachment file', [
                    'ticket_id' => $ticket->id,
                    'path' => $target['path'],
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        array_splice($attachments, $targetIndex, 1);
        $ticket->update([
            'attachments' => !empty($attachments) ? array_values($attachments) : null,
        ]);

        return redirect()->back()->with('success', 'Lampiran berhasil dihapus.');
    }
}
