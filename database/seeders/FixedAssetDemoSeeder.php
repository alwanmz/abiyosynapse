<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Services\CurrentCompany;
use App\Services\FixedAssets\FixedAssetService;
use Illuminate\Database\Seeder;

class FixedAssetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company || FixedAsset::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Mesin Cutting CHR-X1 Demo')->exists()) {
            return;
        }

        $assetAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1.2.1')->first();
        $accumulatedAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1.2.2')->first();
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5.5')->first();
        $sourceAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1.1.2.1')->first();

        if (! $assetAccount || ! $accumulatedAccount || ! $expenseAccount || ! $sourceAccount) {
            return;
        }

        app(CurrentCompany::class)->set($company);
        $service = app(FixedAssetService::class);
        $asset = $service->create([
            'name' => 'Mesin Cutting CHR-X1 Demo',
            'category' => 'Mesin Produksi',
            'asset_account_id' => $assetAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedAccount->id,
            'depreciation_expense_account_id' => $expenseAccount->id,
            'source_account_id' => $sourceAccount->id,
            'acquisition_date' => '2026-01-01',
            'placed_in_service_date' => '2026-01-01',
            'acquisition_cost' => 36000000,
            'salvage_value' => 3600000,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'notes' => 'Demo Fixed Asset untuk alur Fase 9.',
        ]);

        $asset = $service->activate($asset);
        $service->depreciate($asset, '2026-01');
        $service->depreciate($asset, '2026-02');
        $service->depreciate($asset, '2026-03');
    }
}
