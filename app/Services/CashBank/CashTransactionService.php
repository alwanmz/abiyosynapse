<?php

namespace App\Services\CashBank;

use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\MoneyConversionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a manual Kas Bank transaction not tied to a specific AR/AP
 * document (blueprint's generic cash-in/cash-out concept — e.g. capital
 * injection, operational expense, owner withdrawal). AR collection and
 * AP payment get their own dedicated flows in Fase 7/8 that post to the
 * same bank account's GL account through their own services, not this
 * one — this service only ever posts bank_account <-> counter_account.
 *
 * type=in  -> bank account's GL account (debit) / counter account (credit)
 * type=out -> counter account (debit) / bank account's GL account (credit)
 */
class CashTransactionService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly MoneyConversionService $money,
    ) {
    }

    public function create(array $input, ?User $creator = null): CashTransaction
    {
        return DB::transaction(function () use ($input, $creator) {
            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::findOrFail($input['bank_account_id']);
            $amount = (float) $input['amount'];
            $currencyCode = strtoupper((string) $bankAccount->currency_code);
            $conversion = $this->money->convert(
                (string) $input['amount'],
                $currencyCode,
                $this->money->baseCurrency(),
                $input['transaction_date'],
            );
            $amountBase = $conversion['amount'];

            if ($amount <= 0) {
                throw new RuntimeException('Transaction amount must be greater than zero.');
            }

            $transaction = CashTransaction::create([
                'number' => $this->nextNumber(),
                'bank_account_id' => $bankAccount->id,
                'type' => $input['type'],
                'transaction_date' => $input['transaction_date'],
                'counter_account_id' => $input['counter_account_id'],
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'exchange_rate' => $conversion['rate'],
                'amount_base' => $amountBase,
                'description' => $input['description'],
                'reference' => $input['reference'] ?? null,
                'created_by' => $creator?->id,
            ]);

            $lines = $input['type'] === 'in'
                ? [
                    ['account_id' => $bankAccount->account_id, 'debit' => $amountBase, 'amount_currency' => $amount, 'currency_code' => $currencyCode],
                    ['account_id' => $input['counter_account_id'], 'credit' => $amountBase, 'amount_currency' => -$amount, 'currency_code' => $currencyCode],
                ]
                : [
                    ['account_id' => $input['counter_account_id'], 'debit' => $amountBase, 'amount_currency' => $amount, 'currency_code' => $currencyCode],
                    ['account_id' => $bankAccount->account_id, 'credit' => $amountBase, 'amount_currency' => -$amount, 'currency_code' => $currencyCode],
                ];

            $this->posting->post(
                description: "{$transaction->number}: {$input['description']}",
                lines: $lines,
                sourceable: $transaction,
                entryDate: $input['transaction_date'],
            );

            return $transaction->fresh();
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'CT-' . now()->format('Y') . '-';

        $lastNumber = CashTransaction::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
