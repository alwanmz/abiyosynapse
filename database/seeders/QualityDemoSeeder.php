<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Production Order run through the full Fase 3.5 QC path
 * (release -> issue materials -> submit for QC -> final inspection ->
 * complete after QC with a partial reject), reproducing the blueprint's
 * §10-12 "good vs reject" example proportionally: 10 units planned, 9
 * pass / 1 reject — so the Quality Inspections and NCR list pages have a
 * real, working example (including an auto-opened NCR) to show instead
 * of being empty on a fresh install. Mirrors the exact orchestration
 * QualityInspectionController::storeFinal() uses: record the Final
 * inspection first (which auto-opens an NCR on any fail), THEN call
 * completeAfterQc() — calling completeAfterQc() alone does not create an
 * inspection record or NCR, those only come from QualityInspectionService.
 *
 * Requires MasterDataDemoSeeder, InventoryDemoSeeder, and
 * ManufacturingDemoSeeder to have run first (product/BOM/routing/stock
 * must already exist). Idempotent via a fixed order number.
 */
class QualityDemoSeeder extends Seeder
{
    private const ORDER_NUMBER = 'MO-DEMO-QC-01';
    private const PLANNED_QUANTITY = 10;
    private const PASSED_QUANTITY = 9;
    private const FAILED_QUANTITY = 1;

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (ProductionOrder::where('company_id', $company->id)->where('number', self::ORDER_NUMBER)->exists()) {
            return;
        }

        $chrX1 = Product::where('company_id', $company->id)->where('code', 'CHR-X1')->first();
        // ProductionOrder has a single warehouse_id used for both
        // consuming raw materials (issueMaterials()) and receiving
        // finished output (completeAfterQc()) — since raw material stock
        // only exists in WH-RM (per InventoryDemoSeeder), the order has
        // to run there, same as the earlier MO-2026-000001 demo order.
        // SalesDemoSeeder checks WH-RM for sellable CHR-X1 stock to match.
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'WH-RM')->first();

        if (! $chrX1 || ! $chrX1->bom_id || ! $chrX1->routing_id || ! $warehouse) {
            return;
        }

        $order = ProductionOrder::create([
            'company_id' => $company->id,
            'number' => self::ORDER_NUMBER,
            'product_id' => $chrX1->id,
            'bom_id' => $chrX1->bom_id,
            'routing_id' => $chrX1->routing_id,
            'warehouse_id' => $warehouse->id,
            'planned_quantity' => self::PLANNED_QUANTITY,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'planned',
        ]);

        $service = app(ProductionOrderService::class);

        $order = $service->release($order);
        $service->issueMaterials($order->fresh());
        $order = $service->submitForQc($order->fresh());

        app(QualityInspectionService::class)->inspect(
            'final',
            $order,
            $chrX1,
            self::PLANNED_QUANTITY,
            self::PASSED_QUANTITY,
            'Demo: hasil inspeksi akhir contoh blueprint (9 lolos, 1 reject karena cacat las).',
        );

        $service->completeAfterQc($order, self::PASSED_QUANTITY, self::FAILED_QUANTITY);
    }
}
