<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For business models scoped to a single company (Fase 1+). Adds the
 * global CompanyScope and auto-fills `company_id` on create.
 *
 * Only fills `company_id` for Eloquent `create()`/`save()` — direct
 * `DB::table()->insert()` calls bypass this and must set company_id
 * themselves.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (! $model->getAttribute('company_id')) {
                $companyId = app(CurrentCompany::class)->id();

                if ($companyId !== null) {
                    $model->setAttribute('company_id', $companyId);
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
