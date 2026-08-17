<?php

use App\Models\Bom;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Routing;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\ProductionOrderService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Reproduces the CHR-X1 ("Kursi Kantor X1") example from the boss's
 * manufacturing blueprint end-to-end through Fase 3: BOM -> Routing ->
 * Production Order -> Material Issue -> Shop Floor -> Completion, on a
 * smaller scale (10 units instead of 100) to keep the test fast.
 */
beforeEach(function () {
    // ChartOfAccountsSeeder seeds against the 'default' company code —
    // reuse CompanySeeder so the account codes this service depends on
    // (1.1.4/1.1.5/1.1.6) actually exist.
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $this->uomKg = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'KG']);
    $this->uomPcs = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS']);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();

    $this->hollow = Product::factory()->for($this->company)->create([
        'code' => 'MAT-HOLLOW',
        'base_uom_id' => $this->uomKg->id,
        'type' => 'purchased',
    ]);

    $this->chrX1 = Product::factory()->for($this->company)->manufactured()->create([
        'code' => 'CHR-X1',
        'base_uom_id' => $this->uomPcs->id,
        'standard_cost' => 75000,
    ]);

    app(InventoryValuationService::class)->receive($this->hollow, $this->warehouse, 100, 25000);

    $this->bom = Bom::factory()->for($this->company)->create([
        'product_id' => $this->chrX1->id,
        'batch_quantity' => 1,
    ]);
    $this->bom->lines()->create([
        'component_id' => $this->hollow->id,
        'quantity_per_batch' => 5,
        'uom_id' => $this->uomKg->id,
        'scrap_percentage' => 0,
        'sequence' => 10,
    ]);

    $workCenter = WorkCenter::factory()->for($this->company)->create();
    $this->routing = Routing::factory()->for($this->company)->create(['product_id' => $this->chrX1->id]);
    $this->routing->operations()->create([
        'sequence' => 10,
        'name' => 'Cutting',
        'work_center_id' => $workCenter->id,
        'setup_minutes' => 15,
        'run_minutes_per_unit' => 3,
    ]);

    $this->order = ProductionOrder::factory()->for($this->company)->create([
        'product_id' => $this->chrX1->id,
        'bom_id' => $this->bom->id,
        'routing_id' => $this->routing->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_quantity' => 10,
    ]);
});

test('releasing a production order snapshots BOM components and routing operations', function () {
    $service = app(ProductionOrderService::class);

    $released = $service->release($this->order);

    expect($released->status)->toBe('released');
    expect($released->components)->toHaveCount(1);
    expect((float) $released->components->first()->required_quantity)->toBe(50.0); // 5kg * 10 units

    expect($released->operations)->toHaveCount(1);
    expect((float) $released->operations->first()->planned_minutes)->toBe(45.0); // 15 setup + 3*10 run
});

test('releasing a non-planned order throws', function () {
    $service = app(ProductionOrderService::class);
    $service->release($this->order);

    $service->release($this->order->fresh());
})->throws(RuntimeException::class);

test('issuing materials consumes stock and posts WIP debit / raw material credit', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->release($this->order);

    $service->issueMaterials($order);
    $order->refresh();

    expect($order->status)->toBe('in_production');
    expect((float) $order->components->first()->issued_quantity)->toBe(50.0);

    $entry = JournalEntry::where('sourceable_type', $order->getMorphClass())
        ->where('sourceable_id', $order->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect((float) $entry->total_debit)->toBe(1250000.0); // 50kg * 25000
    expect($entry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('1.1.5'); // WIP
    expect($entry->lines()->where('credit', '>', 0)->first()->account->code)->toBe('1.1.4'); // Raw Material
});

test('issuing materials twice only issues the remaining quantity', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->release($this->order);

    $service->issueMaterials($order);
    $service->issueMaterials($order->fresh());

    $order->refresh();
    expect((float) $order->components->first()->issued_quantity)->toBe(50.0);

    // Only one journal entry should exist since the second call had nothing to issue.
    expect(JournalEntry::where('sourceable_type', $order->getMorphClass())->where('sourceable_id', $order->id)->count())->toBe(1);
});

test('issuing materials before release throws', function () {
    $service = app(ProductionOrderService::class);

    $service->issueMaterials($this->order);
})->throws(RuntimeException::class);

test('shop floor operations progress from pending to in_progress to complete', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->release($this->order);
    $operation = $order->operations->first();

    $service->startOperation($operation);
    expect($operation->fresh()->status)->toBe('in_progress');

    $service->completeOperation($operation->fresh(), actualMinutes: 50, outputQuantity: 10);
    $completed = $operation->fresh();
    expect($completed->status)->toBe('complete');
    expect((float) $completed->output_quantity)->toBe(10.0);
});

test('completing a production order receives finished goods and posts FG debit / WIP credit', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->release($this->order);
    $service->issueMaterials($order->fresh());

    $completed = $service->complete($order->fresh(), 10);

    expect($completed->status)->toBe('completed');
    expect((float) $completed->produced_quantity)->toBe(10.0);

    $fgLevel = \App\Models\StockLevel::where('product_id', $this->chrX1->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $fgLevel->quantity_on_hand)->toBe(10.0);

    $entry = JournalEntry::where('sourceable_type', $completed->getMorphClass())
        ->where('sourceable_id', $completed->id)
        ->where('description', 'like', '%completion%')
        ->first();

    expect((float) $entry->total_debit)->toBe(750000.0); // 10 * 75000 standard cost
    expect($entry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('1.1.6'); // Finished Goods
    expect($entry->lines()->where('credit', '>', 0)->first()->account->code)->toBe('1.1.5'); // WIP
});

test('completing a production order that is not in progress throws', function () {
    $service = app(ProductionOrderService::class);

    $service->complete($this->order, 10);
})->throws(RuntimeException::class);

test('full CHR-X1 scenario end to end matches blueprint flow', function () {
    $service = app(ProductionOrderService::class);

    $order = $service->release($this->order);
    expect($order->status)->toBe('released');

    $service->issueMaterials($order->fresh());
    expect($order->fresh()->status)->toBe('in_production');

    $operation = $order->fresh()->operations->first();
    $service->startOperation($operation);
    $service->completeOperation($operation->fresh(), actualMinutes: 45, outputQuantity: 10);

    $completed = $service->complete($order->fresh(), 10);
    expect($completed->status)->toBe('completed');

    // Raw material stock depleted by the BOM requirement.
    $rawLevel = \App\Models\StockLevel::where('product_id', $this->hollow->id)->first();
    expect((float) $rawLevel->quantity_on_hand)->toBe(50.0); // 100 - 50

    // Finished goods stock increased by produced quantity.
    $fgLevel = \App\Models\StockLevel::where('product_id', $this->chrX1->id)->first();
    expect((float) $fgLevel->quantity_on_hand)->toBe(10.0);
});
