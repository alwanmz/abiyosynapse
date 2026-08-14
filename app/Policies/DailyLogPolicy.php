<?php

namespace App\Policies;

use App\Models\DailyLog;
use App\Models\User;

/**
 * Authorization rules for the Daily Logs module.
 *
 * Owners can always touch their own logs. Anyone with the
 * `daily-logs.manage` permission (typically Project Manager + Super Admin)
 * may also view/delete other people's logs for audit purposes.
 */
class DailyLogPolicy
{
    /**
     * Anyone with `daily-logs.create` may create.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermissionTo('daily-logs.create')
            || $user->hasPermissionTo('create-daily-log');
    }

    /**
     * Owner OR a daily-logs.manage holder may view a row.
     */
    public function view(User $user, DailyLog $log): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($log->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('daily-logs.manage') || $user->hasPermissionTo('manage-daily-logs');
    }

    /**
     * Same rule for delete: owner via `daily-logs.delete`,
     * or any user with `daily-logs.manage`.
     */
    public function delete(User $user, DailyLog $log): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (
            $log->user_id === $user->id
            && ($user->hasPermissionTo('daily-logs.delete') || $user->hasPermissionTo('delete-daily-log'))
        ) {
            return true;
        }

        return $user->hasPermissionTo('daily-logs.manage') || $user->hasPermissionTo('manage-daily-logs');
    }
}
