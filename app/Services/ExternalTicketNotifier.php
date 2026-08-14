<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ExternalTicketSubmittedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the staff who triage externally-submitted tickets (Telegram bot and
 * client portal): admins plus anyone holding the tickets.approve permission.
 */
class ExternalTicketNotifier
{
    public function notifyStaff(Ticket $ticket, string $clientName, string $reporterName): void
    {
        $staff = User::where(function ($query) {
            $query->whereHas('role', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
                ->orWhereHas('role.permissions', fn ($q) => $q->where('name', 'tickets.approve'));
        })->where('is_system', false)->get();

        if ($staff->isEmpty()) {
            return;
        }

        Notification::send(
            $staff,
            new ExternalTicketSubmittedNotification($ticket->id, $ticket->ticket_number, $ticket->title, $clientName, $reporterName)
        );
    }
}
