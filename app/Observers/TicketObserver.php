<?php

namespace App\Observers;

use App\Models\DailyLog;
use App\Models\Ticket;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TicketObserver
{
    /**
     * Human-readable Indonesian labels for each ticket status, mirroring the
     * board columns on the frontend.
     */
    private const STATUS_LABELS = [
        'todo' => 'To Do',
        'pending' => 'Menunggu Approval',
        'inprogress' => 'Sedang Dikerjakan',
        'qa-ready' => 'Siap QA',
        'qa-test' => 'QA Test',
        'review' => 'Review',
        'not-appropriate' => 'Belum Sesuai',
        'done' => 'Selesai',
    ];

    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        // Log for Reporter
        DailyLog::updateOrCreate(
            [
                'user_id' => $ticket->reporter_id,
                'ticket_id' => $ticket->id,
                'log_date' => now()->toDateString(),
                'is_automated' => true,
            ],
            [
                'description' => "Mengajukan Ticket: {$ticket->title}",
            ]
        );

        // Log for Assignee
        if ($ticket->assigned_to) {
            DailyLog::updateOrCreate(
                [
                    'user_id' => $ticket->assigned_to,
                    'ticket_id' => $ticket->id,
                    'log_date' => now()->toDateString(),
                    'is_automated' => true,
                ],
                [
                    'description' => "Mengerjakan Ticket: {$ticket->title}",
                ]
            );
        }
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        // Only status moves are worth logging here (edits to other fields would
        // spam the activity feed). Comments are handled by TicketCommentObserver.
        if (! $ticket->wasChanged('status')) {
            return;
        }

        $from = self::STATUS_LABELS[$ticket->getOriginal('status')] ?? $ticket->getOriginal('status');
        $to = self::STATUS_LABELS[$ticket->status] ?? $ticket->status;

        // The actor is whoever triggered the move in the current request.
        // Fall back to the assignee/reporter when there's no auth context
        // (e.g. queued jobs) so the move is still attributed to someone.
        $actorId = Auth::id() ?? $ticket->assigned_to ?? $ticket->reporter_id;

        if ($actorId) {
            DailyLog::updateOrCreate(
                [
                    'user_id' => $actorId,
                    'ticket_id' => $ticket->id,
                    'log_date' => now()->toDateString(),
                    'is_automated' => true,
                ],
                [
                    'description' => "Memindahkan Ticket: {$ticket->title}\nStatus: {$from} → {$to}",
                ]
            );
        }

        $this->notifyTelegramReporter($ticket, $from, $to);
    }

    /**
     * Handle the Ticket "deleted" event.
     */
    public function deleted(Ticket $ticket): void
    {
        $this->notifyTelegramReporterDeleted($ticket);
    }

    /**
     * If this ticket originated from the Telegram bot, let the client know
     * their ticket's status changed — read-only, one-way (they still can't
     * act on it from the bot, just stay informed instead of having to
     * remember to ask /status).
     */
    private function notifyTelegramReporter(Ticket $ticket, string $from, string $to): void
    {
        if ($ticket->source !== 'telegram' || ! $ticket->telegram_chat_id) {
            return;
        }

        try {
            // Title is free-text from the reporter — escape before mixing
            // with HTML tags, otherwise a stray "<" or "&" breaks Telegram's
            // HTML parsing and the whole message silently fails to send.
            $title = e($ticket->title);

            app(TelegramService::class)->sendMessage(
                $ticket->telegram_chat_id,
                "🔔 Tiket <b>{$ticket->ticket_number}</b> diperbarui.\n<i>{$title}</i>\n\nStatus: {$from} → <b>{$to}</b>"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal kirim notifikasi status Telegram', ['ticket_id' => $ticket->id, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * If this ticket originated from the Telegram bot, let the client know
     * that their ticket has been deleted.
     */
    private function notifyTelegramReporterDeleted(Ticket $ticket): void
    {
        if ($ticket->source !== 'telegram' || ! $ticket->telegram_chat_id) {
            return;
        }

        try {
            $title = e($ticket->title);

            app(TelegramService::class)->sendMessage(
                $ticket->telegram_chat_id,
                "🗑️ Tiket <b>{$ticket->ticket_number}</b> dihapus.\n<i>{$title}</i>\n\nStatus: <b>Dihapus</b>"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal kirim notifikasi hapus tiket Telegram', ['ticket_id' => $ticket->id, 'msg' => $e->getMessage()]);
        }
    }
}

