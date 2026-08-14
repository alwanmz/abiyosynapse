<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $teamId,
        public string $teamName,
        public string $addedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'team_assigned',
            'title' => 'Added to team',
            'message' => "You have been added to the {$this->teamName} team",
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'added_by' => $this->addedBy,
        ];
    }
}
