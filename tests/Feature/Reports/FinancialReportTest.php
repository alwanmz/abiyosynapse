<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Reports\FinancialReportService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);

    $this->cash = Account::factory()->for($this->company)->create([
        'code' => '1.1.1',
        'name' => 'Kas',
        'type' => 'asset',
        'normal_balance' => 'debit',
    ]);
    $this->revenue = Account::factory()->for($this->company)->create([
        'code' => '4.1',
        'name' => 'Pendapatan Penjualan',
        'type' => 'revenue',
        'normal_balance' => 'credit',
    ]);
    $this->expense = Account::factory()->for($this->company)->create([
        'code' => '5.5',
        'name' => 'Beban Operasional',
        'type' => 'expense',
        'normal_balance' => 'debit',
    ]);

    $posting = app(JournalPostingService::class);
    $posting->post('Demo sale', [
        ['account_id' => $this->cash->id, 'debit' => 1000],
        ['account_id' => $this->revenue->id, 'credit' => 1000],
    ], entryDate: '2026-01-05');
    $posting->post('Demo expense', [
        ['account_id' => $this->expense->id, 'debit' => 300],
        ['account_id' => $this->cash->id, 'credit' => 300],
    ], entryDate: '2026-01-10');
});

test('trial balance reports posted account balances and remains balanced', function () {
    $report = app(FinancialReportService::class)->build('trial_balance', '2026-01-01', '2026-01-31');
    $trialBalance = $report['trialBalance'];

    expect($trialBalance['totals']['debit'])->toBe(1000.0)
        ->and($trialBalance['totals']['credit'])->toBe(1000.0)
        ->and(collect($trialBalance['rows'])->firstWhere('code', '1.1.1')['debit'])->toBe(700.0);
});

test('general ledger calculates opening, running, and closing balance for one account', function () {
    $report = app(FinancialReportService::class)->build('general_ledger', '2026-01-06', '2026-01-31', $this->cash->id);
    $ledger = $report['generalLedger'];

    expect($ledger['account']['code'])->toBe('1.1.1')
        ->and($ledger['opening_balance'])->toBe(1000.0)
        ->and($ledger['lines'])->toHaveCount(1)
        ->and($ledger['lines'][0]['balance'])->toBe(700.0)
        ->and($ledger['closing_balance'])->toBe(700.0);
});

test('profit and loss uses only activity inside the selected period', function () {
    $report = app(FinancialReportService::class)->build('profit_loss', '2026-01-01', '2026-01-31');
    $profitLoss = $report['profitLoss'];

    expect($profitLoss['totals']['revenue'])->toBe(1000.0)
        ->and($profitLoss['totals']['expenses'])->toBe(300.0)
        ->and($profitLoss['totals']['net_profit'])->toBe(700.0);
});

test('balance sheet includes current earnings and reconciles to zero difference', function () {
    $report = app(FinancialReportService::class)->build('balance_sheet', '2026-01-01', '2026-01-31');
    $balanceSheet = $report['balanceSheet'];

    expect($balanceSheet['totals']['assets'])->toBe(700.0)
        ->and($balanceSheet['current_earnings'])->toBe(700.0)
        ->and($balanceSheet['totals']['difference'])->toBe(0.0);
});

test('financial reports never include another company journals', function () {
    $otherCompany = Company::factory()->create();
    $otherCash = Account::factory()->for($otherCompany)->create([
        'type' => 'asset',
        'normal_balance' => 'debit',
    ]);
    $otherRevenue = Account::factory()->for($otherCompany)->create([
        'type' => 'revenue',
        'normal_balance' => 'credit',
    ]);

    app(CurrentCompany::class)->set($otherCompany);
    app(JournalPostingService::class)->post('Other company sale', [
        ['account_id' => $otherCash->id, 'debit' => 9999],
        ['account_id' => $otherRevenue->id, 'credit' => 9999],
    ], entryDate: '2026-01-15');

    app(CurrentCompany::class)->set($this->company);
    $report = app(FinancialReportService::class)->build('trial_balance', '2026-01-01', '2026-01-31');

    expect($report['trialBalance']['totals']['debit'])->toBe(1000.0)
        ->and(collect($report['trialBalance']['rows'])->pluck('code'))->not->toContain($otherCash->code);
});

test('financial report page renders with the selected report data', function () {
    $user = User::factory()->create();
    $user->companies()->syncWithoutDetaching([$this->company->id => [
        'role_id' => $user->currentCompanyMembership()?->role_id,
        'is_default' => true,
        'joined_at' => now(),
    ]]);
    $user->forceFill(['current_company_id' => $this->company->id])->save();
    app(CurrentCompany::class)->set($this->company);

    $response = $this->actingAs($user)->get('/reports/gl?report=profit_loss&from_date=2026-01-01&to_date=2026-01-31');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/gl/page')
        ->where('report', 'profit_loss')
        ->where('profitLoss.totals.net_profit', 700));
});
