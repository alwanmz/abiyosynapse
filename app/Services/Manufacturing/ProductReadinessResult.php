<?php

namespace App\Services\Manufacturing;

class ProductReadinessResult
{
    /**
     * @param array<string, float> $quantities
     * @param array<int, string> $reasons
     */
    public function __construct(
        public readonly string $status,
        public readonly array $quantities,
        public readonly float $reservedQuantity,
        public readonly float $availableForSale,
        public readonly array $reasons,
        public readonly array $configuration,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'quantities' => $this->quantities,
            'reserved_quantity' => $this->reservedQuantity,
            'available_for_sale' => $this->availableForSale,
            'reasons' => $this->reasons,
            'configuration' => $this->configuration,
        ];
    }
}
