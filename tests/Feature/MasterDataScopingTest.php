<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\CurrentCompany;

test('master data models are scoped to the current company', function (string $model) {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    app(CurrentCompany::class)->set($companyA);
    $recordA = $model::factory()->for($companyA)->create();

    app(CurrentCompany::class)->set($companyB);
    $recordB = $model::factory()->for($companyB)->create();

    $visible = $model::all()->pluck('id');

    expect($visible)->toContain($recordB->id);
    expect($visible)->not->toContain($recordA->id);
})->with([
    UnitOfMeasure::class,
    Warehouse::class,
    Supplier::class,
    Customer::class,
    TaxCode::class,
    ProductCategory::class,
]);

test('creating a record without an explicit company_id auto-fills it from CurrentCompany', function () {
    $company = Company::factory()->create();
    app(CurrentCompany::class)->set($company);

    $warehouse = Warehouse::factory()->make(['company_id' => null]);
    $warehouse->company_id = null;
    $warehouse->save();

    expect($warehouse->fresh()->company_id)->toBe($company->id);
});

test('product belongs to a base unit of measure and optional warehouse', function () {
    $company = Company::factory()->create();
    app(CurrentCompany::class)->set($company);

    $uom = UnitOfMeasure::factory()->for($company)->create(['code' => 'PCS', 'name' => 'Pieces']);
    $warehouse = Warehouse::factory()->for($company)->create();

    $product = Product::factory()->for($company)->manufactured()->create([
        'code' => 'CHR-X1',
        'name' => 'Kursi Kantor X1',
        'base_uom_id' => $uom->id,
        'default_warehouse_id' => $warehouse->id,
    ]);

    expect($product->baseUnitOfMeasure->code)->toBe('PCS');
    expect($product->defaultWarehouse->id)->toBe($warehouse->id);
    expect($product->make_or_buy)->toBe('make');
    expect($product->isActive())->toBeTrue();
});
