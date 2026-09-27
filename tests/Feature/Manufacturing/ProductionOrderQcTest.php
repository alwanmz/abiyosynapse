<?php

use App\Models\Bom;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Routing;
use App\Models\StockLevel;
use App\Models\StockQualityBalance;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Manufacturing\ProductReadinessService;
use App\Services\Quality\QualityInspectionService;
use App\Services\Quality\QualityReleaseService;
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
    $this->user = \App\Models\User::factory()->create();
    TaxCode::factory()->for($this->company)->create(['is_active' => true]);

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

test('final QC holds all output, then Quality Release makes only the passed quantity sellable', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());
    $inspection = app(QualityInspectionService::class)->inspect('final', $order, $this->chrX1, 100, 97);

    $held = $service->completeAfterQc($order, passedQuantity: 97, failedQuantity: 3, inspection: $inspection);

    expect($held->status)->toBe('qc');
    expect((float) $held->produced_quantity)->toBe(100.0);
    expect((float) $held->good_quantity)->toBe(97.0);
    expect((float) $held->rejected_quantity)->toBe(3.0);

    $fgLevel = StockLevel::where('product_id', $this->chrX1->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $fgLevel->quantity_on_hand)->toBe(100.0);
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'hold')->value('quantity'))->toBe(100.0);

    $released = app(QualityReleaseService::class)->release($inspection->fresh(), $this->user);
    expect($released->status)->toBe('completed');
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'approved')->value('quantity'))->toBe(97.0);
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'hold')->value('quantity'))->toBe(3.0);
    $readiness = app(ProductReadinessService::class)->evaluate($this->chrX1, $this->warehouse);
    expect($readiness->status)->toBe('ready_for_sale');
    expect($readiness->availableForSale)->toBe(97.0);
});

test('QC output posts all held finished goods and scrap is posted only after NCR disposition', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());
    $inspection = app(QualityInspectionService::class)->inspect('final', $order, $this->chrX1, 100, 97);

    $held = $service->completeAfterQc($order, passedQuantity: 97, failedQuantity: 3, inspection: $inspection);

    $fgEntry = JournalEntry::where('sourceable_type', $held->getMorphClass())
        ->where('sourceable_id', $held->id)
        ->where('description', 'like', '%Quality Release%')
        ->first();
    expect((float) $fgEntry->total_debit)->toBe(7500000.0); // 100 * 75000 is held in FG
    expect($fgEntry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('1.1.6');

    $ncr = \App\Models\NonConformanceReport::where('quality_inspection_id', $inspection->id)->firstOrFail();
    app(QualityReleaseService::class)->disposition($ncr, 'scrap', 'Unrecoverable defects', $this->user);

    $scrapEntry = JournalEntry::where('sourceable_type', $ncr->getMorphClass())
        ->where('sourceable_id', $ncr->id)
        ->where('description', 'like', '%Scrap%')
        ->first();
    expect($scrapEntry)->not->toBeNull();
    expect((float) $scrapEntry->total_debit)->toBe(225000.0); // 3 * 75000
    expect($scrapEntry->lines()->where('debit', '>', 0)->first()->account->code)->toBe('5.4');
});

test('a rework disposition creates a child order and removes only the rejected quantity from finished goods', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());
    $inspection = app(QualityInspectionService::class)->inspect('final', $order, $this->chrX1, 100, 97);
    $service->completeAfterQc($order, 97, 3, $inspection);

    $ncr = \App\Models\NonConformanceReport::where('quality_inspection_id', $inspection->id)->firstOrFail();
    $child = app(QualityReleaseService::class)->disposition($ncr, 'rework', 'Repair the three rejected chairs', $this->user);

    expect($child)->not->toBeNull();
    expect($child->parent_production_order_id)->toBe($order->id);
    expect($child->source_ncr_id)->toBe($ncr->id);
    expect($child->is_rework)->toBeTrue();
    expect($child->status)->toBe('in_production');
    expect((float) StockLevel::where('product_id', $this->chrX1->id)->where('warehouse_id', $this->warehouse->id)->value('quantity_on_hand'))->toBe(97.0);
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'rework')->value('quantity'))->toBe(0.0);
});

test('completeAfterQc with zero rejects behaves like a full pass', function () {
    $service = app(ProductionOrderService::class);
    $order = $service->submitForQc($this->order->fresh());

    $inspection = app(QualityInspectionService::class)->inspect('final', $order, $this->chrX1, 100, 100);
    $completed = $service->completeAfterQc($order, passedQuantity: 100, failedQuantity: 0, inspection: $inspection);

    expect((float) $completed->produced_quantity)->toBe(100.0);
    expect((float) $completed->rejected_quantity)->toBe(0.0);

    $scrapEntry = JournalEntry::where('sourceable_type', $completed->getMorphClass())
        ->where('description', 'like', '%Scrap%')
        ->first();
    expect($scrapEntry)->toBeNull();
    app(QualityReleaseService::class)->release($inspection->fresh(), $this->user);
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'approved')->value('quantity'))->toBe(100.0);
});

test('completeAfterQc before qc status throws', function () {
    $service = app(ProductionOrderService::class);

    $service->completeAfterQc($this->order->fresh(), 97, 3);
})->throws(RuntimeException::class);

test('the legacy complete() path without QC still works for orders that skip Fase 3.5', function () {
    $service = app(ProductionOrderService::class);

    $completed = $service->complete($this->order->fresh(), 100, 'Customer sample must ship before QC window', $this->user);

    expect($completed->status)->toBe('completed');
    expect((float) $completed->produced_quantity)->toBe(100.0);
    expect((float) StockQualityBalance::where('product_id', $this->chrX1->id)->where('quality_state', 'approved')->value('quantity'))->toBe(100.0);
});
