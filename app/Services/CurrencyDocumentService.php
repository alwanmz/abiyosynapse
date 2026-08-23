<?php

namespace App\Services;

use App\Models\CompanyCurrency;
use RuntimeException;

class CurrencyDocumentService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CurrencyRateService $rates,
        private readonly MoneyConversionService $money,
    ) {
    }

    /**
     * Resolve and freeze the currency context for a transaction document.
     *
     * @return array{currency_code: string, exchange_rate: string, effective_date: string, source_rate_id: int|null}
     */
    public function resolve(?string $currencyCode, ?string $date = null, ?string $fallbackCurrency = null): array
    {
        $company = $this->currentCompany->get();
        if (! $company) {
            throw new RuntimeException('Cannot resolve document currency without a company context.');
        }

        $currencyCode = strtoupper((string) ($currencyCode ?: $fallbackCurrency ?: $company->currency));
        $enabled = CompanyCurrency::where('company_id', $company->id)
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->exists();

        if (! $enabled) {
            throw new RuntimeException("Currency {$currencyCode} is not enabled for this company.");
        }

        $resolution = $this->rates->resolve($currencyCode, $company->currency, $date, $company);

        return [
            'currency_code' => $currencyCode,
            'exchange_rate' => $resolution['rate'],
            'effective_date' => $resolution['effective_date'],
            'source_rate_id' => $resolution['source_rate_id'],
        ];
    }

    public function baseAmount(string|int|float $amount, array $context): string
    {
        $currencyCode = strtoupper((string) ($context['currency_code'] ?? $this->money->baseCurrency()));
        $exchangeRate = (string) ($context['exchange_rate'] ?? '1');

        return $this->money->convert(
            $amount,
            $currencyCode,
            $this->money->baseCurrency(),
            $context['effective_date'] ?? now()->toDateString(),
            $exchangeRate,
        )['amount'];
    }
}
