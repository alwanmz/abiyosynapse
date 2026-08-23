<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CurrencyRate;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\MoneyConversionService;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['currency' => 'IDR']);
    app(CurrentCompany::class)->set($this->company);

    foreach (['IDR', 'USD', 'JPY', 'CNY'] as $code) {
        CompanyCurrency::create([
            'company_id' => $this->company->id,
            'currency_code' => $code,
            'is_active' => true,
            'is_base' => $code === 'IDR',
        ]);
    }

    foreach ([['USD', '16000'], ['JPY', '110'], ['CNY', '2200']] as [$from, $rate]) {
        CurrencyRate::create([
            'company_id' => $this->company->id,
            'from_currency_code' => $from,
            'to_currency_code' => 'IDR',
            'effective_date' => now()->toDateString(),
            'rate' => $rate,
            'rate_type' => 'general',
            'source' => 'manual',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
});

test('official currencies convert to the company base currency', function () {
    $money = app(MoneyConversionService::class);

    expect($money->toBase('1000', 'USD'))->toBe('16000000.000000')
        ->and($money->toBase('100000', 'JPY'))->toBe('11000000.000000')
        ->and($money->toBase('1000', 'CNY'))->toBe('2200000.000000');
});

test('a reverse rate is resolved when only the base-to-foreign direction exists', function () {
    CurrencyRate::where('from_currency_code', 'USD')->delete();
    CurrencyRate::create([
        'company_id' => $this->company->id,
        'from_currency_code' => 'IDR',
        'to_currency_code' => 'USD',
        'effective_date' => now()->toDateString(),
        'rate' => '0.0000625',
        'rate_type' => 'general',
        'source' => 'manual',
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    $resolution = app(CurrencyRateService::class)->resolve('USD', 'IDR');

    expect($resolution['reciprocal'])->toBeTrue()
        ->and((float) $resolution['rate'])->toBe(16000.0);
});

test('a rate older than seven days cannot be used for posting', function () {
    CurrencyRate::where('from_currency_code', 'USD')->update(['effective_date' => now()->subDays(8)->toDateString()]);

    app(CurrencyRateService::class)->resolve('USD', 'IDR');
})->throws(RuntimeException::class, 'older than');

test('foreign journal lines preserve transaction amount and exact base amount', function () {
    $cash = Account::factory()->for($this->company)->create(['type' => 'asset', 'normal_balance' => 'debit']);
    $revenue = Account::factory()->for($this->company)->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    $entry = app(JournalPostingService::class)->post(
        description: 'USD acceptance test',
        lines: [
            ['account_id' => $cash->id, 'debit_base' => '16000000.125000', 'amount_currency' => '1000.0078125', 'currency_code' => 'USD', 'exchange_rate' => '16000.125'],
            ['account_id' => $revenue->id, 'credit_base' => '16000000.125000', 'amount_currency' => '-1000.0078125', 'currency_code' => 'USD', 'exchange_rate' => '16000.125'],
        ],
    );

    expect((string) $entry->total_debit_base)->toBe('16000000.125000')
        ->and((string) $entry->lines->first()->amount_currency)->toBe('1000.007813')
        ->and((string) $entry->lines->first()->debit_base)->toBe('16000000.125000');
});
