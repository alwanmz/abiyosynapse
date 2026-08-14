<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine if the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('projects.create') || $user->hasPermissionTo('create-project');
    }

    /**
     * Determine if the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermissionTo('projects.update-any') && ! $user->hasPermissionTo('projects.edit') && ! $user->hasPermissionTo('update-any-project')) {
            return false;
        }

        // Must be the project manager, or the creator, or a member of the project's team.
        return $user->id === $project->project_manager_id
            || $user->id === $project->created_by
            || $user->isInTeam($project->team);
    }

    /**
     * Determine if the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermissionTo('projects.delete') && ! $user->hasPermissionTo('delete-project')) {
            return false;
        }

        // Must be the project manager or creator linked to this project's team.
        return $user->id === $project->project_manager_id
            || $user->id === $project->created_by
            || $user->isInTeam($project->team);
    }
}
