<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Services\CurrentCompany;
use App\Support\AccountRole;
use RuntimeException;

class AccountRoleResolver
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function id(AccountRole $role, ?int $companyId = null): int
    {
        $companyId ??= $this->currentCompany->id();

        $accountId = AccountRoleMapping::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('role', $role->value)
            ->value('account_id');

        if ($accountId === null) {
            throw new RuntimeException(
                "Akun untuk peran \"{$role->label()}\" belum dipetakan. Atur di Master Data > Akun > Mapping Akun Inti."
            );
        }

        return (int) $accountId;
    }

    public function account(AccountRole $role, ?int $companyId = null): Account
    {
        return Account::withoutGlobalScopes()->findOrFail($this->id($role, $companyId));
    }
}
