<?php

use App\Models\Account;
use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Services\CurrentCompany;
use App\Services\ExchangeRevaluationService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->firstOrFail();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    CompanyCurrency::updateOrCreate(
        ['company_id' => $this->company->id, 'currency_code' => 'USD'],
        ['is_active' => true, 'is_base' => false],
    );

    CurrencyRate::withoutGlobalScopes()->updateOrCreate(
        [
            'company_id' => $this->company->id,
            'from_currency_code' => 'USD',
            'to_currency_code' => 'IDR',
            'effective_date' => now()->toDateString(),
            'rate_type' => 'general',
        ],
        ['rate' => '17000', 'source' => 'manual', 'status' => 'approved', 'approved_at' => now()],
    );
});

test('foreign bank revaluation is idempotent and reversible', function () {
    $bankLedger = Account::factory()->for($this->company)->create([
        'code' => '1.1.2.99',
        'name' => 'USD Demo Bank',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);
    $counterLedger = Account::factory()->for($this->company)->create([
        'code' => '5.9.99',
        'name' => 'USD Demo Counter',
        'type' => 'expense',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);

    $bank = BankAccount::create([
        'company_id' => $this->company->id,
        'code' => 'USD-DEMO',
        'name' => 'USD Demo Bank',
        'type' => 'bank',
        'account_id' => $bankLedger->id,
        'currency_code' => 'USD',
        'opening_balance' => '1000',
        'opening_balance_base' => '16000000',
        'is_active' => true,
    ]);

    CashTransaction::create([
        'company_id' => $this->company->id,
        'number' => 'CT-USD-001',
        'bank_account_id' => $bank->id,
        'counter_account_id' => $counterLedger->id,
        'type' => 'in',
        'transaction_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'exchange_rate' => '16000',
        'amount' => '100',
        'amount_base' => '1600000',
        'description' => 'USD demo movement',
    ]);

    $service = app(ExchangeRevaluationService::class);
    $run = $service->run(now()->toDateString(), 'USD');

    expect($run->status)->toBe('completed')
        ->and((float) $run->total_adjustment_base)->toBe(1100000.0)
        ->and($run->journal_entry_id)->not->toBeNull()
        ->and($service->run(now()->toDateString(), 'USD')->id)->toBe($run->id);

    $reversed = $service->reverse($run);

    expect($reversed->status)->toBe('reversed')
        ->and($reversed->reversal_journal_entry_id)->not->toBeNull();
});

test('foreign bank revaluation includes AR receipts in the bank balance', function () {
    $bankLedger = Account::factory()->for($this->company)->create([
        'code' => '1.1.2.98',
        'name' => 'USD AR Bank',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_postable' => true,
    ]);
    $customer = Customer::factory()->for($this->company)->create();

    $bank = BankAccount::create([
        'company_id' => $this->company->id,
        'code' => 'USD-AR-DEMO',
        'name' => 'USD AR Demo Bank',
        'type' => 'bank',
        'account_id' => $bankLedger->id,
        'currency_code' => 'USD',
        'opening_balance' => '1000',
        'opening_balance_base' => '16000000',
        'is_active' => true,
    ]);

    ArReceipt::create([
        'company_id' => $this->company->id,
        'number' => 'AR-USD-001',
        'customer_id' => $customer->id,
        'bank_account_id' => $bank->id,
        'receipt_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'exchange_rate' => '16000',
        'amount' => '100',
        'amount_base' => '1600000',
        'reference' => 'AR-USD-001',
    ]);

    $run = app(ExchangeRevaluationService::class)->run(now()->toDateString(), 'USD');

    // (1,000 + 100) * 17,000 - (16,000,000 + 1,600,000) = 1,100,000.
    expect((float) $run->total_adjustment_base)->toBe(1100000.0);
});
