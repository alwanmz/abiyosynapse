<?php

namespace App\Policies;

use App\Models\ProjectTimeline;
use App\Models\User;

class ProjectTimelinePolicy
{
    /**
     * Determine if the user can create timelines.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('timelines.create') || $user->hasPermissionTo('create-timeline');
    }

    /**
     * Determine if the user can update the timeline.
     */
    public function update(User $user, ProjectTimeline $timeline): bool
    {
        return $user->hasPermissionTo('timelines.edit') || $user->hasPermissionTo('timelines.update') || $user->hasPermissionTo('update-timeline');
    }

    /**
     * Determine if the user can delete the timeline.
     */
    public function delete(User $user, ProjectTimeline $timeline): bool
    {
        return $user->hasPermissionTo('timelines.delete') || $user->hasPermissionTo('delete-timeline');
    }
}
