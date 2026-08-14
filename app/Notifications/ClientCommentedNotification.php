<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $ticketId,
        public string $ticketNumber,
        public string $ticketTitle,
        public string $clientName
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'client_commented',
            'title' => 'Komentar baru dari klien',
            'message' => "{$this->clientName} mengomentari tiket #{$this->ticketNumber}: {$this->ticketTitle}",
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'ticket_title' => $this->ticketTitle,
            'client_name' => $this->clientName,
        ];
    }
}
