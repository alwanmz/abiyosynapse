<?php

use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Models\CompanyCurrency;
use App\Models\User;
use App\Services\Accounting\AccountRoleResolver;
use App\Services\CashBank\BankAccountService;
use App\Services\CurrentCompany;
use App\Services\Onboarding\ChartOfAccountsInstaller;
use App\Services\Onboarding\CoaDraftValidator;
use App\Support\AccountRole;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = $this->user->currentCompany;
    app(CurrentCompany::class)->set($this->company);
    CompanyCurrency::withoutGlobalScopes()->firstOrCreate(
        ['company_id' => $this->company->id, 'currency_code' => 'IDR'],
        ['is_active' => true, 'is_base' => true],
    );

    // A chart with Mekari-style codes, none of which match the old fixed codes.
    $rows = [];
    foreach ([
        ['1-10000', 'Kas & Bank', 'asset', false, null],
        ['1-10001', 'Kas Toko', 'asset', true, '1-10000'],
        ['1-10002', 'Rekening Bank', 'asset', false, '1-10000'],
        ['1-10100', 'Piutang Dagang', 'asset', true, null],
        ['1-10200', 'Persediaan Barang', 'asset', true, null],
        ['1-10300', 'PPN Masukan', 'asset', true, null],
        ['2-20100', 'Hutang Dagang', 'liability', true, null],
        ['2-20200', 'Hutang Belum Ditagih', 'liability', true, null],
        ['2-20300', 'PPN Keluaran', 'liability', true, null],
        ['3-30000', 'Modal', 'equity', true, null],
        ['4-40000', 'Penjualan', 'revenue', true, null],
        ['7-70000', 'Pendapatan Lain', 'revenue', true, null],
        ['5-50000', 'HPP', 'expense', true, null],
        ['8-80000', 'Beban Lain', 'expense', true, null],
    ] as [$code, $name, $type, $postable, $parent]) {
        $rows[] = ['code' => $code, 'name' => $name, 'type' => $type, 'is_postable' => $postable, 'parent_code' => $parent];
    }

    $validator = app(CoaDraftValidator::class);
    $accounts = $validator->normalize($rows)['accounts'];
    $mapping = [
        'cash_parent' => '1-10001', 'bank_parent' => '1-10002', 'accounts_receivable' => '1-10100',
        'raw_material_inventory' => '1-10200', 'wip_inventory' => '1-10200', 'finished_goods_inventory' => '1-10200',
        'input_tax' => '1-10300', 'accounts_payable' => '2-20100', 'grni' => '2-20200', 'output_tax' => '2-20300',
        'sales_revenue' => '4-40000', 'asset_disposal_gain' => '7-70000', 'fx_gain' => '7-70000', 'cogs' => '5-50000',
        'scrap_expense' => '8-80000', 'fx_realized_loss' => '8-80000', 'fx_unrealized_loss' => '8-80000',
    ];
    expect($validator->mappingErrors($accounts, $mapping))->toBe([]);

    app(ChartOfAccountsInstaller::class)->install($this->company, $accounts, $mapping);
});

test('the resolver returns the mapped account regardless of its code', function () {
    $resolver = app(AccountRoleResolver::class);

    expect($resolver->account(AccountRole::AccountsReceivable)->code)->toBe('1-10100')
        ->and($resolver->account(AccountRole::SalesRevenue)->name)->toBe('Penjualan')
        ->and($resolver->account(AccountRole::FxUnrealizedLoss)->code)->toBe('8-80000');
});

test('a missing mapping fails with an actionable message', function () {
    AccountRoleMapping::where('role', AccountRole::Cogs->value)->delete();

    expect(fn () => app(AccountRoleResolver::class)->id(AccountRole::Cogs))
        ->toThrow(RuntimeException::class, 'Harga Pokok Penjualan');
});

test('new bank accounts are created under the mapped bank parent', function () {
    $bank = app(BankAccountService::class)->create(['code' => 'BCA', 'name' => 'BCA Operasional', 'type' => 'bank']);
    $cash = app(BankAccountService::class)->create(['code' => 'KAS-2', 'name' => 'Kas Kecil', 'type' => 'cash']);

    expect($bank->account->code)->toBe('1-10002.1')
        ->and($cash->account->code)->toBe('1-10001.1')
        ->and(Account::where('code', '1-10001')->first()->is_postable)->toBeFalse();
});

test('the core-role mapping can be changed from the accounts page with type checks', function () {
    $revenue = Account::where('code', '7-70000')->first();
    $expense = Account::where('code', '8-80000')->first();
    $mappings = AccountRoleMapping::pluck('account_id', 'role')->all();

    $this->actingAs($this->user)
        ->put('/master/account-roles', ['mappings' => [...$mappings, 'sales_revenue' => $expense->id]])
        ->assertSessionHasErrors('mappings.sales_revenue');

    $this->actingAs($this->user)
        ->put('/master/account-roles', ['mappings' => [...$mappings, 'sales_revenue' => $revenue->id]])
        ->assertSessionHasNoErrors();

    expect(app(AccountRoleResolver::class)->id(AccountRole::SalesRevenue))->toBe($revenue->id);
});

test('a mapped account cannot be deleted or turned into an unsuitable type', function () {
    $receivable = Account::where('code', '1-10100')->first();

    $this->actingAs($this->user)->delete("/master/accounts/{$receivable->id}")->assertSessionHas('error');
    $this->actingAs($this->user)->put("/master/accounts/{$receivable->id}", [
        'code' => '1-10100', 'name' => 'Piutang', 'type' => 'expense', 'normal_balance' => 'debit', 'is_postable' => true,
    ])->assertSessionHasErrors('type');

    expect(Account::find($receivable->id)->type)->toBe('asset');
});
