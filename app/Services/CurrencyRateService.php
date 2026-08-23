<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CurrencyRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use RuntimeException;

class CurrencyRateService
{
    public const MAX_RATE_AGE_DAYS = 7;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * Resolve an approved rate for 1 unit of $from into $to.
     * Reciprocal lookup is allowed, but the resolved rate is returned so
     * callers can freeze it on the document and never recalculate history.
     *
     * @return array{rate: string, effective_date: string, source_rate_id: int|null, reciprocal: bool}
     */
    public function resolve(
        string $from,
        string $to,
        CarbonInterface|string|null $date = null,
        ?Company $company = null,
        bool $enforceFreshness = true,
    ): array {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $date = $date ? now()->parse($date) : now();
        $company ??= $this->currentCompany->get();

        if (! $company) {
            throw new RuntimeException('Cannot resolve a currency rate without a company context.');
        }

        if ($from === $to) {
            return [
                'rate' => '1',
                'effective_date' => $date->toDateString(),
                'source_rate_id' => null,
                'reciprocal' => false,
            ];
        }

        $rate = CurrencyRate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('from_currency_code', $from)
            ->where('to_currency_code', $to)
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        $reciprocal = false;

        if (! $rate) {
            $rate = CurrencyRate::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('from_currency_code', $to)
                ->where('to_currency_code', $from)
                ->where('status', 'approved')
                ->whereDate('effective_date', '<=', $date->toDateString())
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first();
            $reciprocal = $rate !== null;
        }

        if (! $rate) {
            throw new RuntimeException("No approved exchange rate found for {$from}/{$to} on {$date->toDateString()}.");
        }

        $effectiveDate = $rate->effective_date;
        if ($enforceFreshness && $effectiveDate->diffInDays($date) > self::MAX_RATE_AGE_DAYS) {
            throw new RuntimeException(
                "The approved exchange rate for {$from}/{$to} is older than " . self::MAX_RATE_AGE_DAYS . ' days.',
            );
        }

        $resolvedRate = BigDecimal::of((string) $rate->rate);
        if ($reciprocal) {
            $resolvedRate = BigDecimal::one()->dividedBy($resolvedRate, 12, RoundingMode::HALF_UP);
        }

        return [
            'rate' => $resolvedRate->toScale(12, RoundingMode::HALF_UP)->__toString(),
            'effective_date' => $effectiveDate->toDateString(),
            'source_rate_id' => $rate->id,
            'reciprocal' => $reciprocal,
        ];
    }
}
