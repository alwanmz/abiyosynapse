<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Onboarding\ChartOfAccountsInstaller;
use Illuminate\Database\Seeder;

/**
 * Installs the standard chart of accounts (with system-role and report
 * mappings) for the default demo company. Real companies get theirs from
 * the onboarding wizard.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(ChartOfAccountsInstaller::class)->installDefault($company);
        $company->forceFill(['onboarded_at' => $company->onboarded_at ?? now()])->save();
    }
}
