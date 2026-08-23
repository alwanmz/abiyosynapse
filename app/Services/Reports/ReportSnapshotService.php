<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ReportingAccountMapping;
use App\Services\CurrencyRateService;
use App\Services\CurrentCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use RuntimeException;

class ReportSnapshotService
{
    /** @var array<int, string> */
    private const AMOUNT_KEYS = [
        'amount', 'debit', 'credit', 'balance', 'opening', 'change', 'closing',
        'opening_balance', 'closing_balance', 'current_earnings', 'net_profit',
        'revenue', 'expenses', 'total_revenue', 'total_expenses', 'total_assets',
        'total_liabilities', 'total_equity', 'liabilities_and_equity', 'difference',
        'assets', 'liabilities', 'equity', 'total', 'subtotal', 'tax_total',
        'opening_cash', 'operating', 'investing', 'financing', 'net_change',
        'closing_cash', 'profit_loss', 'total_opening', 'total_change', 'total_closing',
    ];

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly FinancialReportService $reports,
        private readonly CurrencyRateService $rates,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        string $report,
        string $fromDate,
        string $toDate,
        ?int $accountId = null,
        ?string $presentationCurrency = null,
        ?string $language = null,
        bool $comparative = true,
        ?string $reportingStandard = null,
    ): array {
        $company = $this->company();
        $standard = in_array($reportingStandard ?: $company->reporting_standard, ['sak_ep', 'psak_umum'], true)
            ? ($reportingStandard ?: $company->reporting_standard)
            : 'sak_ep';
        $language = in_array($language ?: $company->reporting_language, ['id', 'en', 'zh', 'ja', 'ko'], true)
            ? ($language ?: $company->reporting_language)
            : 'id';
        $functionalCurrency = strtoupper((string) $company->currency);
        $presentationCurrency = strtoupper((string) ($presentationCurrency ?: $company->presentationCurrencyCode()));
        $currentPayload = $this->payload(
            $report,
            $fromDate,
            $toDate,
            $accountId,
            $presentationCurrency,
            $language,
            $comparative,
            $reportingStandard,
        );
        $previous = null;

        if ($comparative && $company->comparative_period_enabled) {
            [$previousFrom, $previousTo] = $this->previousPeriod($fromDate, $toDate);
            $previous = [
                'from' => $previousFrom,
                'to' => $previousTo,
                    'data' => $this->payload(
                        $report,
                        $previousFrom,
                        $previousTo,
                        $accountId,
                        $presentationCurrency,
                        $language,
                        false,
                        $reportingStandard,
                    ),
            ];
        }

        $rate = $this->rates->resolve($functionalCurrency, $presentationCurrency, $toDate, $company, false);
        $minorUnit = (int) (Currency::where('code', $presentationCurrency)->value('minor_unit') ?? 2);
        // A financial_statements payload is already composed from child
        // snapshots, so translating it again would double-convert foreign
        // presentation amounts.
        if ($report !== 'financial_statements') {
            $this->translateAmounts($currentPayload, (string) $rate['rate'], $minorUnit);
        }
        if ($previous !== null && $report !== 'financial_statements') {
            $this->translateAmounts($previous['data'], (string) $rate['rate'], $minorUnit);
        }

        return [
            'meta' => [
                'report' => $report,
                'reporting_standard' => $standard,
                'language' => $language,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'functional_currency' => $functionalCurrency,
                'presentation_currency' => $presentationCurrency,
                'presentation_rate' => (string) $rate['rate'],
                'rate_effective_date' => $rate['effective_date'],
                'rate_source_id' => $rate['source_rate_id'],
                'comparative_available' => $previous !== null,
                'generated_at' => now()->toIso8601String(),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'code' => $company->code,
                'address' => $company->address,
                'tax_id' => $company->tax_id,
            ],
            'data' => $currentPayload,
            'previous' => $previous,
            'warnings' => $this->mappingWarnings($company, $standard, $report),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function buildFinancialPackage(
        string $fromDate,
        string $toDate,
        ?string $presentationCurrency = null,
        ?string $language = null,
        bool $comparative = true,
        ?string $reportingStandard = null,
    ): array {
        $reports = ['balance_sheet', 'profit_loss', 'equity', 'cash_flow', 'notes', 'trial_balance', 'general_ledger'];
        $package = [];

        foreach ($reports as $report) {
            $package[$report] = $this->build(
                $report,
                $fromDate,
                $toDate,
                null,
                $presentationCurrency,
                $language,
                $comparative,
                $reportingStandard,
            );
        }

        return $package;
    }

    /** @return array<string, mixed> */
    private function payload(
        string $report,
        string $fromDate,
        string $toDate,
        ?int $accountId,
        ?string $presentationCurrency = null,
        ?string $language = null,
        bool $comparative = true,
        ?string $reportingStandard = null,
    ): array
    {
        if ($report === 'financial_statements') {
            return [
                'package' => $this->buildFinancialPackage(
                    $fromDate,
                    $toDate,
                    $presentationCurrency,
                    $language,
                    $comparative,
                    $reportingStandard,
                ),
            ];
        }

        return $this->reports->build($report, $fromDate, $toDate, $accountId, $reportingStandard);
    }

    /** @return array{0: string, 1: string} */
    private function previousPeriod(string $fromDate, string $toDate): array
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();

        return [
            $previousTo->copy()->subDays($days - 1)->toDateString(),
            $previousTo->toDateString(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function translateAmounts(array &$payload, string $rate, int $minorUnit): void
    {
        foreach ($payload as $key => &$value) {
            if (is_array($value)) {
                $this->translateAmounts($value, $rate, $minorUnit);
                continue;
            }

            if (! in_array((string) $key, self::AMOUNT_KEYS, true) || ! is_numeric($value)) {
                continue;
            }

            $value = (float) BigDecimal::of((string) $value)
                ->multipliedBy(BigDecimal::of($rate))
                ->toScale($minorUnit, RoundingMode::HALF_UP)
                ->__toString();
        }
        unset($value);
    }

    /** @return array<int, string> */
    private function mappingWarnings(Company $company, string $standard, string $report): array
    {
        $supportedReports = match ($report) {
            'balance_sheet', 'profit_loss', 'equity' => [$report === 'equity' ? 'balance_sheet' : $report],
            'notes', 'financial_statements' => ['balance_sheet', 'profit_loss'],
            default => [],
        };
        if ($supportedReports === []) {
            return [];
        }
        $mappedIds = ReportingAccountMapping::query()
            ->where('company_id', $company->id)
            ->where('reporting_standard', $standard)
            ->whereIn('report_code', $supportedReports)
            ->where('is_active', true)
            ->pluck('account_id');
        $types = match ($report) {
            'profit_loss' => ['revenue', 'expense'],
            'equity' => ['equity'],
            'notes', 'financial_statements' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
            default => ['asset', 'liability', 'equity'],
        };
        $unmapped = Account::query()
            ->where('company_id', $company->id)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->whereIn('type', $types)
            ->whereNotIn('id', $mappedIds)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        if ($unmapped === []) {
            return [];
        }

        return ['Unmapped accounts: ' . implode(', ', $unmapped)];
    }

    private function company(): Company
    {
        $company = $this->currentCompany->get();
        if (! $company) {
            throw new RuntimeException('Cannot build a report without a resolved company context.');
        }

        return $company;
    }
}
