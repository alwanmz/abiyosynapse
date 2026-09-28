<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CurrencySeeder::class,
            SubscriptionPlanSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
            MasterDataDemoSeeder::class,
            InventoryDemoSeeder::class,
            ManufacturingDemoSeeder::class,
            QualityDemoSeeder::class,
            PurchasingDemoSeeder::class,
            SalesDemoSeeder::class,
            CashBankDemoSeeder::class,
            ArDemoSeeder::class,
            ApDemoSeeder::class,
            FixedAssetDemoSeeder::class,
            MaintenanceDemoSeeder::class,
            MultiCurrencyDemoSeeder::class,
        ]);
    }
}
