<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\CurrentCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generic double-entry posting engine. Every transactional module
 * (Purchase, Sales, Production, Inventory, etc.) builds its own list of
 * lines and calls post() — this service never contains module-specific
 * business logic, only the invariants of double-entry bookkeeping.
 *
 * Usage:
 *   $service->post(
 *       description: 'Purchase receipt PO-2026-001',
 *       lines: [
 *           ['account_id' => $inventoryAccount->id, 'debit' => 500000],
 *           ['account_id' => $apAccount->id, 'credit' => 500000],
 *       ],
 *       sourceable: $purchaseReceipt,
 *   );
 */
class JournalPostingService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * @param  array<int, array{account_id: int, debit?: string|float, credit?: string|float, debit_base?: string|float, credit_base?: string|float, amount_currency?: string|float, currency_code?: string, exchange_rate?: string|float, description?: string, costable?: Model}>  $lines
     */
    public function post(
        string $description,
        array $lines,
        ?Model $sourceable = null,
        ?string $entryDate = null,
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new RuntimeException('A journal entry needs at least two lines.');
        }

        $totalDebit = BigDecimal::zero();
        $totalCredit = BigDecimal::zero();

        foreach ($lines as $line) {
            $totalDebit = $totalDebit->plus(BigDecimal::of((string) ($line['debit_base'] ?? $line['debit'] ?? 0)));
            $totalCredit = $totalCredit->plus(BigDecimal::of((string) ($line['credit_base'] ?? $line['credit'] ?? 0)));
        }

        if ($totalDebit->toScale(6, RoundingMode::HALF_UP)->compareTo($totalCredit->toScale(6, RoundingMode::HALF_UP)) !== 0) {
            throw new RuntimeException(
                "Journal entry is not balanced: debit {$totalDebit} != credit {$totalCredit}.",
            );
        }

        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Cannot post a journal entry without a resolved company context.');
        }

        $company = $this->currentCompany->get();
        $entryCurrency = strtoupper((string) ($lines[0]['currency_code'] ?? $company?->currency ?? 'IDR'));
        $entryRate = (string) ($lines[0]['exchange_rate'] ?? 1);

        return DB::transaction(function () use ($companyId, $description, $lines, $sourceable, $entryDate, $totalDebit, $totalCredit, $entryCurrency, $entryRate) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'number' => $this->nextNumber($companyId),
                'entry_date' => $entryDate ?? now()->toDateString(),
                'description' => $description,
                'currency_code' => $entryCurrency,
                'exchange_rate' => $entryRate,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'status' => 'posted',
                'total_debit' => $totalDebit->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'total_credit' => $totalCredit->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'total_debit_base' => $totalDebit->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'total_credit_base' => $totalCredit->toScale(6, RoundingMode::HALF_UP)->__toString(),
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $this->assertPostable($line['account_id']);

                $debitBase = (string) ($line['debit_base'] ?? $line['debit'] ?? 0);
                $creditBase = (string) ($line['credit_base'] ?? $line['credit'] ?? 0);
                $amountCurrency = (string) ($line['amount_currency'] ?? BigDecimal::of($debitBase)->minus(BigDecimal::of($creditBase)));
                $currencyCode = strtoupper((string) ($line['currency_code'] ?? $entryCurrency));
                $exchangeRate = (string) ($line['exchange_rate'] ?? 1);

                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'currency_code' => $currencyCode,
                    'amount_currency' => $amountCurrency,
                    'exchange_rate' => $exchangeRate,
                    'debit' => $debitBase,
                    'credit' => $creditBase,
                    'debit_base' => $debitBase,
                    'credit_base' => $creditBase,
                    'description' => $line['description'] ?? null,
                    'costable_type' => ($line['costable'] ?? null)?->getMorphClass(),
                    'costable_id' => ($line['costable'] ?? null)?->getKey(),
                ]);
            }

            return $entry;
        });
    }

    private function assertPostable(int $accountId): void
    {
        $account = Account::find($accountId);

        if (! $account) {
            throw new RuntimeException("Account {$accountId} does not exist.");
        }

        if (! $account->is_postable) {
            throw new RuntimeException("Account \"{$account->name}\" is a header account and cannot receive postings directly.");
        }
    }

    /**
     * Derives the next number from the highest existing suffix for this
     * year's prefix, not a row count — a count-based scheme collides as
     * soon as any entry is ever deleted (count drops but existing higher
     * numbers remain), which a hard-deleted record from manual cleanup
     * has already triggered once in this app's history.
     */
    private function nextNumber(int $companyId): string
    {
        $prefix = 'JE-' . now()->format('Y') . '-';

        $lastNumber = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
