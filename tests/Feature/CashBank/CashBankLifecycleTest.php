<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\CashBank\BankAccountService;
use App\Services\CashBank\BankReconciliationService;
use App\Services\CashBank\CashTransactionService;
use App\Services\CurrentCompany;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Fase 6 lifecycle: BankAccount creation (converting 1.1.1/1.1.2 into
 * header accounts and creating a leaf child), manual cash-in/cash-out
 * transactions posting to the correct sides of the entry, and a Bank
 * Reconciliation session snapshotting + clearing transactions.
 */
beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $this->user = User::factory()->create();
});

test('creating the first bank account converts 1.1.2 into a header account and creates a leaf child', function () {
    $service = app(BankAccountService::class);

    $bca = $service->create([
        'code' => 'BCA-001',
        'name' => 'BCA - 123456789',
        'type' => 'bank',
        'bank_name' => 'BCA',
        'account_number' => '123456789',
        'opening_balance' => 5000000,
    ]);

    $parent = Account::where('company_id', $this->company->id)->where('code', '1.1.2')->first();
    expect($parent->is_postable)->toBeFalse();

    $child = Account::find($bca->account_id);
    expect($child->code)->toBe('1.1.2.1');
    expect($child->is_postable)->toBeTrue();
    expect($child->parent_id)->toBe($parent->id);
    expect($child->type)->toBe('asset');
    expect($child->normal_balance)->toBe('debit');
});

test('creating a second bank account of the same type does not re-flip the already-converted parent and gets the next code', function () {
    $service = app(BankAccountService::class);

    $service->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $mandiri = $service->create(['code' => 'MANDIRI-001', 'name' => 'Mandiri', 'type' => 'bank']);

    $child = Account::find($mandiri->account_id);
    expect($child->code)->toBe('1.1.2.2');

    $parent = Account::where('company_id', $this->company->id)->where('code', '1.1.2')->first();
    expect($parent->is_postable)->toBeFalse();
});

test('a cash-type bank account converts 1.1.1 independently of 1.1.2', function () {
    $service = app(BankAccountService::class);

    $pettyCash = $service->create(['code' => 'KAS-001', 'name' => 'Kas Kecil', 'type' => 'cash']);

    $child = Account::find($pettyCash->account_id);
    expect($child->code)->toBe('1.1.1.1');

    $kasParent = Account::where('company_id', $this->company->id)->where('code', '1.1.1')->first();
    $bankParent = Account::where('company_id', $this->company->id)->where('code', '1.1.2')->first();
    expect($kasParent->is_postable)->toBeFalse();
    expect($bankParent->is_postable)->toBeTrue(); // untouched — no bank-type account created yet
});

test('converting a parent account that already has direct postings throws instead of silently orphaning them', function () {
    $kasAccount = Account::where('company_id', $this->company->id)->where('code', '1.1.1')->first();
    $modalAccount = Account::where('company_id', $this->company->id)->where('code', '3.1')->first();

    app(\App\Services\Accounting\JournalPostingService::class)->post(
        description: 'Setoran modal awal langsung ke akun Kas (skenario pra-Fase 6)',
        lines: [
            ['account_id' => $kasAccount->id, 'debit' => 1000000],
            ['account_id' => $modalAccount->id, 'credit' => 1000000],
        ],
    );

    app(BankAccountService::class)->create(['code' => 'KAS-001', 'name' => 'Kas Kecil', 'type' => 'cash']);
})->throws(RuntimeException::class);

test('a cash-in transaction debits the bank account and credits the counter account', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $modalAccount = Account::where('company_id', $this->company->id)->where('code', '3.1')->first();

    $transaction = app(CashTransactionService::class)->create([
        'bank_account_id' => $bankAccount->id,
        'type' => 'in',
        'transaction_date' => now()->toDateString(),
        'counter_account_id' => $modalAccount->id,
        'amount' => 10000000,
        'description' => 'Setoran modal awal',
    ], $this->user);

    expect($transaction->type)->toBe('in');

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\CashTransaction::class)
        ->where('sourceable_id', $transaction->id)
        ->with('lines.account')
        ->first();

    $bankLine = $journal->lines->firstWhere('account_id', $bankAccount->account_id);
    $modalLine = $journal->lines->firstWhere('account_id', $modalAccount->id);

    expect((float) $bankLine->debit)->toBe(10000000.0);
    expect((float) $modalLine->credit)->toBe(10000000.0);
});

test('a cash-out transaction credits the bank account and debits the counter account', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();

    $transaction = app(CashTransactionService::class)->create([
        'bank_account_id' => $bankAccount->id,
        'type' => 'out',
        'transaction_date' => now()->toDateString(),
        'counter_account_id' => $expenseAccount->id,
        'amount' => 500000,
        'description' => 'Bayar listrik kantor',
    ], $this->user);

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\CashTransaction::class)
        ->where('sourceable_id', $transaction->id)
        ->with('lines.account')
        ->first();

    $bankLine = $journal->lines->firstWhere('account_id', $bankAccount->account_id);
    $expenseLine = $journal->lines->firstWhere('account_id', $expenseAccount->id);

    expect((float) $bankLine->credit)->toBe(500000.0);
    expect((float) $expenseLine->debit)->toBe(500000.0);
});

test('a zero or negative amount throws', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();

    app(CashTransactionService::class)->create([
        'bank_account_id' => $bankAccount->id,
        'type' => 'out',
        'transaction_date' => now()->toDateString(),
        'counter_account_id' => $expenseAccount->id,
        'amount' => 0,
        'description' => 'Invalid',
    ], $this->user);
})->throws(RuntimeException::class);

test('transaction numbers are sequential and gap-safe', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();
    $service = app(CashTransactionService::class);

    $t1 = $service->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => now()->toDateString(), 'counter_account_id' => $expenseAccount->id, 'amount' => 1000, 'description' => 'A'], $this->user);
    $t2 = $service->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => now()->toDateString(), 'counter_account_id' => $expenseAccount->id, 'amount' => 1000, 'description' => 'B'], $this->user);

    expect($t1->number)->not->toBe($t2->number);

    // Simulate a gap: delete the first transaction's journal entry and the transaction itself.
    \App\Models\JournalEntry::where('sourceable_type', \App\Models\CashTransaction::class)->where('sourceable_id', $t1->id)->first()->lines()->delete();
    \App\Models\JournalEntry::where('sourceable_type', \App\Models\CashTransaction::class)->where('sourceable_id', $t1->id)->delete();
    $t1->delete();

    $t3 = $service->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => now()->toDateString(), 'counter_account_id' => $expenseAccount->id, 'amount' => 1000, 'description' => 'C'], $this->user);

    // Must not collide with t2's number despite the gap left by deleting t1.
    expect($t3->number)->not->toBe($t2->number);
});

test('a bank reconciliation snapshots not-yet-reconciled transactions up to the statement date', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();
    $txService = app(CashTransactionService::class);

    $t1 = $txService->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-08-01', 'counter_account_id' => $expenseAccount->id, 'amount' => 100000, 'description' => 'A'], $this->user);
    $t2 = $txService->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-08-15', 'counter_account_id' => $expenseAccount->id, 'amount' => 200000, 'description' => 'B'], $this->user);
    // Future transaction, after the statement date — should not be included.
    $txService->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-09-01', 'counter_account_id' => $expenseAccount->id, 'amount' => 300000, 'description' => 'C'], $this->user);

    $reconciliation = app(BankReconciliationService::class)->create([
        'bank_account_id' => $bankAccount->id,
        'statement_date' => '2026-08-31',
        'statement_balance' => -300000,
    ], $this->user);

    expect($reconciliation->lines)->toHaveCount(2);
    expect($reconciliation->lines->pluck('cash_transaction_id')->sort()->values()->all())
        ->toBe(collect([$t1->id, $t2->id])->sort()->values()->all());
    expect($reconciliation->lines->every(fn ($line) => $line->is_cleared === false))->toBeTrue();
});

test('marking lines cleared and completing a reconciliation works, and a completed one cannot be edited', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();
    app(CashTransactionService::class)->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-08-01', 'counter_account_id' => $expenseAccount->id, 'amount' => 100000, 'description' => 'A'], $this->user);

    $service = app(BankReconciliationService::class);
    $reconciliation = $service->create(['bank_account_id' => $bankAccount->id, 'statement_date' => '2026-08-31', 'statement_balance' => -100000], $this->user);

    $line = $reconciliation->lines->first();
    $reconciliation = $service->updateLines($reconciliation, [['id' => $line->id, 'is_cleared' => true]]);
    expect($reconciliation->lines->first()->is_cleared)->toBeTrue();

    $reconciliation = $service->complete($reconciliation);
    expect($reconciliation->status)->toBe('completed');
    expect($reconciliation->completed_at)->not->toBeNull();

    $service->updateLines($reconciliation, [['id' => $line->id, 'is_cleared' => false]]);
})->throws(RuntimeException::class);

test('a second reconciliation does not re-include transactions already captured by a prior reconciliation', function () {
    $bankAccount = app(BankAccountService::class)->create(['code' => 'BCA-001', 'name' => 'BCA', 'type' => 'bank']);
    $expenseAccount = Account::where('company_id', $this->company->id)->where('code', '5.5')->first();
    app(CashTransactionService::class)->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-08-01', 'counter_account_id' => $expenseAccount->id, 'amount' => 100000, 'description' => 'A'], $this->user);

    $service = app(BankReconciliationService::class);
    $first = $service->create(['bank_account_id' => $bankAccount->id, 'statement_date' => '2026-08-31', 'statement_balance' => -100000], $this->user);
    expect($first->lines)->toHaveCount(1);

    app(CashTransactionService::class)->create(['bank_account_id' => $bankAccount->id, 'type' => 'out', 'transaction_date' => '2026-09-05', 'counter_account_id' => $expenseAccount->id, 'amount' => 50000, 'description' => 'B'], $this->user);

    $second = $service->create(['bank_account_id' => $bankAccount->id, 'statement_date' => '2026-09-30', 'statement_balance' => -150000], $this->user);
    expect($second->lines)->toHaveCount(1); // only the new one, not the one already in $first
});
