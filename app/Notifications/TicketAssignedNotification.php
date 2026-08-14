<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $ticketId,
        public string $ticketNumber,
        public string $ticketTitle,
        public string $assignedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket_assigned',
            'title' => 'New ticket assigned',
            'message' => "You have been assigned to ticket #{$this->ticketNumber}: {$this->ticketTitle}",
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'ticket_title' => $this->ticketTitle,
            'assigned_by' => $this->assignedBy,
        ];
    }
}
