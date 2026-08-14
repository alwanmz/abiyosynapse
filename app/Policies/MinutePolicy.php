<?php

namespace App\Policies;

use App\Models\Minute;
use App\Models\User;

class MinutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('minutes.view');
    }

    public function view(User $user, Minute $minute): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('minutes.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('minutes.create');
    }

    public function update(User $user, Minute $minute): bool
    {
        return $user->isAdmin()
            || ($minute->created_by === $user->id && $user->hasPermissionTo('minutes.edit') || $user->hasPermissionTo('minutes.update'));
    }

    public function delete(User $user, Minute $minute): bool
    {
        return $user->isAdmin()
            || ($minute->created_by === $user->id && $user->hasPermissionTo('minutes.delete'));
    }
}
