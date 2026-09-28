<?php

namespace App\Services;

use App\Models\ApPayment;
use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\ExchangeRevaluationRun;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\SupplierInvoice;
use App\Services\Accounting\JournalPostingService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

class ExchangeRevaluationService
{
    private const AR_ACCOUNT_ROLE = AccountRole::AccountsReceivable;
    private const AP_ACCOUNT_ROLE = AccountRole::AccountsPayable;
    private const FX_GAIN_ACCOUNT_ROLE = AccountRole::FxGain;
    private const FX_LOSS_ACCOUNT_ROLE = AccountRole::FxUnrealizedLoss;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CurrencyRateService $rates,
        private readonly JournalPostingService $posting,
    ) {
    }

    public function run(string $date, ?string $currencyCode = null, ?int $userId = null): ExchangeRevaluationRun
    {
        $company = $this->currentCompany->get();
        if (! $company) {
            throw new RuntimeException('Cannot run currency revaluation without a company context.');
        }

        $currencyCode = strtoupper((string) ($currencyCode ?: $company->currency));
        if ($currencyCode === $company->currency) {
            throw new RuntimeException('The company base currency does not need revaluation.');
        }

        $existing = ExchangeRevaluationRun::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereDate('revaluation_date', $date)
            ->where('currency_code', $currencyCode)
            ->where('status', 'completed')
            ->first();
        if ($existing) {
            return $existing;
        }

        $rate = $this->rates->resolve($currencyCode, $company->currency, $date, $company)['rate'];
        $lines = [];
        $netAdjustment = 0.0;

        $ar = $this->accountId(self::AR_ACCOUNT_ROLE);
        $ap = $this->accountId(self::AP_ACCOUNT_ROLE);
        $gain = $this->accountId(self::FX_GAIN_ACCOUNT_ROLE);
        $loss = $this->accountId(self::FX_LOSS_ACCOUNT_ROLE);

        $sales = SalesInvoice::where('currency_code', $currencyCode)->whereIn('status', ['posted', 'paid'])->get();
        $foreignAr = $sales->sum(fn (SalesInvoice $invoice) => $invoice->outstandingAmount());
        $carryingAr = $sales->sum(fn (SalesInvoice $invoice) => (float) $invoice->total_base - (float) $invoice->paid_amount_base);
        $newAr = (float) BigDecimal::of((string) $foreignAr)->multipliedBy((string) $rate)->__toString();
        $arAdjustment = round($newAr - $carryingAr, 6);
        if (abs($arAdjustment) > 0.000001) {
            $netAdjustment += $arAdjustment;
            $lines[] = $arAdjustment > 0
                ? ['account_id' => $ar, 'debit' => $arAdjustment, 'amount_currency' => $foreignAr, 'currency_code' => $currencyCode, 'exchange_rate' => $rate]
                : ['account_id' => $ar, 'credit' => abs($arAdjustment), 'amount_currency' => -$foreignAr, 'currency_code' => $currencyCode, 'exchange_rate' => $rate];
            $lines[] = $arAdjustment > 0
                ? ['account_id' => $gain, 'credit' => $arAdjustment, 'currency_code' => $company->currency, 'exchange_rate' => 1]
                : ['account_id' => $loss, 'debit' => abs($arAdjustment), 'currency_code' => $company->currency, 'exchange_rate' => 1];
        }

        $purchases = SupplierInvoice::where('currency_code', $currencyCode)->whereIn('status', ['matched', 'paid'])->get();
        $foreignAp = $purchases->sum(fn (SupplierInvoice $invoice) => $invoice->outstandingAmount());
        $carryingAp = $purchases->sum(fn (SupplierInvoice $invoice) => (float) $invoice->total_base - (float) $invoice->paid_amount_base);
        $newAp = (float) BigDecimal::of((string) $foreignAp)->multipliedBy((string) $rate)->__toString();
        $apAdjustment = round($newAp - $carryingAp, 6);
        if (abs($apAdjustment) > 0.000001) {
            $netAdjustment -= $apAdjustment;
            $lines[] = $apAdjustment > 0
                ? ['account_id' => $ap, 'credit' => $apAdjustment, 'amount_currency' => -$foreignAp, 'currency_code' => $currencyCode, 'exchange_rate' => $rate]
                : ['account_id' => $ap, 'debit' => abs($apAdjustment), 'amount_currency' => $foreignAp, 'currency_code' => $currencyCode, 'exchange_rate' => $rate];
            $lines[] = $apAdjustment > 0
                ? ['account_id' => $loss, 'debit' => $apAdjustment, 'currency_code' => $company->currency, 'exchange_rate' => 1]
                : ['account_id' => $gain, 'credit' => abs($apAdjustment), 'currency_code' => $company->currency, 'exchange_rate' => 1];
        }

        $bankAccounts = BankAccount::query()
            ->where('currency_code', $currencyCode)
            ->with('cashTransactions:id,bank_account_id,type,amount,amount_base')
            ->get();

        foreach ($bankAccounts as $bankAccount) {
            $foreignBalance = (float) $bankAccount->opening_balance;
            $carryingBase = (float) $bankAccount->opening_balance_base;

            foreach ($bankAccount->cashTransactions as $transaction) {
                $direction = $transaction->type === 'in' ? 1 : -1;
                $foreignBalance += $direction * (float) $transaction->amount;
                $carryingBase += $direction * (float) $transaction->amount_base;
            }

            // AR receipts and AP payments move the same bank balance as a
            // manual cash transaction. Include them here so revaluation
            // reflects the complete foreign-currency ledger, including
            // settlements that may already contain realized FX gain/loss.
            $receipts = ArReceipt::where('bank_account_id', $bankAccount->id)->get();
            foreach ($receipts as $receipt) {
                $foreignBalance += (float) $receipt->amount;
                $carryingBase += (float) $receipt->amount_base;
            }

            $payments = ApPayment::where('bank_account_id', $bankAccount->id)->get();
            foreach ($payments as $payment) {
                $foreignBalance -= (float) $payment->amount;
                $carryingBase -= (float) $payment->amount_base;
            }

            if (abs($foreignBalance) < 0.000001) {
                continue;
            }

            $newBase = (float) BigDecimal::of((string) $foreignBalance)->multipliedBy((string) $rate)->__toString();
            $bankAdjustment = round($newBase - $carryingBase, 6);
            if (abs($bankAdjustment) < 0.000001) {
                continue;
            }

            $netAdjustment += $bankAdjustment;
            $lines[] = $bankAdjustment > 0
                ? ['account_id' => $bankAccount->account_id, 'debit' => $bankAdjustment, 'currency_code' => $company->currency, 'exchange_rate' => 1]
                : ['account_id' => $bankAccount->account_id, 'credit' => abs($bankAdjustment), 'currency_code' => $company->currency, 'exchange_rate' => 1];
            $lines[] = $bankAdjustment > 0
                ? ['account_id' => $gain, 'credit' => $bankAdjustment, 'currency_code' => $company->currency, 'exchange_rate' => 1]
                : ['account_id' => $loss, 'debit' => abs($bankAdjustment), 'currency_code' => $company->currency, 'exchange_rate' => 1];
        }

        return DB::transaction(function () use ($company, $date, $currencyCode, $netAdjustment, $lines, $userId) {
            $journal = null;
            if ($lines !== []) {
                $journal = $this->posting->post(
                    description: "FX revaluation {$currencyCode} {$date}",
                    lines: $lines,
                    entryDate: $date,
                );
            }

            return ExchangeRevaluationRun::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'revaluation_date' => $date,
                'currency_code' => $currencyCode,
                'total_adjustment_base' => $netAdjustment,
                'status' => 'completed',
                'journal_entry_id' => $journal?->id,
                'created_by' => $userId ?? auth()->id(),
            ]);
        });
    }

    public function reverse(ExchangeRevaluationRun $run, ?int $userId = null): ExchangeRevaluationRun
    {
        $company = $this->currentCompany->get();
        if (! $company || (int) $run->company_id !== (int) $company->id) {
            throw new RuntimeException('The revaluation run does not belong to the current company.');
        }
        if ($run->status !== 'completed') {
            throw new RuntimeException('Only a completed revaluation run can be reversed.');
        }
        if ($run->reversal_journal_entry_id) {
            return $run;
        }

        return DB::transaction(function () use ($run, $userId) {
            $original = $run->journalEntry()->with('lines')->first();
            $journal = null;

            if ($original) {
                $journal = $this->posting->post(
                    description: "Reverse FX revaluation {$run->currency_code} {$run->revaluation_date->toDateString()}",
                    lines: $original->lines->map(fn ($line): array => [
                        'account_id' => $line->account_id,
                        'debit_base' => (string) $line->credit_base,
                        'credit_base' => (string) $line->debit_base,
                        'amount_currency' => '-' . (string) $line->amount_currency,
                        'currency_code' => $line->currency_code,
                        'exchange_rate' => (string) $line->exchange_rate,
                    ])->all(),
                    sourceable: $run,
                    entryDate: $run->revaluation_date->toDateString(),
                );
            }

            $run->update([
                'status' => 'reversed',
                'reversal_journal_entry_id' => $journal?->id,
            ]);

            return $run->fresh();
        });
    }

    private function accountId(AccountRole $role): int
    {
        return app(AccountRoleResolver::class)->id($role);
    }
}
