<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    private const CURRENCIES = [
        ['code' => 'IDR', 'numeric_code' => 360, 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'minor_unit' => 2],
        ['code' => 'USD', 'numeric_code' => 840, 'name' => 'United States Dollar', 'symbol' => '$', 'minor_unit' => 2],
        ['code' => 'JPY', 'numeric_code' => 392, 'name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit' => 0],
        ['code' => 'CNY', 'numeric_code' => 156, 'name' => 'Chinese Yuan Renminbi', 'symbol' => '¥', 'minor_unit' => 2],
    ];

    public function run(): void
    {
        foreach (self::CURRENCIES as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], [...$currency, 'is_active' => true]);
        }

        Company::query()->each(function (Company $company): void {
            CompanyCurrency::updateOrCreate(
                ['company_id' => $company->id, 'currency_code' => $company->currency],
                ['is_active' => true, 'is_base' => true],
            );
        });
    }
}
