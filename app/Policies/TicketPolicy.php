<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Determine if the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tickets.create') || $user->hasPermissionTo('create-ticket');
    }

    /**
     * Determine if the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermissionTo('tickets.update-any')
            || $user->hasPermissionTo('tickets.edit-any')
            || $user->hasPermissionTo('update-any-ticket')
            || $user->hasPermissionTo('tickets.update')
            || $user->hasPermissionTo('tickets.edit')
            || $user->hasPermissionTo('update-ticket');
    }

    /**
     * Determine if the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.delete') || $user->hasPermissionTo('delete-ticket');
    }
}
