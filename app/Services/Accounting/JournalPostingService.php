<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\CurrentCompany;
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
     * @param  array<int, array{account_id: int, debit?: float, credit?: float, description?: string, costable?: Model}>  $lines
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

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += $line['debit'] ?? 0;
            $totalCredit += $line['credit'] ?? 0;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new RuntimeException(
                "Journal entry is not balanced: debit {$totalDebit} != credit {$totalCredit}.",
            );
        }

        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Cannot post a journal entry without a resolved company context.');
        }

        return DB::transaction(function () use ($companyId, $description, $lines, $sourceable, $entryDate, $totalDebit, $totalCredit) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'number' => $this->nextNumber($companyId),
                'entry_date' => $entryDate ?? now()->toDateString(),
                'description' => $description,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $this->assertPostable($line['account_id']);

                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
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
