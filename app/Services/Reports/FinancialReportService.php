<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ReportingAccountMapping;
use App\Services\CurrentCompany;
use App\Support\AccountRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read-only financial reports backed by posted double-entry journals.
 *
 * The service deliberately owns all report arithmetic so the four views
 * cannot slowly drift apart by implementing their own balance formulas.
 */
class FinancialReportService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $report, string $fromDate, string $toDate, ?int $accountId = null, ?string $reportingStandard = null): array
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Cannot build a financial report without a resolved company context.');
        }

        return match ($report) {
            'trial_balance' => ['trialBalance' => $this->trialBalance($companyId, $toDate)],
            'general_ledger' => ['generalLedger' => $this->generalLedger($companyId, $fromDate, $toDate, $accountId)],
            'profit_loss' => ['profitLoss' => $this->profitLoss($companyId, $fromDate, $toDate, $reportingStandard)],
            'balance_sheet' => ['balanceSheet' => $this->balanceSheet($companyId, $toDate, $reportingStandard)],
            'cash_flow' => ['cashFlow' => $this->cashFlow($companyId, $fromDate, $toDate)],
            'equity' => ['equityChanges' => $this->equityChanges($companyId, $fromDate, $toDate, $reportingStandard)],
            'notes' => ['notes' => $this->notes($companyId, $fromDate, $toDate, $reportingStandard)],
            default => throw new RuntimeException("Unknown financial report [{$report}]."),
        };
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function trialBalance(int $companyId, string $toDate): array
    {
        $totals = $this->postedLines($companyId)
            ->whereDate('journal_entries.entry_date', '<=', $toDate)
            ->select(
                'journal_lines.account_id',
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.debit_base <> 0 OR journal_lines.debit = 0 THEN journal_lines.debit_base ELSE journal_lines.debit END), 0) AS total_debit'),
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.credit_base <> 0 OR journal_lines.credit = 0 THEN journal_lines.credit_base ELSE journal_lines.credit END), 0) AS total_credit'),
            )
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $this->postableAccounts($companyId)
            ->map(function (Account $account) use ($totals): ?array {
                $total = $totals->get($account->id);
                $net = (float) ($total?->total_debit ?? 0) - (float) ($total?->total_credit ?? 0);
                $debitBalance = max($net, 0);
                $creditBalance = max(-$net, 0);

                if ($debitBalance === 0.0 && $creditBalance === 0.0) {
                    return null;
                }

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'debit' => $debitBalance,
                    'credit' => $creditBalance,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'as_of' => $toDate,
            'rows' => $rows,
            'totals' => [
                'debit' => round(array_sum(array_column($rows, 'debit')), 2),
                'credit' => round(array_sum(array_column($rows, 'credit')), 2),
            ],
        ];
    }

    /**
     * @return array{account: array<string, mixed>|null, opening_balance: float, lines: array<int, array<string, mixed>>, closing_balance: float}
     */
    private function generalLedger(int $companyId, string $fromDate, string $toDate, ?int $accountId): array
    {
        $account = $accountId
            ? $this->postableAccounts($companyId)->firstWhere('id', $accountId)
            : $this->postableAccounts($companyId)->first();

        if (! $account) {
            return [
                'account' => null,
                'opening_balance' => 0.0,
                'lines' => [],
                'closing_balance' => 0.0,
            ];
        }

        $opening = $this->postedLines($companyId)
            ->where('journal_lines.account_id', $account->id)
            ->whereDate('journal_entries.entry_date', '<', $fromDate)
            ->select(
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.debit_base <> 0 OR journal_lines.debit = 0 THEN journal_lines.debit_base ELSE journal_lines.debit END), 0) AS debit'),
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.credit_base <> 0 OR journal_lines.credit = 0 THEN journal_lines.credit_base ELSE journal_lines.credit END), 0) AS credit'),
            )
            ->first();

        $runningBalance = (float) ($opening?->debit ?? 0) - (float) ($opening?->credit ?? 0);
        $lines = [];

        $entries = $this->postedLines($companyId)
            ->where('journal_lines.account_id', $account->id)
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->select([
                'journal_lines.id',
                'journal_lines.debit_base',
                'journal_lines.credit_base',
                'journal_lines.description as line_description',
                'journal_entries.entry_date',
                'journal_entries.number',
                'journal_entries.description',
            ])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->get();

        foreach ($entries as $entry) {
            $debit = (float) $entry->debit_base;
            $credit = (float) $entry->credit_base;
            $runningBalance += $debit - $credit;
            $lines[] = [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date,
                'number' => $entry->number,
                'description' => $entry->line_description ?: $entry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
            ],
            'opening_balance' => round($runningBalance - array_sum(array_map(fn (array $line): float => $line['debit'] - $line['credit'], $lines)), 2),
            'lines' => $lines,
            'closing_balance' => round($runningBalance, 2),
        ];
    }

    /**
     * @return array{from: string, to: string, revenue: array<int, array<string, mixed>>, expenses: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function profitLoss(int $companyId, string $fromDate, string $toDate, ?string $reportingStandard = null): array
    {
        $rows = $this->accountTotals($companyId, ['revenue', 'expense'], $fromDate, $toDate, 'profit_loss', $reportingStandard);
        $revenue = [];
        $expenses = [];

        foreach ($rows as $row) {
            $amount = $row['type'] === 'revenue'
                ? $row['credit'] - $row['debit']
                : $row['debit'] - $row['credit'];

            if (abs($amount) < 0.005) {
                continue;
            }

            $row['amount'] = round($amount, 2);
            if ($row['type'] === 'revenue') {
                $revenue[] = $row;
            } else {
                $expenses[] = $row;
            }
        }

        $totalRevenue = round(array_sum(array_column($revenue, 'amount')), 2);
        $totalExpenses = round(array_sum(array_column($expenses, 'amount')), 2);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => [
                'revenue' => $totalRevenue,
                'expenses' => $totalExpenses,
                'net_profit' => round($totalRevenue - $totalExpenses, 2),
            ],
        ];
    }

    /**
     * @return array{as_of: string, assets: array<int, array<string, mixed>>, liabilities: array<int, array<string, mixed>>, equity: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function balanceSheet(int $companyId, string $toDate, ?string $reportingStandard = null): array
    {
        $rows = $this->accountTotals($companyId, ['asset', 'liability', 'equity'], null, $toDate, 'balance_sheet', $reportingStandard);
        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($rows as $row) {
            $amount = in_array($row['type'], ['asset'], true)
                ? $row['debit'] - $row['credit']
                : $row['credit'] - $row['debit'];

            if (abs($amount) < 0.005) {
                continue;
            }

            $row['amount'] = round($amount, 2);
            match ($row['type']) {
                'asset' => $assets[] = $row,
                'liability' => $liabilities[] = $row,
                default => $equity[] = $row,
            };
        }

        $earnings = $this->accountTotals($companyId, ['revenue', 'expense'], null, $toDate, 'profit_loss', $reportingStandard);
        $currentEarnings = 0.0;
        foreach ($earnings as $row) {
            $currentEarnings += $row['type'] === 'revenue'
                ? $row['credit'] - $row['debit']
                : -($row['debit'] - $row['credit']);
        }

        $totalAssets = round(array_sum(array_column($assets, 'amount')), 2);
        $totalLiabilities = round(array_sum(array_column($liabilities, 'amount')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'amount')), 2);

        return [
            'as_of' => $toDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_earnings' => round($currentEarnings, 2),
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => round($totalLiabilities + $totalEquity + $currentEarnings, 2),
                'difference' => round($totalAssets - ($totalLiabilities + $totalEquity + $currentEarnings), 2),
            ],
        ];
    }

    /**
     * Derives a traceable indirect cash-flow view from posted entries that
     * touch the company's cash or bank accounts. It deliberately exposes the
     * beginning/ending cash reconciliation so an accountant can review the
     * classification instead of receiving an unexplained total.
     *
     * @return array<string, mixed>
     */
    private function cashFlow(int $companyId, string $fromDate, string $toDate): array
    {
        $cashAccountIds = $this->cashAndBankAccountIds($companyId);

        $opening = $this->cashBalance($companyId, $cashAccountIds->all(), '<', $fromDate);
        $closing = $this->cashBalance($companyId, $cashAccountIds->all(), '<=', $toDate);
        $categories = ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0];

        JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->with('lines.account:id,code,type')
            ->get()
            ->each(function (JournalEntry $entry) use ($cashAccountIds, &$categories): void {
                $cashNet = $entry->lines
                    ->whereIn('account_id', $cashAccountIds)
                    ->sum(fn (JournalLine $line): float => (float) $line->debit_base - (float) $line->credit_base);

                if (abs($cashNet) < 0.005) {
                    return;
                }

                $counterparts = $entry->lines->reject(fn (JournalLine $line): bool => $cashAccountIds->contains($line->account_id));
                $category = $counterparts->contains(fn (JournalLine $line): bool => str_starts_with((string) $line->account?->code, '1.2'))
                    ? 'investing'
                    : ($counterparts->contains(fn (JournalLine $line): bool => $line->account?->type === 'equity' || str_starts_with((string) $line->account?->code, '2.2'))
                        ? 'financing'
                        : 'operating');

                $categories[$category] += $cashNet;
            });

        $netChange = round($closing - $opening, 2);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'opening_cash' => round($opening, 2),
            'operating' => round($categories['operating'], 2),
            'investing' => round($categories['investing'], 2),
            'financing' => round($categories['financing'], 2),
            'net_change' => $netChange,
            'closing_cash' => round($closing, 2),
            'reconciles' => abs($netChange - array_sum($categories)) < 0.01,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function equityChanges(int $companyId, string $fromDate, string $toDate, ?string $reportingStandard = null): array
    {
        $openingDate = now()->parse($fromDate)->subDay()->toDateString();
        $openingRows = collect($this->accountTotals($companyId, ['equity'], null, $openingDate, 'balance_sheet', $reportingStandard))
            ->keyBy('id');
        $closingRows = collect($this->accountTotals($companyId, ['equity'], null, $toDate, 'balance_sheet', $reportingStandard))
            ->keyBy('id');
        $rows = $closingRows->map(function (array $row) use ($openingRows): array {
            $opening = (float) ($openingRows->get($row['id'])['credit'] ?? 0) - (float) ($openingRows->get($row['id'])['debit'] ?? 0);
            $closing = (float) $row['credit'] - (float) $row['debit'];

            return [
                'id' => $row['id'], 'code' => $row['code'], 'name' => $row['name'],
                'opening' => round($opening, 2), 'change' => round($closing - $opening, 2),
                'closing' => round($closing, 2),
            ];
        })->values()->all();
        $profitLoss = $this->profitLoss($companyId, $fromDate, $toDate, $reportingStandard);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'rows' => $rows,
            'profit_loss' => $profitLoss['totals']['net_profit'],
            'total_opening' => round(array_sum(array_column($rows, 'opening')), 2),
            'total_change' => round(array_sum(array_column($rows, 'change')), 2),
            'total_closing' => round(array_sum(array_column($rows, 'closing')), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notes(int $companyId, string $fromDate, string $toDate, ?string $reportingStandard = null): array
    {
        $company = $this->currentCompany->get();
        $balance = $this->balanceSheet($companyId, $toDate, $reportingStandard);
        $profitLoss = $this->profitLoss($companyId, $fromDate, $toDate, $reportingStandard);

        return [
            'accounting_basis' => 'Accrual basis; posted double-entry journals are the source of financial statement balances.',
            'reporting_standard' => $company?->reporting_standard ?: 'sak_ep',
            'functional_currency' => strtoupper((string) ($company?->currency ?: 'IDR')),
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'key_balances' => [
                'total_assets' => $balance['totals']['assets'],
                'total_liabilities' => $balance['totals']['liabilities'],
                'total_equity' => $balance['totals']['equity'],
                'net_profit' => $profitLoss['totals']['net_profit'],
            ],
            'disclosure_warnings' => [
                'These notes are system-generated and require accountant review before statutory filing.',
            ],
        ];
    }

    /** @param array<int, int> $accountIds */
    private function cashBalance(int $companyId, array $accountIds, string $operator, string $date): float
    {
        if ($accountIds === []) {
            return 0.0;
        }

        $row = $this->postedLines($companyId)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->whereDate('journal_entries.entry_date', $operator, $date)
            ->selectRaw('COALESCE(SUM(journal_lines.debit_base - journal_lines.credit_base), 0) AS balance')
            ->first();

        return (float) ($row?->balance ?? 0);
    }

    /**
     * @param array<int, string> $types
     * @return array<int, array<string, mixed>>
     */
    private function accountTotals(
        int $companyId,
        array $types,
        ?string $fromDate,
        string $toDate,
        ?string $reportCode = null,
        ?string $reportingStandard = null,
    ): array
    {
        $query = $this->postedLines($companyId)
            ->whereIn('accounts.type', $types)
            ->whereDate('journal_entries.entry_date', '<=', $toDate)
            ->select(
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.debit_base <> 0 OR journal_lines.debit = 0 THEN journal_lines.debit_base ELSE journal_lines.debit END), 0) AS debit'),
                DB::raw('COALESCE(SUM(CASE WHEN journal_lines.credit_base <> 0 OR journal_lines.credit = 0 THEN journal_lines.credit_base ELSE journal_lines.credit END), 0) AS credit'),
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');

        if ($fromDate !== null) {
            $query->whereDate('journal_entries.entry_date', '>=', $fromDate);
        }

        $rows = $query->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
        ])->all();

        if (! $reportCode || ! $reportingStandard) {
            return $rows;
        }

        $mappings = ReportingAccountMapping::query()
            ->where('company_id', $companyId)
            ->where('reporting_standard', $reportingStandard)
            ->where('report_code', $reportCode)
            ->where('is_active', true)
            ->get(['account_id', 'line_code', 'display_order', 'sign'])
            ->keyBy('account_id');

        foreach ($rows as &$row) {
            $mapping = $mappings->get($row['id']);
            $row['report_line_code'] = $mapping?->line_code;
            $row['report_display_order'] = $mapping?->display_order;
            $row['report_sign'] = $mapping?->sign;
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            $order = ($left['report_display_order'] ?? PHP_INT_MAX) <=> ($right['report_display_order'] ?? PHP_INT_MAX);

            return $order !== 0 ? $order : strcmp((string) $left['code'], (string) $right['code']);
        });

        return $rows;
    }

    /**
     * The mapped cash and bank parent accounts plus all of their descendants.
     *
     * @return Collection<int, int>
     */
    private function cashAndBankAccountIds(int $companyId): Collection
    {
        $ids = AccountRoleMapping::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('role', [AccountRole::CashParent->value, AccountRole::BankParent->value])
            ->pluck('account_id');
        $frontier = $ids;

        while ($frontier->isNotEmpty()) {
            $frontier = Account::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id');
            $ids = $ids->merge($frontier);
        }

        return $ids->unique()->values();
    }

    private function postedLines(int $companyId): \Illuminate\Database\Eloquent\Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.company_id', $companyId)
            ->where('journal_entries.status', 'posted');
    }

    /**
     * @return Collection<int, Account>
     */
    private function postableAccounts(int $companyId): Collection
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);
    }
}
