<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamRemovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $teamId,
        public string $teamName,
        public string $removedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'team_removed',
            'title' => 'Removed from team',
            'message' => "You have been removed from the {$this->teamName} team",
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'removed_by' => $this->removedBy,
        ];
    }
}
