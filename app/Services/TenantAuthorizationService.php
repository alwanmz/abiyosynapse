<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;

class TenantAuthorizationService
{
    public function can(User $user, Company $company, string ...$permissions): bool
    {
        $membership = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->first();

        if (! $membership?->role) {
            return false;
        }

        if (in_array($membership->role->name, ['super_admin', 'admin'], true)) {
            return true;
        }

        return collect($permissions)->contains(
            fn (string $permission): bool => $membership->role->hasPermissionTo($permission),
        );
    }

    public function ensure(User $user, Company $company, string ...$permissions): void
    {
        abort_unless($this->can($user, $company, ...$permissions), 403);
    }
}
