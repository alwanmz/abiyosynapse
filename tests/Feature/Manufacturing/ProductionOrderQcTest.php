<?php

use App\Models\Bom;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Routing;
use App\Models\StockLevel;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\ProductionOrderService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Reproduces the blueprint's "planned 100 -> produced 100 -> good 97 ->
 * reject 3 -> FG stock +97" example (§10-12) through the Fase 3.5 QC
 * path: In Production -> QC -> Completed, with the 3 rejected units
 * scrapped rather than silently entering finished goods stock.
 */
beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $uomKg = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'KG']);
    $uomPcs = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS']);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();

    $hollow = Product::factory()->for($this->company)->create([
        'base_uom_id' => $uomKg->id,
        'type' => 'purchased',
    ]);

    $this->chrX1 = Product::factory()->for($this->company)->manufactured()->create([
        'base_uom_id' => $uomPcs->id,
        'standard_cost' => 75000,
    ]);

    app(InventoryValuationService::class)->receive($hollow, $this->warehouse, 1000, 25000);

    $bom = Bom::factory()->for($this->company)->create(['product_id' => $this->chrX1->id, 'batch_quantity' => 1]);
    $bom->lines()->create([
        'component_id' => $hollow->id,
        'quantity_per_batch' => 5,
        'uom_id' => $uomKg->id,
        'sequence' => 10,
    ]);

    $workCenter = WorkCenter::factory()->for($this->company)->create();
    $routing = Routing::factory()->for($this->company)->create(['product_id' => $this->chrX1->id]);
    $routing->operations()->create([
        'sequence' => 10,
        'name' => 'Assembly',
        'work_center_id' => $workCenter->id,
        'setup_minutes' => 10,
        'run_minutes_per_unit' => 12,
    ]);

    $this->order = ProductionOrder::factory()->for($this->company)->create([
        'product_id' => $this->chrX1->id,
        'bom_id' => $bom->id,
        'routing_id' => $routing->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_quantity' => 100,
    ]);

    $service = app(ProductionOrderService::class);
    $this->order = $service->release($this->order);
    $service->issueMaterials($this->order->fresh());
});

test('submitForQc moves an in-production order to qc without touching inventory', function () {
    $service = app(ProductionOrderService::class);

    $order = $service->submitForQc($this->order->fresh());

    expect($order->status)->toBe('qc');

    $fgLevel = StockLevel::where('product_id', $this->chrX1->id)->first();
    expect($fgLevel)->toBeNull(); // nothing received into FG stock yet
});

test('submitForQc before in_production throws', function () {
    $service = app(ProductionOrderService::class);
    $planned = ProductionOrder::factory()->for($this->company)->create([
        'product_id' => $this->chrX1->id,
        'bom_id' => $this->order->bom_id,
        'routing_id' => $this->order->routing_id,
        'warehouse_id' => $this->warehouse->id,
        'planned_quantity' => 10,
        'status' => 'planned',
    ]);

    $service->submitForQc($planned);
})->throws(RuntimeException::class);

test('completeAfterQc receives only the passed quantity into finished goods stock', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());

    $completed = $service->completeAfterQc($order, passedQuantity: 97, failedQuantity: 3);

    expect($completed->status)->toBe('completed');
    expect((float) $completed->produced_quantity)->toBe(97.0);
    expect((float) $completed->rejected_quantity)->toBe(3.0);

    $fgLevel = StockLevel::where('product_id', $this->chrX1->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $fgLevel->quantity_on_hand)->toBe(97.0); // NOT 100 — matches blueprint example exactly
});

test('completeAfterQc posts finished goods debit for the good quantity and scrap expense for the rejected quantity', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());

    $completed = $service->completeAfterQc($order, passedQuantity: 97, failedQuantity: 3);

    $fgEntry = JournalEntry::where('sourceable_type', $completed->getMorphClass())
        ->where('sourceable_id', $completed->id)
        ->where('description', 'like', '%completion%')
        ->first();
    expect((float) $fgEntry->total_debit)->toBe(7275000.0); // 97 * 75000
    expect($fgEntry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('1.1.6');

    $scrapEntry = JournalEntry::where('sourceable_type', $completed->getMorphClass())
        ->where('sourceable_id', $completed->id)
        ->where('description', 'like', '%Scrap%')
        ->first();
    expect($scrapEntry)->not->toBeNull();
    expect((float) $scrapEntry->total_debit)->toBe(225000.0); // 3 * 75000
    expect($scrapEntry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('5.4');
});

test('completeAfterQc with zero rejects behaves like a full pass', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());

    $completed = $service->completeAfterQc($order, passedQuantity: 100, failedQuantity: 0);

    expect((float) $completed->produced_quantity)->toBe(100.0);
    expect((float) $completed->rejected_quantity)->toBe(0.0);

    $scrapEntry = JournalEntry::where('sourceable_type', $completed->getMorphClass())
        ->where('description', 'like', '%Scrap%')
        ->first();
    expect($scrapEntry)->toBeNull();
});

test('completeAfterQc before qc status throws', function () {
    $service = app(ProductionOrderService::class);

    $service->completeAfterQc($this->order->fresh(), 97, 3);
})->throws(RuntimeException::class);

test('the legacy complete() path without QC still works for orders that skip Fase 3.5', function () {
    $service = app(ProductionOrderService::class);

    $completed = $service->complete($this->order->fresh(), 100);

    expect($completed->status)->toBe('completed');
    expect((float) $completed->produced_quantity)->toBe(100.0);
});
