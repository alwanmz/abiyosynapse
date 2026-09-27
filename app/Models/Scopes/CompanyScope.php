<?php

namespace App\Models\Scopes;

use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters every query on a BelongsToCompany model to the currently
 * resolved company (set by EnsureCompanyContext on each web request).
 *
 * If no company is resolved, this scope returns no rows. This fail-closed
 * behavior protects console commands and queue jobs from accidentally
 * reading every tenant. Cross-company maintenance code must set an
 * explicit context with app(CurrentCompany::class)->set($company), or
 * deliberately opt out with withoutGlobalScope(CompanyScope::class).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}
