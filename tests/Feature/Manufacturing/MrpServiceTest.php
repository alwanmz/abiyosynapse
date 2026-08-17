<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\MrpService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\InventoryDemoSeeder;
use Database\Seeders\ManufacturingDemoSeeder;
use Database\Seeders\MasterDataDemoSeeder;

/**
 * Reproduces the exact MRP example table from the boss's blueprint (§7):
 * a demand of 100 units of CHR-X1 against the blueprint's opening stock
 * numbers should report Busa short by 20 PCS and Cat short by 20 L, with
 * everything else covered by stock on hand.
 */
beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);

    (new ChartOfAccountsSeeder())->run();
    (new MasterDataDemoSeeder())->run();
    (new InventoryDemoSeeder())->run();
    (new ManufacturingDemoSeeder())->run();
});

test('MRP for 100 units of CHR-X1 matches the blueprint example exactly', function () {
    $product = Product::where('code', 'CHR-X1')->first();
    $warehouse = Warehouse::where('code', 'WH-RM')->first();

    $results = app(MrpService::class)->run($product, 100, $warehouse);

    $byCode = collect($results)->keyBy('code');

    expect((float) $byCode['MAT-HOLLOW']['requirement'])->toBe(500.0);
    expect((float) $byCode['MAT-HOLLOW']['shortage'])->toBe(0.0);
    expect($byCode['MAT-HOLLOW']['action'])->toBe('Use Stock');

    expect((float) $byCode['MAT-BUSA']['requirement'])->toBe(100.0);
    expect((float) $byCode['MAT-BUSA']['stock'])->toBe(80.0);
    expect((float) $byCode['MAT-BUSA']['shortage'])->toBe(20.0);
    expect($byCode['MAT-BUSA']['action'])->toBe('Purchase 20');

    expect((float) $byCode['MAT-KAINJOK']['shortage'])->toBe(0.0);

    expect((float) $byCode['MAT-BAUT']['requirement'])->toBe(1200.0);
    expect((float) $byCode['MAT-BAUT']['shortage'])->toBe(0.0);

    expect((float) $byCode['MAT-CAT']['requirement'])->toBe(50.0);
    expect((float) $byCode['MAT-CAT']['stock'])->toBe(30.0);
    expect((float) $byCode['MAT-CAT']['shortage'])->toBe(20.0);
    expect($byCode['MAT-CAT']['action'])->toBe('Purchase 20');

    expect((float) $byCode['MAT-RODA']['shortage'])->toBe(0.0);
});

test('MRP for a product with no active BOM returns an empty result', function () {
    $product = Product::factory()->for($this->company)->manufactured()->create([
        'base_uom_id' => \App\Models\UnitOfMeasure::factory()->for($this->company)->create()->id,
    ]);
    $warehouse = Warehouse::where('code', 'WH-RM')->first();

    $results = app(MrpService::class)->run($product, 10, $warehouse);

    expect($results)->toBe([]);
});

test('MRP requirement scales linearly with demand quantity', function () {
    $product = Product::where('code', 'CHR-X1')->first();
    $warehouse = Warehouse::where('code', 'WH-RM')->first();

    $results = app(MrpService::class)->run($product, 50, $warehouse);
    $byCode = collect($results)->keyBy('code');

    expect((float) $byCode['MAT-HOLLOW']['requirement'])->toBe(250.0); // half of 500
    expect((float) $byCode['MAT-BUSA']['requirement'])->toBe(50.0);
});

test('MRP treats a component with zero stock as fully short', function () {
    $product = Product::where('code', 'CHR-X1')->first();
    $emptyWarehouse = Warehouse::factory()->for($this->company)->create();

    $results = app(MrpService::class)->run($product, 100, $emptyWarehouse);
    $byCode = collect($results)->keyBy('code');

    expect((float) $byCode['MAT-HOLLOW']['stock'])->toBe(0.0);
    expect((float) $byCode['MAT-HOLLOW']['shortage'])->toBe(500.0);
    expect($byCode['MAT-HOLLOW']['action'])->toBe('Purchase 500');
});
