<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\ReportingAccountMapping;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

class ReportingMappingSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private const MAP = [
        'balance_sheet' => [
            '1.1.1' => 'cash', '1.1.2' => 'bank', '1.1.3' => 'trade_receivables',
            '1.1.4' => 'raw_material_inventory', '1.1.5' => 'work_in_progress',
            '1.1.6' => 'finished_goods_inventory', '1.1.7' => 'input_tax',
            '1.2.1' => 'property_plant_equipment', '1.2.2' => 'accumulated_depreciation',
            '2.1.1' => 'trade_payables', '2.1.2' => 'grni', '2.1.3' => 'output_tax',
            '3.1' => 'paid_in_capital', '3.2' => 'retained_earnings',
        ],
        'profit_loss' => [
            '4.1' => 'revenue', '4.2' => 'foreign_exchange_gain',
            '5.1' => 'cost_of_sales', '5.2' => 'direct_labour', '5.3' => 'factory_overhead',
            '5.4' => 'scrap_expense', '5.5' => 'operating_expenses',
            '5.6' => 'foreign_exchange_loss', '5.7' => 'revaluation_loss',
        ],
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();
        if (! $company) {
            return;
        }

        $context = app(CurrentCompany::class);
        $previousCompany = $context->get();
        $context->set($company);

        try {
            foreach (['sak_ep', 'psak_umum'] as $standard) {
                foreach (self::MAP as $reportCode => $accountMap) {
                    foreach (Account::where('company_id', $company->id)->where('is_postable', true)->get() as $account) {
                        $accountCode = $account->code;
                        $lineCode = $accountMap[$accountCode] ?? collect($accountMap)
                            ->filter(fn (string $mappedLine, string $mappedCode): bool => str_starts_with($accountCode, $mappedCode . '.'))
                            ->sortByDesc(fn (string $mappedLine, string $mappedCode): int => strlen($mappedCode))
                            ->first();

                        if (! $lineCode) {
                            continue;
                        }

                        ReportingAccountMapping::updateOrCreate(
                            [
                                'company_id' => $company->id,
                                'account_id' => $account->id,
                                'reporting_standard' => $standard,
                                'report_code' => $reportCode,
                            ],
                            [
                                'line_code' => $lineCode,
                                'display_order' => (int) str_replace('.', '', $accountCode),
                                'sign' => 1,
                                'is_active' => true,
                            ],
                        );
                    }
                }
            }
        } finally {
            $context->set($previousCompany);
        }
    }
}
