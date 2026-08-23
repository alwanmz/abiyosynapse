<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\FixedAssetDepreciation> */
class FixedAssetDepreciationFactory extends Factory
{
    public function definition(): array
    {
        $asset = FixedAsset::factory();

        return [
            'company_id' => Company::factory(),
            'fixed_asset_id' => $asset,
            'period_date' => now()->endOfMonth()->toDateString(),
            'amount' => 1000,
            'accumulated_depreciation' => 1000,
            'notes' => null,
        ];
    }
}
