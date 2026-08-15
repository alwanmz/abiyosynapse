<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['code' => 'default'],
            [
                'name' => 'PT Abiyo Synapse Contoh',
                'legal_name' => 'PT Abiyo Synapse Contoh',
                'currency' => 'IDR',
                'fiscal_year_start_month' => 1,
                'is_active' => true,
            ]
        );
    }
}
