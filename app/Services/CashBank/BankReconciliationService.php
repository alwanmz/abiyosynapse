<?php

namespace App\Services\CashBank;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives a Bank Reconciliation session (draft -> completed, mirrors
 * StockOpname's physical-count-vs-system-record pattern from Fase 2):
 * create() snapshots every not-yet-reconciled cash transaction for the
 * bank account up to the statement date as reconciliation lines (all
 * starting uncleared), the user then marks lines cleared as they match
 * them against the real bank statement, and complete() just locks the
 * session — it does not post any GL entries itself, since reconciliation
 * doesn't change what happened financially, only confirms it against an
 * external record.
 */
class BankReconciliationService
{
    public function create(array $input, ?User $creator = null): BankReconciliation
    {
        return DB::transaction(function () use ($input, $creator) {
            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::findOrFail($input['bank_account_id']);

            $reconciliation = BankReconciliation::create([
                'number' => $this->nextNumber(),
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $input['statement_date'],
                'statement_balance' => $input['statement_balance'],
                'status' => 'draft',
                'created_by' => $creator?->id,
            ]);

            $alreadyReconciledIds = BankReconciliationLine::whereHas(
                'cashTransaction',
                fn ($query) => $query->where('bank_account_id', $bankAccount->id)
            )->pluck('cash_transaction_id');

            $transactions = CashTransaction::where('bank_account_id', $bankAccount->id)
                ->where('transaction_date', '<=', $input['statement_date'])
                ->whereNotIn('id', $alreadyReconciledIds)
                ->get();

            foreach ($transactions as $transaction) {
                $reconciliation->lines()->create([
                    'cash_transaction_id' => $transaction->id,
                    'is_cleared' => false,
                ]);
            }

            return $reconciliation->fresh('lines');
        });
    }

    public function updateLines(BankReconciliation $reconciliation, array $lineUpdates): BankReconciliation
    {
        if (! $reconciliation->isDraft()) {
            throw new RuntimeException("Bank reconciliation {$reconciliation->number} is no longer editable.");
        }

        DB::transaction(function () use ($reconciliation, $lineUpdates) {
            foreach ($lineUpdates as $update) {
                $reconciliation->lines()
                    ->where('id', $update['id'])
                    ->update(['is_cleared' => (bool) $update['is_cleared']]);
            }
        });

        return $reconciliation->fresh('lines');
    }

    public function complete(BankReconciliation $reconciliation): BankReconciliation
    {
        if (! $reconciliation->isDraft()) {
            throw new RuntimeException("Bank reconciliation {$reconciliation->number} has already been completed.");
        }

        $reconciliation->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    private function nextNumber(): string
    {
        $prefix = 'BR-' . now()->format('Y') . '-';

        $lastNumber = BankReconciliation::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
