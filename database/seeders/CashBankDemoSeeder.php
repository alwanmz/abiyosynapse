<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use App\Services\CashBank\BankAccountService;
use App\Services\CashBank\BankReconciliationService;
use App\Services\CashBank\CashTransactionService;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Fase 6 Kas Bank cycle: two bank accounts (a petty cash
 * account and a real bank account), a handful of manual cash transactions
 * against each (capital injection in, operational expenses out), and one
 * completed bank reconciliation for the bank account — so every Kas Bank
 * list/detail page has real non-empty example data instead of being empty
 * on a fresh install.
 *
 * Requires ChartOfAccountsSeeder to have run first (1.1.1/1.1.2/3.1/5.5
 * must already exist). Idempotent via a fixed bank account code check.
 */
class CashBankDemoSeeder extends Seeder
{
    private const PETTY_CASH_CODE = 'KAS-001';
    private const BANK_CODE = 'BCA-001';

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (BankAccount::where('company_id', $company->id)->where('code', self::PETTY_CASH_CODE)->exists()) {
            return;
        }

        $modalAccount = Account::where('company_id', $company->id)->where('code', '3.1')->first();
        $expenseAccount = Account::where('company_id', $company->id)->where('code', '5.5')->first();
        $user = User::first();

        if (! $modalAccount || ! $expenseAccount) {
            return;
        }

        $bankAccountService = app(BankAccountService::class);
        $txService = app(CashTransactionService::class);

        $pettyCash = $bankAccountService->create([
            'code' => self::PETTY_CASH_CODE,
            'name' => 'Kas Kecil',
            'type' => 'cash',
            'opening_balance' => 0,
        ]);

        $bca = $bankAccountService->create([
            'code' => self::BANK_CODE,
            'name' => 'BCA - 1234567890',
            'type' => 'bank',
            'bank_name' => 'Bank Central Asia',
            'account_number' => '1234567890',
            'opening_balance' => 0,
        ]);

        // Capital injection into the bank account.
        $txService->create([
            'bank_account_id' => $bca->id,
            'type' => 'in',
            'transaction_date' => now()->subDays(10)->toDateString(),
            'counter_account_id' => $modalAccount->id,
            'amount' => 50000000,
            'description' => 'Setoran modal awal pemilik',
        ], $user);

        // Transfer a slice into petty cash for day-to-day operational spend.
        $txService->create([
            'bank_account_id' => $pettyCash->id,
            'type' => 'in',
            'transaction_date' => now()->subDays(9)->toDateString(),
            'counter_account_id' => $modalAccount->id,
            'amount' => 2000000,
            'description' => 'Pengisian kas kecil dari modal',
        ], $user);

        // A couple of operational expenses paid from the bank account.
        $bankExpense1 = $txService->create([
            'bank_account_id' => $bca->id,
            'type' => 'out',
            'transaction_date' => now()->subDays(7)->toDateString(),
            'counter_account_id' => $expenseAccount->id,
            'amount' => 1500000,
            'description' => 'Bayar tagihan listrik pabrik',
        ], $user);

        $bankExpense2 = $txService->create([
            'bank_account_id' => $bca->id,
            'type' => 'out',
            'transaction_date' => now()->subDays(3)->toDateString(),
            'counter_account_id' => $expenseAccount->id,
            'amount' => 800000,
            'description' => 'Bayar internet & telepon kantor',
        ], $user);

        // One petty-cash expense too.
        $txService->create([
            'bank_account_id' => $pettyCash->id,
            'type' => 'out',
            'transaction_date' => now()->subDays(2)->toDateString(),
            'counter_account_id' => $expenseAccount->id,
            'amount' => 350000,
            'description' => 'Beli alat tulis kantor',
        ], $user);

        // A completed reconciliation for the bank account covering the
        // two expenses above (both cleared), demonstrating the full
        // draft -> mark cleared -> complete flow.
        $reconciliationService = app(BankReconciliationService::class);
        $reconciliation = $reconciliationService->create([
            'bank_account_id' => $bca->id,
            'statement_date' => now()->subDays(1)->toDateString(),
            'statement_balance' => 47700000, // 50,000,000 - 1,500,000 - 800,000
        ], $user);

        $lineUpdates = $reconciliation->lines
            ->whereIn('cash_transaction_id', [$bankExpense1->id, $bankExpense2->id])
            ->map(fn ($line) => ['id' => $line->id, 'is_cleared' => true])
            ->values()
            ->all();

        $reconciliation = $reconciliationService->updateLines($reconciliation, $lineUpdates);
        $reconciliationService->complete($reconciliation);
    }
}
