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
        ]);
    }
}
