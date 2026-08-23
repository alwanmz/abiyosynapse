<?php

namespace App\Services\CashBank;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CompanyCurrency;
use App\Models\JournalLine;
use App\Services\CurrentCompany;
use App\Services\MoneyConversionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates BankAccount records, each backed by its own leaf GL account
 * (a child of 1.1.1 Kas for type=cash, or 1.1.2 Bank for type=bank) so
 * per-bank-account balances are directly readable from the chart of
 * accounts rather than every bank account sharing one undifferentiated
 * leaf. 1.1.1/1.1.2 start out postable (Fase 1's ChartOfAccountsSeeder
 * seeds them that way, matching every other leaf account) — the first
 * BankAccount of a given type converts its parent into a header account
 * (is_postable=false) so postings only ever land on a specific bank
 * account's own leaf from then on. That conversion only happens if the
 * parent has never actually been posted to directly; if it has (a
 * company that posted straight to 1.1.1/1.1.2 before Fase 6 existed),
 * conversion is refused with a clear error rather than silently
 * orphaning those historical postings under a now-header account.
 */
class BankAccountService
{
    private const CASH_PARENT_CODE = '1.1.1';
    private const BANK_PARENT_CODE = '1.1.2';

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly MoneyConversionService $money,
    ) {
    }

    public function create(array $input): BankAccount
    {
        return DB::transaction(function () use ($input) {
            $companyId = $this->currentCompany->id();
            $currencyCode = strtoupper((string) ($input['currency_code'] ?? $this->currentCompany->get()?->currency ?? 'IDR'));

            if (! CompanyCurrency::where('company_id', $companyId)->where('currency_code', $currencyCode)->where('is_active', true)->exists()) {
                throw new RuntimeException("Currency {$currencyCode} is not enabled for this company.");
            }
            $parentCode = $input['type'] === 'cash' ? self::CASH_PARENT_CODE : self::BANK_PARENT_CODE;

            $parent = Account::where('company_id', $companyId)->where('code', $parentCode)->first();

            if (! $parent) {
                throw new RuntimeException("Chart of accounts is missing the expected account \"{$parentCode}\" for bank accounts.");
            }

            $this->ensureParentIsHeaderAccount($parent);

            $childAccount = Account::create([
                'company_id' => $companyId,
                'parent_id' => $parent->id,
                'code' => $this->nextChildCode($parent),
                'name' => $input['name'],
                'type' => $parent->type,
                'normal_balance' => $parent->normal_balance,
                'is_postable' => true,
                'currency_code' => $currencyCode,
                'is_active' => true,
            ]);

            $openingBalance = (string) ($input['opening_balance'] ?? 0);

            return BankAccount::create([
                'code' => $input['code'],
                'name' => $input['name'],
                'type' => $input['type'],
                'bank_name' => $input['bank_name'] ?? null,
                'account_number' => $input['account_number'] ?? null,
                'account_id' => $childAccount->id,
                'currency_code' => $currencyCode,
                'opening_balance' => $openingBalance,
                'opening_balance_base' => $this->money->toBase($openingBalance, $currencyCode),
                'is_active' => true,
            ]);
        });
    }

    private function ensureParentIsHeaderAccount(Account $parent): void
    {
        if (! $parent->is_postable) {
            return;
        }

        $hasPostings = JournalLine::where('account_id', $parent->id)->exists();

        if ($hasPostings) {
            throw new RuntimeException(
                "Account \"{$parent->code} {$parent->name}\" already has journal postings directly against it and cannot be converted into a header account. Move those postings to a specific bank account first."
            );
        }

        $parent->update(['is_postable' => false]);
    }

    private function nextChildCode(Account $parent): string
    {
        $lastSuffix = Account::where('parent_id', $parent->id)
            ->where('company_id', $parent->company_id)
            ->orderByRaw('CAST(SUBSTR(code, ' . (strlen($parent->code) + 2) . ') AS INTEGER) DESC')
            ->value('code');

        $nextSuffix = $lastSuffix ? ((int) substr($lastSuffix, strlen($parent->code) + 1)) + 1 : 1;

        return "{$parent->code}.{$nextSuffix}";
    }
}
