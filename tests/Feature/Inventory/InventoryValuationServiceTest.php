<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockLot;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);

    $this->uom = UnitOfMeasure::factory()->for($this->company)->create();
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
});

function makeProduct(Company $company, UnitOfMeasure $uom, string $valuation = 'average'): Product
{
    return Product::factory()->for($company)->create([
        'base_uom_id' => $uom->id,
        'valuation_method' => $valuation,
    ]);
}

test('receiving stock creates a movement and updates the stock level', function () {
    $product = makeProduct($this->company, $this->uom);
    $service = app(InventoryValuationService::class);

    $movement = $service->receive($product, $this->warehouse, 100, 5000);

    expect($movement->type)->toBe('in');
    expect((float) $movement->balance_quantity)->toBe(100.0);

    $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(100.0);
    expect((float) $level->average_unit_cost)->toBe(5000.0);
});

test('average valuation blends cost across multiple receipts', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000); // 100 @ 1000 = 100,000
    $service->receive($product, $this->warehouse, 100, 2000); // 100 @ 2000 = 200,000
    // total 200 units, 300,000 value => avg 1500

    $level = StockLevel::where('product_id', $product->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(200.0);
    expect((float) $level->average_unit_cost)->toBe(1500.0);
});

test('average valuation issues stock at the current weighted-average cost', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000);
    $service->receive($product, $this->warehouse, 100, 2000); // avg 1500

    $result = $service->issue($product, $this->warehouse, 50);

    expect($result['total_cost'])->toBe(75000.0); // 50 * 1500
    expect((float) $result['movement']->balance_quantity)->toBe(150.0);
});

test('fifo valuation consumes the oldest layer first', function () {
    $product = makeProduct($this->company, $this->uom, 'fifo');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000); // lot 1: 100 @ 1000
    $service->receive($product, $this->warehouse, 100, 2000); // lot 2: 100 @ 2000

    $result = $service->issue($product, $this->warehouse, 50);

    // Should consume 50 from the oldest (cheapest) lot first.
    expect($result['total_cost'])->toBe(50000.0); // 50 * 1000

    $lots = StockLot::where('product_id', $product->id)->orderBy('received_at')->get();
    expect((float) $lots[0]->quantity_remaining)->toBe(50.0);
    expect((float) $lots[1]->quantity_remaining)->toBe(100.0);
});

test('fifo valuation splits across layers when a single layer is insufficient', function () {
    $product = makeProduct($this->company, $this->uom, 'fifo');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 50, 1000);  // lot 1: 50 @ 1000
    $service->receive($product, $this->warehouse, 100, 2000); // lot 2: 100 @ 2000

    $result = $service->issue($product, $this->warehouse, 80);

    // 50 @ 1000 + 30 @ 2000 = 50,000 + 60,000 = 110,000
    expect($result['total_cost'])->toBe(110000.0);

    $lots = StockLot::where('product_id', $product->id)->orderBy('received_at')->get();
    expect((float) $lots[0]->quantity_remaining)->toBe(0.0);
    expect((float) $lots[1]->quantity_remaining)->toBe(70.0);
});

test('issuing more than available stock throws', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 10, 1000);

    $service->issue($product, $this->warehouse, 20);
})->throws(RuntimeException::class, 'Insufficient stock');

test('adjustTo increases stock with a receive when counted quantity is higher', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000);
    $movement = $service->adjustTo($product, $this->warehouse, 120);

    expect($movement->type)->toBe('in');
    expect((float) $movement->quantity)->toBe(20.0);

    $level = StockLevel::where('product_id', $product->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(120.0);
});

test('adjustTo decreases stock with an issue when counted quantity is lower', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000);
    $movement = $service->adjustTo($product, $this->warehouse, 80);

    expect($movement->type)->toBe('out');
    expect((float) $movement->quantity)->toBe(20.0);

    $level = StockLevel::where('product_id', $product->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(80.0);
});

test('adjustTo does nothing when counted quantity matches system quantity', function () {
    $product = makeProduct($this->company, $this->uom, 'average');
    $service = app(InventoryValuationService::class);

    $service->receive($product, $this->warehouse, 100, 1000);
    $movement = $service->adjustTo($product, $this->warehouse, 100);

    expect($movement)->toBeNull();
});

test('stock levels and lots are scoped per company', function () {
    $productA = makeProduct($this->company, $this->uom, 'fifo');
    $service = app(InventoryValuationService::class);
    $service->receive($productA, $this->warehouse, 10, 1000);

    $companyB = Company::factory()->create();
    app(CurrentCompany::class)->set($companyB);
    $uomB = UnitOfMeasure::factory()->for($companyB)->create();
    $warehouseB = Warehouse::factory()->for($companyB)->create();
    $productB = makeProduct($companyB, $uomB, 'fifo');
    $service->receive($productB, $warehouseB, 20, 2000);

    expect(StockLevel::all())->toHaveCount(1);
    expect(StockLot::all())->toHaveCount(1);
});
