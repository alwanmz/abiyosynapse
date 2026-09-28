<?php

namespace App\Services\Billing;

final class PaymentResult
{
    /**
     * @param  'paid'|'failed'|'expired'|'pending'  $status
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly ?string $providerRef = null,
        public readonly array $payload = [],
    ) {
    }
}
