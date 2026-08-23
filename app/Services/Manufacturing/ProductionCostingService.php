<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionOrder;
use App\Models\StockMovement;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Calculates and stores a production order's standard-vs-actual cost.
 *
 * Material cost comes from the valuation result captured during issue.
 * Conversion cost uses each work center's cost_rate_per_minute and the
 * planned/actual operation minutes. The snapshot is deliberately separate
 * from the legacy completion posting so existing production orders remain
 * financially compatible while gaining variance visibility.
 */
class ProductionCostingService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * @return array<string, float>
     */
    public function calculate(ProductionOrder $order): array
    {
        $order->loadMissing([
            'components.component',
            'operations.workCenter',
        ]);

        $standardMaterial = $order->components->sum(
            fn ($component) => (float) $component->required_quantity
                * (float) ($component->standard_unit_cost ?: $component->component->standard_cost)
        );

        $actualMaterial = $order->components->sum(function ($component) use ($order) {
            $capturedCost = (float) $component->actual_material_cost;

            if ($capturedCost > 0) {
                return $capturedCost;
            }

            // Backward-compatible fallback for orders released before the
            // costing snapshot columns existed.
            return (float) StockMovement::withoutGlobalScopes()
                ->where('company_id', $order->company_id)
                ->where('sourceable_type', $order->getMorphClass())
                ->where('sourceable_id', $order->id)
                ->where('product_id', $component->component_id)
                ->where('type', 'out')
                ->sum('total_cost');
        });

        $standardConversion = $order->operations->sum(
            fn ($operation) => (float) ($operation->planned_cost
                ?: (float) $operation->planned_minutes * (float) $operation->workCenter->cost_rate_per_minute)
        );

        $actualConversion = $order->operations->sum(
            fn ($operation) => (float) ($operation->actual_cost
                ?: (float) ($operation->actual_minutes ?? 0) * (float) $operation->workCenter->cost_rate_per_minute)
        );

        $standardTotal = $standardMaterial + $standardConversion;
        $actualTotal = $actualMaterial + $actualConversion;
        $variance = $actualTotal - $standardTotal;

        return [
            'standard_material_cost' => round($standardMaterial, 2),
            'actual_material_cost' => round($actualMaterial, 2),
            'standard_conversion_cost' => round($standardConversion, 2),
            'actual_conversion_cost' => round($actualConversion, 2),
            'standard_total_cost' => round($standardTotal, 2),
            'actual_total_cost' => round($actualTotal, 2),
            'variance_amount' => round($variance, 2),
            'variance_percentage' => $standardTotal > 0 ? round($variance / $standardTotal * 100, 4) : 0,
        ];
    }

    public function cost(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, ['completed', 'closed'], true)) {
            throw new RuntimeException("Production order {$order->number} must be completed before it can be costed.");
        }

        return DB::transaction(function () use ($order) {
            $snapshot = $this->calculate($order);

            $order->update([
                ...$snapshot,
                'cost_currency_code' => $this->currentCompany->get()?->currency ?? 'IDR',
                'cost_exchange_rate' => 1,
                'standard_total_cost_base' => $snapshot['standard_total_cost'],
                'actual_total_cost_base' => $snapshot['actual_total_cost'],
                'variance_amount_base' => $snapshot['variance_amount'],
                'costed_at' => now(),
            ]);

            return $order->fresh(['components', 'operations']);
        });
    }
}
