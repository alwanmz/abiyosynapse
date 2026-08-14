<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RoleAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $oldRole,
        public string $newRole,
        public string $updatedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'role_assigned',
            'title' => 'Your role has been updated',
            'message' => "You have been assigned the role of {$this->newRole}",
            'old_role' => $this->oldRole,
            'new_role' => $this->newRole,
            'updated_by' => $this->updatedBy,
        ];
    }
}
