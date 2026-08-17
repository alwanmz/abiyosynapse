<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds the CHR-X1 BOM, Work Centers, and Routing exactly as specified
 * in the boss's blueprint (§4-5, Nexumi_ERP_Manufacturing_Flow_Lengkap.pdf)
 * so Fase 3 (BOM/Routing/Production Order/MRP) has a ready-to-use
 * reference dataset that reproduces the document's example numbers.
 *
 * Requires MasterDataDemoSeeder to have run first.
 */
class ManufacturingDemoSeeder extends Seeder
{
    /**
     * component product code => qty per 100 PCS of CHR-X1 (blueprint §4).
     *
     * @var array<string, float>
     */
    private const BOM_LINES = [
        'MAT-HOLLOW' => 500,   // 5 KG / unit
        'MAT-BUSA' => 100,     // 1 PCS / unit
        'MAT-KAINJOK' => 150,  // 1.5 M / unit
        'MAT-BAUT' => 1200,    // 12 PCS / unit
        'MAT-CAT' => 50,       // 0.5 L / unit
        'MAT-RODA' => 500,     // 5 PCS / unit
    ];

    /**
     * Work centers + routing operations (blueprint §5): code, name,
     * operation name, setup minutes, run minutes/unit.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const OPERATIONS = [
        ['WC-CUT-01', 'Cutting Station', 'Cutting', 15, 3],
        ['WC-WELD-01', 'Welding Station', 'Welding', 10, 8],
        ['WC-PAINT-01', 'Painting Station', 'Painting', 20, 10],
        ['WC-ASM-01', 'Assembly Station', 'Assembly', 10, 12],
        ['WC-QC-01', 'QC Station', 'QC', 0, 3],
        ['WC-PACK-01', 'Packing Station', 'Packing', 5, 4],
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        $chrX1 = Product::where('company_id', $company->id)->where('code', 'CHR-X1')->first();

        if (! $chrX1) {
            return;
        }

        $bom = \App\Models\Bom::updateOrCreate(
            ['company_id' => $company->id, 'product_id' => $chrX1->id, 'version' => 1],
            ['code' => 'BOM-CHR-X1-01', 'batch_quantity' => 100, 'status' => 'active'],
        );

        $bom->lines()->delete();
        $sequence = 10;
        foreach (self::BOM_LINES as $componentCode => $qtyPer100) {
            $component = Product::where('company_id', $company->id)->where('code', $componentCode)->first();

            if (! $component) {
                continue;
            }

            $bom->lines()->create([
                'component_id' => $component->id,
                'quantity_per_batch' => $qtyPer100,
                'uom_id' => $component->base_uom_id,
                'scrap_percentage' => 0,
                'sequence' => $sequence,
            ]);

            $sequence += 10;
        }

        $routing = \App\Models\Routing::updateOrCreate(
            ['company_id' => $company->id, 'product_id' => $chrX1->id, 'version' => 1],
            ['code' => 'ROUTE-CHR-X1-01', 'status' => 'active'],
        );

        $routing->operations()->delete();
        $sequence = 10;
        foreach (self::OPERATIONS as [$wcCode, $wcName, $opName, $setup, $run]) {
            $workCenter = WorkCenter::updateOrCreate(
                ['company_id' => $company->id, 'code' => $wcCode],
                ['name' => $wcName, 'capacity_per_day_minutes' => 480, 'is_active' => true],
            );

            $routing->operations()->create([
                'sequence' => $sequence,
                'name' => $opName,
                'work_center_id' => $workCenter->id,
                'setup_minutes' => $setup,
                'run_minutes_per_unit' => $run,
                // QC operation is flagged as an inspection point now —
                // dormant until Fase 3.5 (Quality Management) exists to
                // act on it.
                'is_inspection_point' => $opName === 'QC',
            ]);

            $sequence += 10;
        }

        $chrX1->update(['bom_id' => $bom->id, 'routing_id' => $routing->id]);
    }
}
