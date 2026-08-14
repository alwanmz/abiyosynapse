<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExternalTicketSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $ticketId,
        public string $ticketNumber,
        public string $ticketTitle,
        public string $clientName,
        public string $reporterName
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'external_ticket_submitted',
            'title' => 'Tiket baru dari Telegram',
            'message' => "{$this->reporterName} ({$this->clientName}) mengirim tiket #{$this->ticketNumber}: {$this->ticketTitle}",
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'ticket_title' => $this->ticketTitle,
            'client_name' => $this->clientName,
            'reporter_name' => $this->reporterName,
        ];
    }
}
