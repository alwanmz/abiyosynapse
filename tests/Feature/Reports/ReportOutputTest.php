<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\ReportingAccountMapping;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportSnapshotService;

beforeEach(function (): void {
    $this->company = Company::factory()->create([
        'currency' => 'IDR',
        'reporting_standard' => 'sak_ep',
        'comparative_period_enabled' => true,
    ]);
    app(CurrentCompany::class)->set($this->company);

    $this->cash = Account::factory()->for($this->company)->create([
        'code' => '1.1.1', 'type' => 'asset', 'normal_balance' => 'debit',
    ]);
    $this->revenue = Account::factory()->for($this->company)->create([
        'code' => '4.1', 'type' => 'revenue', 'normal_balance' => 'credit',
    ]);

    app(JournalPostingService::class)->post('Foreign currency demo', [
        ['account_id' => $this->cash->id, 'debit' => 16000000],
        ['account_id' => $this->revenue->id, 'credit' => 16000000],
    ], entryDate: '2026-08-23');

    CurrencyRate::create([
        'company_id' => $this->company->id,
        'from_currency_code' => 'USD',
        'to_currency_code' => 'IDR',
        'effective_date' => '2026-08-23',
        'rate' => '16000',
        'rate_type' => 'general',
        'source' => 'manual',
        'status' => 'approved',
        'approved_at' => now(),
    ]);
});

test('report snapshots translate base values into the selected presentation currency', function (): void {
    $snapshot = app(ReportSnapshotService::class)->build(
        'balance_sheet', '2026-01-01', '2026-08-23', null, 'USD', 'en', false,
    );

    expect($snapshot['meta']['presentation_currency'])->toBe('USD')
        ->and($snapshot['data']['balanceSheet']['totals']['assets'])->toBe(1000.0)
        ->and($snapshot['warnings'])->toBeArray();
});

test('financial statement package does not translate child snapshots twice', function (): void {
    $snapshot = app(ReportSnapshotService::class)->build(
        'financial_statements', '2026-01-01', '2026-08-23', null, 'USD', 'en', false,
    );

    expect($snapshot['data']['package']['balance_sheet']['data']['balanceSheet']['totals']['assets'])->toBe(1000.0);
});

test('financial statement rows carry the selected framework account mapping', function (): void {
    ReportingAccountMapping::create([
        'company_id' => $this->company->id,
        'account_id' => $this->cash->id,
        'reporting_standard' => 'sak_ep',
        'report_code' => 'balance_sheet',
        'line_code' => 'cash_and_cash_equivalents',
        'display_order' => 10,
        'sign' => 1,
        'is_active' => true,
    ]);

    $snapshot = app(ReportSnapshotService::class)->build(
        'balance_sheet', '2026-01-01', '2026-08-23', null, 'IDR', 'id', false, 'sak_ep',
    );

    expect(collect($snapshot['data']['balanceSheet']['assets'])->firstWhere('id', $this->cash->id)['report_line_code'])
        ->toBe('cash_and_cash_equivalents');
});

test('report export records an auditable PDF run', function (): void {
    $response = app(ReportExportService::class)->pdf([
        'report' => 'balance_sheet',
        'from_date' => '2026-01-01',
        'to_date' => '2026-08-23',
        'presentation_currency' => 'USD',
        'language' => 'en',
        'comparative' => '0',
    ]);

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
    $this->assertDatabaseHas('report_export_runs', [
        'company_id' => $this->company->id,
        'report_code' => 'balance_sheet',
        'format' => 'pdf',
    ]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'report_exported']);
});

test('report export workbook contains numeric report sheets and exchange rates', function (): void {
    $response = app(ReportExportService::class)->xlsx([
        'report' => 'profit_loss',
        'from_date' => '2026-01-01',
        'to_date' => '2026-08-23',
        'presentation_currency' => 'USD',
        'language' => 'en',
        'comparative' => '0',
    ]);

    ob_start();
    $response->sendContent();
    $bytes = ob_get_clean();
    $path = storage_path('app/report-output-test.xlsx');
    file_put_contents($path, $bytes);
    $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

    expect($workbook->getSheetNames())->toContain('Cover')
        ->toContain('Exchange Rates')
        ->toContain('Warnings');
    $this->assertDatabaseHas('report_export_runs', [
        'company_id' => $this->company->id,
        'report_code' => 'profit_loss',
        'format' => 'xlsx',
    ]);
});
