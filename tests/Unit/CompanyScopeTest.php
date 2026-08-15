<?php

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\ScopedTestModel;

beforeEach(function () {
    Schema::create('scoped_test_models', function ($table) {
        $table->id();
        $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('scoped_test_models');
});

test('global scope filters queries to the current company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    app(CurrentCompany::class)->set($companyA);
    ScopedTestModel::create(['company_id' => $companyA->id, 'name' => 'A row']);

    app(CurrentCompany::class)->set($companyB);
    ScopedTestModel::create(['company_id' => $companyB->id, 'name' => 'B row']);

    app(CurrentCompany::class)->set($companyA);
    expect(ScopedTestModel::count())->toBe(1);
    expect(ScopedTestModel::first()->name)->toBe('A row');

    app(CurrentCompany::class)->set($companyB);
    expect(ScopedTestModel::count())->toBe(1);
    expect(ScopedTestModel::first()->name)->toBe('B row');
});

test('creating auto-fills company_id from the current company', function () {
    $company = Company::factory()->create();
    app(CurrentCompany::class)->set($company);

    $model = ScopedTestModel::create(['name' => 'auto-filled']);

    expect($model->company_id)->toBe($company->id);
});

test('withoutGlobalScope bypasses the company filter', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    app(CurrentCompany::class)->set($companyA);
    ScopedTestModel::create(['company_id' => $companyA->id, 'name' => 'A row']);

    app(CurrentCompany::class)->set($companyB);
    ScopedTestModel::create(['company_id' => $companyB->id, 'name' => 'B row']);

    expect(ScopedTestModel::withoutGlobalScope(CompanyScope::class)->count())->toBe(2);
});

test('scope applies no filter when no company is resolved', function () {
    $company = Company::factory()->create();
    app(CurrentCompany::class)->set($company);
    ScopedTestModel::create(['company_id' => $company->id, 'name' => 'orphan row']);

    app(CurrentCompany::class)->set(null);

    expect(ScopedTestModel::count())->toBe(1);
});
