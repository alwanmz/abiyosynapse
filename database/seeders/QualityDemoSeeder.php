<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Quality\QualityInspectionService;
use App\Services\Quality\QualityReleaseService;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Production Order run through the full Fase 3.5 QC path
 * (release -> issue materials -> submit for QC -> final inspection ->
 * hold -> Quality Release with a partial reject), reproducing the blueprint's
 * §10-12 "good vs reject" example exactly: 100 units planned, 97
 * pass / 3 reject — so the Quality Inspections and NCR list pages have a
 * real, working example (including an auto-opened NCR) to show instead
 * of being empty on a fresh install. Mirrors the exact orchestration
 * QualityInspectionController::storeFinal() uses: record the Final
 * inspection first (which auto-opens an NCR on any fail), THEN call
 * completeAfterQc() — calling completeAfterQc() alone does not create an
 * inspection record or NCR, those only come from QualityInspectionService.
 *
 * Requires MasterDataDemoSeeder, InventoryDemoSeeder, and
 * ManufacturingDemoSeeder to have run first (product/BOM/routing/stock
 * must already exist). It resumes a fixed order number safely if a previous
 * seeding attempt stopped between workflow stages.
 */
class QualityDemoSeeder extends Seeder
{
    private const ORDER_NUMBER = 'MO-DEMO-CHR-X1-QC-100';
    private const PLANNED_QUANTITY = 100;
    private const PASSED_QUANTITY = 97;
    private const FAILED_QUANTITY = 3;

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        $chrX1 = Product::where('company_id', $company->id)->where('code', 'CHR-X1')->first();
        $user = User::first();
        // ProductionOrder has a single warehouse_id used for both
        // consuming raw materials (issueMaterials()) and receiving
        // finished output (completeAfterQc()) — since raw material stock
        // only exists in WH-RM (per InventoryDemoSeeder), the order has
        // to run there, same as the earlier MO-2026-000001 demo order.
        // SalesDemoSeeder checks WH-RM for sellable CHR-X1 stock to match.
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'WH-RM')->first();

        if (! $chrX1 || ! $chrX1->bom_id || ! $chrX1->routing_id || ! $warehouse || ! $user) {
            return;
        }

        // This is the planned MRP replenishment for the 100-unit demo run.
        // It intentionally tops up only the shortages before material issue;
        // the production output itself still follows the real QC/release flow.
        $valuation = app(InventoryValuationService::class);
        foreach ($chrX1->bom->lines as $line) {
            $required = (float) $line->quantity_per_batch * (self::PLANNED_QUANTITY / (float) $chrX1->bom->batch_quantity);
            $available = (float) \App\Models\StockLevel::where('company_id', $company->id)
                ->where('product_id', $line->component_id)
                ->where('warehouse_id', $warehouse->id)
                ->value('quantity_on_hand');

            if ($available + 0.0001 < $required) {
                $valuation->receive(
                    $line->component,
                    $warehouse,
                    $required - $available,
                    (float) $line->component->standard_cost,
                    notes: 'Demo MRP replenishment for CHR-X1 100-unit run',
                );
            }
        }

        $service = app(ProductionOrderService::class);
        $order = ProductionOrder::firstOrCreate(
            ['company_id' => $company->id, 'number' => self::ORDER_NUMBER],
            [
                'product_id' => $chrX1->id,
                'bom_id' => $chrX1->bom_id,
                'routing_id' => $chrX1->routing_id,
                'warehouse_id' => $warehouse->id,
                'planned_quantity' => self::PLANNED_QUANTITY,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
                'status' => 'planned',
            ],
        );

        if ($order->status === 'planned') {
            $order = $service->release($order);
        }

        if ($order->fresh()->status === 'released') {
            $service->issueMaterials($order->fresh());
        }

        if ($order->fresh()->status === 'in_production') {
            $order = $service->submitForQc($order->fresh());
        }

        $inspection = $order->fresh()->inspections()
            ->where('type', 'final')
            ->latest('id')
            ->first();

        if (! $inspection && $order->fresh()->status === 'qc') {
            $inspection = app(QualityInspectionService::class)->inspect(
                'final',
                $order->fresh(),
                $chrX1,
                self::PLANNED_QUANTITY,
                self::PASSED_QUANTITY,
                'Demo: hasil inspeksi akhir blueprint (97 lolos, 3 reject karena cacat las).',
            );
        }

        if ($inspection && $order->fresh()->status === 'qc' && (float) $order->fresh()->produced_quantity === 0.0) {
            $order = $service->completeAfterQc($order->fresh(), self::PASSED_QUANTITY, self::FAILED_QUANTITY, $inspection);
        }

        if ($inspection && ! $inspection->fresh()->released_at) {
            app(QualityReleaseService::class)->release($inspection->fresh(), $user);
        }
    }
}
