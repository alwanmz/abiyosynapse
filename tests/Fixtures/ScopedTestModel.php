<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Exists only to exercise BelongsToCompany/CompanyScope mechanics in
 * CompanyScopeTest. Real business models use this same trait; the fixture
 * keeps the scope behavior test independent from any one module schema.
 */
class ScopedTestModel extends Model
{
    use BelongsToCompany;

    protected $table = 'scoped_test_models';

    protected $fillable = ['company_id', 'name'];
}
