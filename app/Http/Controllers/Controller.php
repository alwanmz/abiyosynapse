<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

abstract class Controller
{
    use AuthorizesRequests;

    protected function currentTenantId(): int
    {
        $companyId = app(CurrentCompany::class)->id();

        abort_if($companyId === null, 500, 'A company context is required for this operation.');

        return $companyId;
    }

    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where(fn ($query) => $query->where('company_id', $this->currentTenantId()));
    }

    protected function tenantUnique(string $table, string $column, int|string|null $ignoreId = null): Unique
    {
        $rule = Rule::unique($table, $column)
            ->where(fn ($query) => $query->where('company_id', $this->currentTenantId()));

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }

    protected function tenantChildExists(
        string $table,
        string $column,
        string $parentTable,
        string $foreignKey,
    ): Exists {
        return Rule::exists($table, $column)->where(function ($query) use ($table, $parentTable, $foreignKey): void {
            $query->whereExists(function ($parentQuery) use ($table, $parentTable, $foreignKey): void {
                $parentQuery->selectRaw('1')
                    ->from($parentTable)
                    ->whereColumn("{$parentTable}.id", "{$table}.{$foreignKey}")
                    ->where("{$parentTable}.company_id", $this->currentTenantId());
            });
        });
    }
}
