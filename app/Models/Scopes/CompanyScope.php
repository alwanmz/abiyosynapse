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
 * If no company is resolved (console commands, queue jobs, tests that
 * don't go through the HTTP middleware stack) this scope deliberately
 * applies NO filter rather than throwing — EnsureCompanyContext is the
 * real guard for HTTP requests. Code that touches BelongsToCompany
 * models outside of an HTTP request must call
 * app(CurrentCompany::class)->set($company) explicitly first, or the
 * query will run unscoped across all companies.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId !== null) {
            $builder->where($model->getTable() . '.company_id', $companyId);
        }
    }
}
