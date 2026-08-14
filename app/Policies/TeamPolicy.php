<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine if the user can create teams.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('teams.create') || $user->hasPermissionTo('create-team');
    }

    /**
     * Determine if the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.edit') || $user->hasPermissionTo('teams.update') || $user->hasPermissionTo('update-team');
    }

    /**
     * Determine if the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.manage-members') || $user->hasPermissionTo('manage-team-members');
    }

    /**
     * Determine if the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.delete') || $user->hasPermissionTo('delete-team');
    }
}
