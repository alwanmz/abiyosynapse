<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use Illuminate\Database\Seeder;

/**
 * Seeds opening stock for the CHR-X1 raw materials, matching the "Stock"
 * column from the MRP example table in the boss's blueprint (§7,
 * Nexumi_ERP_Manufacturing_Flow_Lengkap.pdf) — so a future Fase 3 MRP run
 * against a 100-unit CHR-X1 sales order reproduces the same shortage
 * numbers shown in the document (Busa short 20, Cat short 20L).
 *
 * Requires MasterDataDemoSeeder to have run first (products/warehouses
 * must already exist).
 */
class InventoryDemoSeeder extends Seeder
{
    /**
     * product code => [warehouse code, quantity, unit cost].
     *
     * @var array<string, array{0: string, 1: float, 2: float}>
     */
    private const OPENING_STOCK = [
        'MAT-HOLLOW' => ['WH-RM', 700, 25000],
        'MAT-BUSA' => ['WH-RM', 80, 45000],
        'MAT-KAINJOK' => ['WH-RM', 200, 60000],
        'MAT-BAUT' => ['WH-RM', 2000, 500],
        'MAT-CAT' => ['WH-RM', 30, 85000],
        'MAT-RODA' => ['WH-RM', 600, 15000],
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);
        $valuation = app(InventoryValuationService::class);

        foreach (self::OPENING_STOCK as $productCode => [$warehouseCode, $quantity, $unitCost]) {
            $product = Product::where('company_id', $company->id)->where('code', $productCode)->first();
            $warehouse = Warehouse::where('company_id', $company->id)->where('code', $warehouseCode)->first();

            if (! $product || ! $warehouse) {
                continue;
            }

            $alreadyStocked = \App\Models\StockLevel::where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity_on_hand', '>', 0)
                ->exists();

            if ($alreadyStocked) {
                continue;
            }

            $valuation->receive(
                $product,
                $warehouse,
                $quantity,
                $unitCost,
                notes: 'Saldo awal (demo seeder)',
            );
        }
    }
}
