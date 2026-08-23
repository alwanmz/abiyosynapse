<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\FixedAsset> */
class FixedAssetFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();
        $assetAccount = Account::factory()->for($company);
        $accumulatedAccount = Account::factory()->for($company);
        $expenseAccount = Account::factory()->for($company);
        $sourceAccount = Account::factory()->for($company);

        return [
            'company_id' => $company,
            'number' => 'FA-' . fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true),
            'category' => 'Equipment',
            'asset_account_id' => $assetAccount,
            'accumulated_depreciation_account_id' => $accumulatedAccount,
            'depreciation_expense_account_id' => $expenseAccount,
            'source_account_id' => $sourceAccount,
            'acquisition_date' => now()->subMonth()->toDateString(),
            'placed_in_service_date' => now()->subMonth()->toDateString(),
            'acquisition_cost' => 12000000,
            'salvage_value' => 0,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation' => 0,
            'status' => 'draft',
        ];
    }
}
