<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

class MoneyConversionService
{
    public function __construct(
        private readonly CurrencyRateService $rates,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function baseCurrency(): string
    {
        return strtoupper((string) ($this->currentCompany->get()?->currency ?? 'IDR'));
    }

    /** @return array{amount: string, rate: string, effective_date: string, source_rate_id: int|null, reciprocal: bool} */
    public function convert(
        string|int|float $amount,
        string $from,
        string $to,
        CarbonInterface|string|null $date = null,
        ?string $overrideRate = null,
    ): array {
        $resolution = $overrideRate !== null
            ? [
                'rate' => $overrideRate,
                'effective_date' => $date ? now()->parse($date)->toDateString() : now()->toDateString(),
                'source_rate_id' => null,
                'reciprocal' => false,
            ]
            : $this->rates->resolve($from, $to, $date);

        $converted = BigDecimal::of((string) $amount)
            ->multipliedBy(BigDecimal::of((string) $resolution['rate']))
            ->toScale(6, RoundingMode::HALF_UP);

        return [
            'amount' => $converted->__toString(),
            ...$resolution,
        ];
    }

    public function toBase(
        string|int|float $amount,
        string $currency,
        CarbonInterface|string|null $date = null,
        ?string $overrideRate = null,
    ): string {
        $base = $this->baseCurrency();

        return $this->convert($amount, $currency, $base, $date, $overrideRate)['amount'];
    }

    public function fromBase(
        string|int|float $amount,
        string $currency,
        CarbonInterface|string|null $date = null,
        ?string $overrideRate = null,
    ): string {
        $base = $this->baseCurrency();

        return $this->convert($amount, $base, $currency, $date, $overrideRate)['amount'];
    }
}
