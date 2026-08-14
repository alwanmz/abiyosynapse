<?php

namespace App\Observers;

use App\Models\DailyLog;
use App\Models\TicketComment;

class TicketCommentObserver
{
    /**
     * Handle the TicketComment "created" event.
     */
    public function created(TicketComment $ticketComment): void
    {
        $ticket = $ticketComment->ticket;
        if (!$ticket) return;

        // Content: Title + Comment
        $description = "Update Ticket: {$ticket->title}\nKomentar: {$ticketComment->comment}";

        // 1. Log for the person who added the comment (The Actor)
        $this->ensureLog($ticketComment->user_id, $ticket->id, $description);

        // 2. Log for the Reporter (if not the actor)
        if ($ticket->reporter_id !== $ticketComment->user_id) {
            $this->ensureLog($ticket->reporter_id, $ticket->id, $description);
        }

        // 3. Log for the Assignee (if not the actor and exists)
        if ($ticket->assigned_to && $ticket->assigned_to !== $ticketComment->user_id && $ticket->assigned_to !== $ticket->reporter_id) {
            $this->ensureLog($ticket->assigned_to, $ticket->id, $description);
        }
    }

    /**
     * Ensure a log exists for the user on this ticket today with this content.
     */
    private function ensureLog($userId, $ticketId, $description)
    {
        if (!$userId) return;

        DailyLog::updateOrCreate(
            [
                'user_id' => $userId,
                'ticket_id' => $ticketId,
                'log_date' => now()->toDateString(),
                'is_automated' => true,
            ],
            [
                'description' => $description,
            ]
        );
    }
}
