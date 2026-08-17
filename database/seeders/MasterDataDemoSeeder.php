<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds demo master data for the default company, following the CHR-X1
 * ("Kursi Kantor X1") example used throughout the boss's manufacturing
 * blueprint (Nexumi_ERP_Manufacturing_Flow_Lengkap.pdf) — UOM, warehouse,
 * a supplier/customer, a tax code, and the raw materials + the finished
 * product itself. BOM/Routing for CHR-X1 are NOT seeded here since those
 * models don't exist until Fase 3 — this seeder only covers what Fase 1
 * (Master Data) and Fase 2 (Inventory) can represent.
 */
class MasterDataDemoSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> */
    private const UOMS = [
        ['PCS', 'Pieces'],
        ['KG', 'Kilogram'],
        ['M', 'Meter'],
        ['L', 'Liter'],
    ];

    /**
     * Raw materials for CHR-X1, per blueprint §4 (BOM table): code, name,
     * uom code, standard cost per unit.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: float}>
     */
    private const RAW_MATERIALS = [
        ['MAT-HOLLOW', 'Besi Hollow', 'KG', 25000],
        ['MAT-BUSA', 'Busa', 'PCS', 45000],
        ['MAT-KAINJOK', 'Kain Jok', 'M', 60000],
        ['MAT-BAUT', 'Baut', 'PCS', 500],
        ['MAT-CAT', 'Cat', 'L', 85000],
        ['MAT-RODA', 'Roda', 'PCS', 15000],
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        $uoms = [];
        foreach (self::UOMS as [$code, $name]) {
            $uoms[$code] = UnitOfMeasure::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        $rawMaterialCategory = ProductCategory::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Bahan Baku'],
        );

        $finishedGoodCategory = ProductCategory::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Office Furniture'],
        );

        $rawMaterialsWarehouse = Warehouse::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-RM'],
            ['name' => 'Gudang Bahan Baku', 'is_active' => true],
        );

        $finishedGoodsWarehouse = Warehouse::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'FG-01'],
            ['name' => 'Gudang Barang Jadi', 'is_active' => true],
        );

        foreach (self::RAW_MATERIALS as [$code, $name, $uomCode, $cost]) {
            Product::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => 'purchased',
                    'product_category_id' => $rawMaterialCategory->id,
                    'base_uom_id' => $uoms[$uomCode]->id,
                    'make_or_buy' => 'buy',
                    'lot_tracked' => false,
                    'serial_tracked' => false,
                    'standard_cost' => $cost,
                    'valuation_method' => 'average',
                    'selling_price' => 0,
                    'default_warehouse_id' => $rawMaterialsWarehouse->id,
                    'status' => 'active',
                ],
            );
        }

        Product::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CHR-X1'],
            [
                'name' => 'Kursi Kantor X1',
                'type' => 'manufactured',
                'product_category_id' => $finishedGoodCategory->id,
                'base_uom_id' => $uoms['PCS']->id,
                'make_or_buy' => 'make',
                'lot_tracked' => false,
                'serial_tracked' => false,
                'standard_cost' => 750000,
                'valuation_method' => 'average',
                'selling_price' => 1250000,
                'default_warehouse_id' => $finishedGoodsWarehouse->id,
                'status' => 'active',
            ],
        );

        Supplier::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SUP-0001'],
            [
                'name' => 'PT Sumber Material Jaya',
                'email' => 'sales@sumbermaterial.example',
                'phone' => '021-5550101',
                'payment_term_days' => 30,
                'is_active' => true,
            ],
        );

        Customer::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CUST-0001'],
            [
                'name' => 'PT ABC Indonesia',
                'email' => 'purchasing@abcindonesia.example',
                'phone' => '021-5550202',
                'payment_term_days' => 30,
                'is_active' => true,
            ],
        );

        $ppnAccount = \App\Models\Account::where('company_id', $company->id)->where('code', '2.1.3')->first();

        TaxCode::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PPN11'],
            [
                'name' => 'PPN 11%',
                'rate' => 11,
                'account_id' => $ppnAccount?->id,
                'is_active' => true,
            ],
        );
    }
}
