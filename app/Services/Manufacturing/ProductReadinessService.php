<?php

namespace App\Services\Manufacturing;

use App\Models\CompanyCurrency;
use App\Models\Bom;
use App\Models\Product;
use App\Models\TaxCode;
use App\Models\Routing;
use App\Models\Warehouse;
use App\Services\Inventory\StockQualityService;

class ProductReadinessService
{
    public function __construct(
        private readonly StockQualityService $quality,
    ) {
    }

    public function evaluate(Product $product, Warehouse $warehouse): ProductReadinessResult
    {
        $product->loadMissing('bom', 'routing');
        $bom = $product->bom ?? Bom::where('product_id', $product->id)->where('status', 'active')->effectiveOn(now()->toDateString())->first();
        $routing = $product->routing ?? Routing::where('product_id', $product->id)->where('status', 'active')->first();
        $quantities = $this->quality->quantities($product, $warehouse);
        $reserved = $this->quality->reservedQuantity($product, $warehouse);
        $available = max(0, $quantities['approved'] - $reserved);

        $configuration = [
            'product_active' => $product->isActive(),
            'bom_active' => $product->type !== 'manufactured' || $bom?->isActive() === true,
            'routing_active' => $product->type !== 'manufactured' || $routing?->isActive() === true,
            'selling_price_available' => (float) $product->selling_price > 0,
            'tax_available' => TaxCode::where('is_active', true)->exists(),
            'currency_available' => CompanyCurrency::where('is_active', true)->exists(),
        ];

        $reasons = [];
        foreach ($configuration as $key => $valid) {
            if (! $valid) {
                $reasons[] = $key;
            }
        }

        if ($reasons !== []) {
            $status = 'not_configured';
        } elseif ($product->type === 'service') {
            $status = 'ready_for_sale';
        } elseif ($available > 0.0001) {
            $status = 'ready_for_sale';
        } elseif ($quantities['rework'] > 0.0001) {
            $status = 'rework_required';
        } elseif ($quantities['hold'] > 0.0001 || $quantities['rejected'] > 0.0001) {
            $status = 'quality_hold';
        } elseif ($quantities['pending'] > 0.0001) {
            $status = 'pending_qc';
        } else {
            $status = 'out_of_stock';
        }

        if ($status !== 'ready_for_sale' && $available <= 0.0001 && $product->type !== 'service') {
            $reasons[] = 'no_available_stock';
        }

        return new ProductReadinessResult($status, $quantities, $reserved, $available, array_values(array_unique($reasons)), $configuration);
    }
}
