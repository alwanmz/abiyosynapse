<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles stock quantity + cost bookkeeping for both valuation methods a
 * Product can be configured with (Product::valuation_method):
 *
 *  - 'average': a single running weighted-average cost on StockLevel.
 *  - 'fifo': cost layers on StockLot, consumed oldest-first; StockLevel's
 *    average_unit_cost becomes a derived display figure (total lot value
 *    / total lot qty) rather than the value actually used to cost issues.
 *
 * This service only moves quantity and cost — it does NOT post to the GL
 * itself. Callers (Fase 3+ modules: Purchase Receipt, Production Order,
 * Sales Invoice, etc.) know which accounts a movement should hit and call
 * JournalPostingService themselves with the cost this service returns.
 */
class InventoryValuationService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly StockQualityService $quality,
    ) {
    }

    /**
     * Record incoming stock (purchase receipt, production output, positive
     * adjustment). $unitCost is required — the caller always knows it
     * (PO price, production cost, or a manually entered opname cost).
     */
    public function receive(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        float $unitCost,
        ?Model $sourceable = null,
        string $type = 'in',
        ?string $notes = null,
        string $qualityState = 'approved',
        array $qualityContext = [],
    ): StockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Received quantity must be greater than zero.');
        }

        $companyId = $this->requireCompanyId();

        return DB::transaction(function () use ($companyId, $product, $warehouse, $quantity, $unitCost, $sourceable, $type, $notes, $qualityState, $qualityContext) {
            $level = $this->lockOrCreateLevel($companyId, $product, $warehouse);

            $newQty = (float) $level->quantity_on_hand + $quantity;
            $newAvgCost = $newQty > 0
                ? (((float) $level->quantity_on_hand * (float) $level->average_unit_cost) + ($quantity * $unitCost)) / $newQty
                : 0;

            $level->update([
                'quantity_on_hand' => $newQty,
                'average_unit_cost' => $newAvgCost,
            ]);

            // Quality segregation needs a traceable layer for every receipt,
            // including average-cost products. Costing still follows the
            // product valuation method below; these layers are availability
            // and quality controls, not a change to average valuation.
            $lot = StockLot::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'received_at' => now(),
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'quality_state' => $qualityState,
                'unit_cost' => $unitCost,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'quality_inspection_id' => $qualityContext['quality_inspection_id'] ?? null,
                'non_conformance_report_id' => $qualityContext['non_conformance_report_id'] ?? null,
            ]);

            $this->quality->increase($product, $warehouse, $qualityState, $quantity);

            return StockMovement::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'stock_lot_id' => $lot->id,
                'type' => $type,
                'quality_state' => $qualityState,
                'quality_event' => 'received',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'balance_quantity' => $newQty,
                'notes' => $notes,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Record outgoing stock (sale, material consumption, negative
     * adjustment). Returns the movement plus the actual cost consumed
     * (weighted-average for 'average' products, FIFO-layer cost for
     * 'fifo' products) so the caller can post the correct GL amount.
     *
     * @return array{movement: StockMovement, total_cost: float}
     */
    public function issue(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        ?Model $sourceable = null,
        string $type = 'out',
        ?string $notes = null,
        string $qualityState = 'approved',
        array $lotAllocations = [],
    ): array {
        if ($quantity <= 0) {
            throw new RuntimeException('Issued quantity must be greater than zero.');
        }

        $companyId = $this->requireCompanyId();

        return DB::transaction(function () use ($companyId, $product, $warehouse, $quantity, $sourceable, $type, $notes, $qualityState, $lotAllocations) {
            $level = $this->lockOrCreateLevel($companyId, $product, $warehouse);

            if ((float) $level->quantity_on_hand < $quantity) {
                throw new RuntimeException(
                    "Insufficient stock for \"{$product->name}\" at \"{$warehouse->name}\": have {$level->quantity_on_hand}, need {$quantity}.",
                );
            }

            $allocations = $lotAllocations !== []
                ? $lotAllocations
                : $this->quality->allocateLots($product, $warehouse, $quantity, $qualityState);

            $allocatedQuantity = array_sum(array_map(fn (array $allocation) => $allocation['quantity'], $allocations));
            if (abs($allocatedQuantity - $quantity) > 0.0001) {
                throw new RuntimeException('Stock lot allocations do not match the requested issue quantity.');
            }

            foreach ($allocations as $allocation) {
                if ($allocation['lot']->quality_state !== $qualityState) {
                    throw new RuntimeException('A stock issue may only consume lots from the requested quality state.');
                }
            }

            $totalCost = $product->usesFifo()
                ? array_sum(array_map(fn (array $allocation) => $allocation['quantity'] * (float) $allocation['lot']->unit_cost, $allocations))
                : $quantity * (float) $level->average_unit_cost;

            $this->quality->consumeLots($allocations);
            $this->quality->decrease($product, $warehouse, $qualityState, $quantity);

            $newQty = (float) $level->quantity_on_hand - $quantity;

            // Average cost per unit is unchanged by an issue — only the
            // quantity shrinks. For FIFO products this field is a display
            // derivation refreshed from remaining lot value below.
            $level->update(['quantity_on_hand' => $newQty]);

            if ($product->usesFifo()) {
                $this->refreshFifoDisplayAverage($level);
            }

            $unitCost = $quantity > 0 ? $totalCost / $quantity : 0;

            $movement = StockMovement::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'stock_lot_id' => count($allocations) === 1 ? $allocations[0]['lot']->id : null,
                'type' => $type,
                'quality_state' => $qualityState,
                'quality_event' => 'issued',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'balance_quantity' => $newQty,
                'notes' => $notes,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'created_by' => auth()->id(),
            ]);

            return ['movement' => $movement, 'total_cost' => $totalCost];
        });
    }

    /**
     * Adjust stock to a target quantity (used by Stock Opname). Internally
     * just calls receive()/issue() for the delta so the same lot/average
     * logic applies — a shrinkage still needs FIFO layers consumed, a
     * growth still needs a cost basis.
     */
    public function adjustTo(
        Product $product,
        Warehouse $warehouse,
        float $countedQuantity,
        ?Model $sourceable = null,
        ?string $notes = null,
    ): ?StockMovement {
        $companyId = $this->requireCompanyId();
        $level = $this->lockOrCreateLevel($companyId, $product, $warehouse);

        $delta = $countedQuantity - (float) $level->quantity_on_hand;

        if (abs($delta) < 0.0001) {
            return null;
        }

        if ($delta > 0) {
            $costBasis = (float) $level->average_unit_cost ?: (float) $product->standard_cost;

            return $this->receive($product, $warehouse, $delta, $costBasis, $sourceable, 'in', $notes);
        }

        return $this->issue($product, $warehouse, abs($delta), $sourceable, 'out', $notes)['movement'];
    }

    private function refreshFifoDisplayAverage(StockLevel $level): void
    {
        $lots = StockLot::withoutGlobalScopes()
            ->where('company_id', $level->company_id)
            ->where('product_id', $level->product_id)
            ->where('warehouse_id', $level->warehouse_id)
            ->where('quantity_remaining', '>', 0)
            ->get();

        $totalQty = (float) $lots->sum('quantity_remaining');
        $totalValue = (float) $lots->sum(fn (StockLot $lot) => (float) $lot->quantity_remaining * (float) $lot->unit_cost);

        $level->update([
            'average_unit_cost' => $totalQty > 0 ? $totalValue / $totalQty : 0,
        ]);
    }

    private function lockOrCreateLevel(int $companyId, Product $product, Warehouse $warehouse): StockLevel
    {
        return StockLevel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first() ?? StockLevel::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity_on_hand' => 0,
                'average_unit_cost' => 0,
            ]);
    }

    private function requireCompanyId(): int
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Cannot record stock movement without a resolved company context.');
        }

        return $companyId;
    }
}
