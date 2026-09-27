<?php

use App\Models\Bom;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Routing;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\ProductionCostingService;
use App\Services\Manufacturing\ProductionOrderService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->firstOrFail();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $this->uomKg = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'KG']);
    $this->uomPcs = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS']);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
    $this->material = Product::factory()->for($this->company)->create([
        'code' => 'MAT-COST',
        'base_uom_id' => $this->uomKg->id,
        'type' => 'purchased',
        'standard_cost' => 20000,
    ]);
    $this->finishedProduct = Product::factory()->for($this->company)->manufactured()->create([
        'code' => 'FG-COST',
        'base_uom_id' => $this->uomPcs->id,
        'standard_cost' => 75000,
    ]);

    app(InventoryValuationService::class)->receive($this->material, $this->warehouse, 100, 25000);

    $this->bom = Bom::factory()->for($this->company)->create([
        'product_id' => $this->finishedProduct->id,
        'batch_quantity' => 1,
    ]);
    $this->bom->lines()->create([
        'component_id' => $this->material->id,
        'quantity_per_batch' => 5,
        'uom_id' => $this->uomKg->id,
        'scrap_percentage' => 0,
        'sequence' => 10,
    ]);

    $this->workCenter = WorkCenter::factory()->for($this->company)->create([
        'cost_rate_per_minute' => 100,
    ]);
    $this->routing = Routing::factory()->for($this->company)->create([
        'product_id' => $this->finishedProduct->id,
    ]);
    $this->routing->operations()->create([
        'sequence' => 10,
        'name' => 'Cutting',
        'work_center_id' => $this->workCenter->id,
        'setup_minutes' => 15,
        'run_minutes_per_unit' => 3,
    ]);

    $this->order = ProductionOrder::factory()->for($this->company)->create([
        'product_id' => $this->finishedProduct->id,
        'bom_id' => $this->bom->id,
        'routing_id' => $this->routing->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_quantity' => 10,
    ]);
});

test('production costing captures material, conversion, and total variance', function () {
    $production = app(ProductionOrderService::class);
    $costing = app(ProductionCostingService::class);

    $order = $production->release($this->order);
    $production->issueMaterials($order);
    $operation = $order->fresh()->operations->first();
    $production->startOperation($operation);
    $production->completeOperation($operation->fresh(), actualMinutes: 50, outputQuantity: 10);
    $completed = $production->complete($order->fresh(), 10, 'Costing test bypasses Final QC intentionally.');

    $costed = $costing->cost($completed);

    expect((float) $costed->standard_material_cost)->toBe(1000000.0)
        ->and((float) $costed->actual_material_cost)->toBe(1250000.0)
        ->and((float) $costed->standard_conversion_cost)->toBe(4500.0)
        ->and((float) $costed->actual_conversion_cost)->toBe(5000.0)
        ->and((float) $costed->standard_total_cost)->toBe(1004500.0)
        ->and((float) $costed->actual_total_cost)->toBe(1255000.0)
        ->and((float) $costed->variance_amount)->toBe(250500.0)
        ->and((float) $costed->variance_percentage)->toBe(24.9378)
        ->and($costed->costed_at)->not->toBeNull();
});

test('production costing refuses an unfinished order', function () {
    app(ProductionCostingService::class)->cost($this->order);
})->throws(RuntimeException::class);
