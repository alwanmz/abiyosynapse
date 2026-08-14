<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ClientAiUsage;
use App\Models\Ticket;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class AiController extends Controller
{
    public function __construct(private readonly AiService $ai)
    {
    }

    /**
     * Answer a client's question about their own tickets, limited to a daily
     * quota. Context is strictly scoped to the logged-in client — no internal
     * data ever reaches the model.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|min:2|max:2000',
            'history' => 'nullable|array|max:10',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:2000',
        ]);

        if (! $this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'AI belum aktif di server ini. Hubungi tim kami.',
            ], 503);
        }

        $clientId = (int) Auth::guard('client')->id();
        $limit = $this->dailyLimit();
        $used = ClientAiUsage::todayCountFor($clientId);

        if ($used >= $limit) {
            return response()->json([
                'ok' => false,
                'message' => "Kuota tanya AI hari ini sudah habis ({$limit}/{$limit}). Coba lagi besok ya.",
                'remaining' => 0,
            ]);
        }

        $history = collect($validated['history'] ?? [])
            ->map(fn ($item) => [
                'role' => $item['role'],
                'content' => (string) $item['content'],
            ])
            ->all();

        try {
            $answer = $this->ai->answerClientTicketQuestion(
                $validated['question'],
                $this->buildClientContext($clientId),
                $history,
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Maaf, ada gangguan sementara pada AI. Coba ulangi sebentar ya.',
            ], 502);
        }

        // Only a successful answer consumes quota.
        $count = ClientAiUsage::incrementFor($clientId);

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'remaining' => max(0, $limit - $count),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function dailyLimit(): int
    {
        return max(1, (int) config('services.deepseek.client_daily_limit', 5));
    }

    /**
     * Compact, client-safe snapshot: only this client's tickets and their
     * non-internal comments. Never includes assignees/PIC, hours, review notes,
     * or internal comments.
     */
    private function buildClientContext(int $clientId): array
    {
        $base = fn () => Ticket::query()->where('client_id', $clientId);

        $byStatus = (clone $base())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byPriority = (clone $base())
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $tickets = (clone $base())
            ->with([
                'project:id,name',
                'taskType:id,nama',
                'comments' => fn ($q) => $q->where('is_internal', false)
                    ->latest('created_at')
                    ->limit(3)
                    ->with('client:id'),
            ])
            ->select([
                'id', 'ticket_number', 'title', 'status', 'priority', 'type',
                'project_id', 'task_type_id', 'created_at', 'resolved_at',
            ])
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'type' => $ticket->type,
                'project' => $ticket->project?->name,
                'task_type' => $ticket->taskType?->nama,
                'created_at' => $ticket->created_at?->toDateString(),
                'resolved_at' => $ticket->resolved_at?->toDateString(),
                'recent_comments' => $ticket->comments
                    ->sortBy('created_at')
                    ->map(fn ($comment) => [
                        // Author is only ever "Tim" (staff) or "Anda" (this client).
                        'from' => $comment->client_id ? 'Anda' : 'Tim',
                        'text' => Str::limit((string) $comment->comment, 200),
                        'at' => $comment->created_at?->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'note' => 'Snapshot hanya berisi tiket milik klien ini. Komentar internal tidak disertakan.',
            'overview' => [
                'tickets_total' => (clone $base())->count(),
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
            ],
            'tickets' => $tickets,
        ];
    }
}
