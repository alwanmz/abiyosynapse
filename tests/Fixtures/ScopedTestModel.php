<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Exists only to exercise BelongsToCompany/CompanyScope mechanics in
 * CompanyScopeTest. No real model uses this trait yet in Fase 0 §2 — the
 * first business model in Fase 1 should add a second, model-specific
 * regression test alongside this one.
 */
class ScopedTestModel extends Model
{
    use BelongsToCompany;

    protected $table = 'scoped_test_models';

    protected $fillable = ['company_id', 'name'];
}
