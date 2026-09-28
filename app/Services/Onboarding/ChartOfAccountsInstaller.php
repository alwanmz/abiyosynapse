<?php

namespace App\Services\Onboarding;

use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Models\Company;
use App\Models\ReportingAccountMapping;
use App\Support\DefaultChartOfAccounts;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsInstaller
{
    private const REPORTING_STANDARDS = ['sak_ep', 'psak_umum'];

    /**
     * Upserts the accounts (parents first), the system-role mapping and the
     * financial-report mapping for both reporting standards.
     *
     * @param  array<int, array<string, mixed>>  $accounts  normalized draft rows
     * @param  array<string, string>  $roleMapping  role => account code
     */
    public function install(Company $company, array $accounts, array $roleMapping): void
    {
        DB::transaction(function () use ($company, $accounts, $roleMapping): void {
            $idsByCode = [];

            foreach ($accounts as $row) {
                $account = Account::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'normal_balance' => $row['normal_balance'],
                        'is_postable' => $row['is_postable'],
                        'parent_id' => $row['parent_code'] ? ($idsByCode[$row['parent_code']] ?? null) : null,
                        'currency_code' => $company->currency,
                        'is_active' => true,
                    ],
                );
                $idsByCode[$row['code']] = $account->id;

                $reportCode = $row['report_line'] ? DefaultChartOfAccounts::reportCodeFor($row['report_line']) : null;
                if ($reportCode === null) {
                    continue;
                }

                foreach (self::REPORTING_STANDARDS as $standard) {
                    ReportingAccountMapping::withoutGlobalScopes()->updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'account_id' => $account->id,
                            'reporting_standard' => $standard,
                            'report_code' => $reportCode,
                        ],
                        [
                            'line_code' => $row['report_line'],
                            'display_order' => count($idsByCode),
                            'sign' => 1,
                            'is_active' => true,
                        ],
                    );
                }
            }

            foreach ($roleMapping as $role => $code) {
                AccountRoleMapping::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'role' => $role],
                    ['account_id' => $idsByCode[$code]],
                );
            }
        });
    }

    public function installDefault(Company $company): void
    {
        $this->install($company, DefaultChartOfAccounts::accounts(), DefaultChartOfAccounts::roleMapping());
    }
}
