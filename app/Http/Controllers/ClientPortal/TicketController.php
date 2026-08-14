<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ClientRequestQuota;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    /**
     * List the tickets belonging to the currently authenticated client only.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('client-portal/tickets/index', [
            'tickets' => $this->clientTickets(),
            'quota' => ClientRequestQuota::payloadFor(Auth::guard('client')->user()),
        ]);
    }

    /**
     * Read-only Kanban board of the client's own tickets.
     */
    public function board(Request $request): Response
    {
        return Inertia::render('client-portal/tickets/board', [
            'tickets' => $this->clientTickets(),
            'quota' => ClientRequestQuota::payloadFor(Auth::guard('client')->user()),
        ]);
    }

    /**
     * Show a single ticket as a full page (deep-link / breadcrumb target).
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorizeClientOwnership($ticket);

        return Inertia::render('client-portal/tickets/show', [
            'ticket' => $this->buildDetailPayload($ticket),
        ]);
    }

    /**
     * JSON detail used by the in-page detail dialog (Kanban / table).
     */
    public function detail(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeClientOwnership($ticket);

        return response()->json([
            'ticket' => $this->buildDetailPayload($ticket),
        ]);
    }

    /**
     * Stream a ticket attachment, but only after verifying the ticket belongs to
     * the authenticated client. This replaces reliance on the public disk URL and
     * the staff-guarded /attachments route (prevents cross-client access / IDOR).
     */
    public function attachment(Request $request, Ticket $ticket): StreamedResponse
    {
        $this->authorizeClientOwnership($ticket);

        $filename = basename((string) $request->query('filename'));
        if ($filename === '') {
            abort(404, 'File not found');
        }

        $attachment = collect($ticket->attachments ?? [])
            ->merge($this->commentAttachments($ticket))
            ->first(fn ($item) => isset($item['path']) && basename($item['path']) === $filename);

        if (! $attachment || empty($attachment['path'])) {
            abort(404, 'File not found');
        }

        $disk = $this->resolveDisk($attachment);
        if ($disk === null) {
            abort(404, 'File not found');
        }

        $name = $attachment['name'] ?? $filename;
        $mimeType = $attachment['mime_type'] ?? Storage::disk($disk)->mimeType($attachment['path']);

        return Storage::disk($disk)->response($attachment['path'], $name, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addcslashes($name, '"\\').'"',
        ]);
    }

    /**
     * Lightweight ticket list scoped to the authenticated client.
     */
    private function clientTickets()
    {
        return Ticket::query()
            ->where('client_id', Auth::guard('client')->id())
            ->select([
                'id', 'ticket_number', 'title', 'status', 'priority', 'type',
                'project_id', 'task_type_id', 'attachments', 'created_at', 'resolved_at',
            ])
            ->with(['project:id,name', 'taskType:id,nama'])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('is_internal', false),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'type' => $ticket->type,
                'project' => $ticket->project,
                'task_type' => $ticket->taskType,
                'attachments_count' => count($ticket->attachments ?? []),
                'comments_count' => (int) $ticket->comments_count,
                'created_at' => $ticket->created_at,
                'resolved_at' => $ticket->resolved_at,
            ]);
    }

    /**
     * Build the full detail payload shared by show() (Inertia) and detail() (JSON).
     * Only non-internal comments are ever exposed; attachment URLs point at the
     * ownership-checked portal streaming route.
     */
    private function buildDetailPayload(Ticket $ticket): array
    {
        $ticket->load([
            'project:id,name',
            'taskType:id,nama',
            'statusLogs' => fn ($q) => $q->orderBy('changed_at')->orderBy('id'),
            'statusLogs.user:id,name,avatar_path',
            'comments' => fn ($q) => $q->where('is_internal', false)->with('reactions')->orderBy('created_at'),
            'comments.user:id,name,avatar_path',
            'comments.client:id,nama',
        ]);

        $clientId = Auth::guard('client')->id();

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'type' => $ticket->type,
            'project' => $ticket->project,
            'task_type' => $ticket->taskType,
            'created_at' => $ticket->created_at,
            'resolved_at' => $ticket->resolved_at,
            'attachments' => $this->mapAttachments($ticket, $ticket->attachments ?? []),
            'status_logs' => $ticket->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'changed_at' => $log->changed_at,
                'user_name' => $log->user?->name,
            ]),
            'comments' => $ticket->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'attachments' => $this->mapAttachments($ticket, $comment->attachments ?? []),
                'reactions' => $this->mapReactions($comment, null, $clientId),
                'created_at' => $comment->created_at,
                'is_from_client' => $comment->isFromClient(),
                'author_name' => $comment->authorName(),
                'author_avatar' => $comment->isFromClient() ? null : $comment->user?->avatar_path,
            ]),
            'users' => \App\Models\User::query()->select(['id', 'name', 'avatar_path'])->get(),
        ];
    }

    private function mapReactions($comment, ?int $userId, ?int $clientId): array
    {
        return $comment->reactions
            ->groupBy('emoji')
            ->map(function ($items, $emoji) use ($userId, $clientId) {
                return [
                    'emoji' => $emoji,
                    'count' => $items->count(),
                    'reacted_by_me' => $items->contains(function ($r) use ($userId, $clientId) {
                        return ($userId && (int) $r->user_id === (int) $userId) ||
                               ($clientId && (int) $r->client_id === (int) $clientId);
                    }),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Rewrite each attachment's url to the ownership-checked portal route so the
     * frontend never receives a raw public/storage URL.
     */
    private function mapAttachments(Ticket $ticket, array $attachments): array
    {
        return collect($attachments)
            ->filter(fn ($a) => ! empty($a['path']))
            ->map(fn ($a) => [
                'name' => $a['name'] ?? basename($a['path']),
                'path' => basename($a['path']),
                'size' => $a['size'] ?? 0,
                'mime_type' => $a['mime_type'] ?? 'application/octet-stream',
                'url' => route('portal.tickets.attachment', $ticket).'?filename='.urlencode(basename($a['path'])),
            ])
            ->values()
            ->all();
    }

    /**
     * Flatten every attachment referenced by this ticket's non-internal comments,
     * so the streaming route can also serve comment attachments by filename.
     */
    private function commentAttachments(Ticket $ticket): array
    {
        return $ticket->comments()
            ->where('is_internal', false)
            ->get(['attachments'])
            ->flatMap(fn ($comment) => $comment->attachments ?? [])
            ->all();
    }

    private function resolveDisk(array $attachment): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $attachment['disk'] ?? null, 'public', 'local',
        ])));

        foreach ($candidates as $disk) {
            if (config("filesystems.disks.{$disk}") && Storage::disk($disk)->exists($attachment['path'])) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Abort with 404 unless the ticket belongs to the authenticated client.
     * Never rely on route-model binding alone — this prevents IDOR.
     */
    private function authorizeClientOwnership(Ticket $ticket): void
    {
        abort_unless((int) $ticket->client_id === (int) Auth::guard('client')->id(), 404);
    }

    public function deleteAttachment(Request $request, Ticket $ticket)
    {
        $this->authorizeClientOwnership($ticket);

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
                $disk = $this->resolveDisk($target) ?? 'public';
                Storage::disk($disk)->delete($target['path']);
            } catch (\Throwable $e) {
                // Best effort delete
            }
        }

        array_splice($attachments, $targetIndex, 1);
        $ticket->update([
            'attachments' => !empty($attachments) ? array_values($attachments) : null,
        ]);

        return redirect()->back()->with('success', 'Lampiran berhasil dihapus.');
    }
}
