<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockOpname;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;

test('creating a stock opname snapshots current stock levels into lines', function () {
    $user = User::factory()->create();
    app(CurrentCompany::class)->set($user->currentCompany);

    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $warehouse = Warehouse::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);

    app(InventoryValuationService::class)->receive($product, $warehouse, 50, 1000);

    $response = $this->actingAs($user)->post('/inventory/stock-opnames', [
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
    ]);

    $opname = StockOpname::first();
    $response->assertRedirect(route('inventory.stock-opnames.show', $opname));

    expect($opname->lines)->toHaveCount(1);
    expect((float) $opname->lines->first()->system_quantity)->toBe(50.0);
});

test('saving counted quantities updates the lines without completing the opname', function () {
    $user = User::factory()->create();
    app(CurrentCompany::class)->set($user->currentCompany);

    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $warehouse = Warehouse::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);
    app(InventoryValuationService::class)->receive($product, $warehouse, 50, 1000);

    $this->actingAs($user)->post('/inventory/stock-opnames', [
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
    ]);
    $opname = StockOpname::first();
    $line = $opname->lines->first();

    $response = $this->actingAs($user)->put("/inventory/stock-opnames/{$opname->id}/lines", [
        'lines' => [
            ['id' => $line->id, 'counted_quantity' => 45],
        ],
    ]);

    $response->assertRedirect();
    expect($line->fresh()->counted_quantity)->toBe('45.0000');
    expect($opname->fresh()->status)->toBe('draft');
});

test('completing a stock opname adjusts stock to the counted quantity', function () {
    $user = User::factory()->create();
    app(CurrentCompany::class)->set($user->currentCompany);

    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $warehouse = Warehouse::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);
    app(InventoryValuationService::class)->receive($product, $warehouse, 50, 1000);

    $this->actingAs($user)->post('/inventory/stock-opnames', [
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
    ]);
    $opname = StockOpname::first();
    $line = $opname->lines->first();

    $this->actingAs($user)->put("/inventory/stock-opnames/{$opname->id}/lines", [
        'lines' => [['id' => $line->id, 'counted_quantity' => 45]],
    ]);

    $response = $this->actingAs($user)->post("/inventory/stock-opnames/{$opname->id}/complete");

    $response->assertRedirect(route('inventory.stock-opnames.show', $opname));
    expect($opname->fresh()->status)->toBe('completed');

    $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(45.0);
});

test('a completed stock opname cannot be edited or completed again', function () {
    $user = User::factory()->create();
    app(CurrentCompany::class)->set($user->currentCompany);

    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $warehouse = Warehouse::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);
    app(InventoryValuationService::class)->receive($product, $warehouse, 50, 1000);

    $this->actingAs($user)->post('/inventory/stock-opnames', [
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
    ]);
    $opname = StockOpname::first();
    $line = $opname->lines->first();
    $this->actingAs($user)->put("/inventory/stock-opnames/{$opname->id}/lines", [
        'lines' => [['id' => $line->id, 'counted_quantity' => 45]],
    ]);
    $this->actingAs($user)->post("/inventory/stock-opnames/{$opname->id}/complete");

    $secondComplete = $this->actingAs($user)->post("/inventory/stock-opnames/{$opname->id}/complete");
    $secondComplete->assertSessionHas('error');

    $editAttempt = $this->actingAs($user)->put("/inventory/stock-opnames/{$opname->id}/lines", [
        'lines' => [['id' => $line->id, 'counted_quantity' => 99]],
    ]);
    $editAttempt->assertSessionHas('error');
    expect($line->fresh()->counted_quantity)->toBe('45.0000');
});
