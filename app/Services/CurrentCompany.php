<?php

namespace App\Services;

use App\Models\Company;

class CurrentCompany
{
    protected ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function isResolved(): bool
    {
        return $this->company !== null;
    }
}
