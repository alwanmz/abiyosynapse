<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockQualityBalance;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockQualityService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /** @return array<string, float> */
    public function quantities(Product $product, Warehouse $warehouse): array
    {
        $quantities = array_fill_keys(StockQualityBalance::STATES, 0.0);

        StockQualityBalance::withoutGlobalScopes()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->get()
            ->each(function (StockQualityBalance $balance) use (&$quantities): void {
                $quantities[$balance->quality_state] = (float) $balance->quantity;
            });

        return $quantities;
    }

    public function reservedQuantity(Product $product, Warehouse $warehouse): float
    {
        return (float) StockReservation::withoutGlobalScopes()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('status', 'reserved')
            ->sum('quantity');
    }

    public function availableForSale(Product $product, Warehouse $warehouse): float
    {
        $quantities = $this->quantities($product, $warehouse);

        return max(0, $quantities['approved'] - $this->reservedQuantity($product, $warehouse));
    }

    public function increase(Product $product, Warehouse $warehouse, string $state, float $quantity): void
    {
        $this->assertState($state);

        if ($quantity <= 0) {
            return;
        }

        $balance = $this->lockBalance($product, $warehouse, $state);
        $balance->increment('quantity', $quantity);
    }

    public function decrease(Product $product, Warehouse $warehouse, string $state, float $quantity): void
    {
        $this->assertState($state);

        if ($quantity <= 0) {
            return;
        }

        $balance = $this->lockBalance($product, $warehouse, $state);

        if ((float) $balance->quantity + 0.0001 < $quantity) {
            throw new RuntimeException("Insufficient {$state} stock for \"{$product->name}\" at \"{$warehouse->name}\".");
        }

        $balance->decrement('quantity', $quantity);
    }

    /**
     * @return array<int, array{lot: StockLot, quantity: float}>
     */
    public function allocateLots(Product $product, Warehouse $warehouse, float $quantity, string $state = 'approved', ?int $inspectionId = null): array
    {
        $this->assertState($state);

        $remaining = $quantity;
        $companyId = $this->companyId();
        // Lock individual reservation rows before grouping them in memory.
        // PostgreSQL does not permit FOR UPDATE on a grouped result set.
        $reservedByLot = StockReservation::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get(['stock_lot_id', 'quantity'])
            ->groupBy('stock_lot_id')
            ->map(fn (Collection $reservations): float => (float) $reservations->sum('quantity'));

        $lots = StockLot::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quality_state', $state)
            ->when($inspectionId !== null, fn ($query) => $query->where('quality_inspection_id', $inspectionId))
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }

            $free = max(0, (float) $lot->quantity_remaining - (float) ($reservedByLot[$lot->id] ?? 0));
            if ($free <= 0.0001) {
                continue;
            }

            $take = min($free, $remaining);
            $allocations[] = ['lot' => $lot, 'quantity' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0.0001) {
            throw new RuntimeException("Approved stock for \"{$product->name}\" at \"{$warehouse->name}\" is insufficient for this delivery.");
        }

        return $allocations;
    }

    /**
     * @param array<int, array{lot: StockLot, quantity: float}> $allocations
     */
    public function consumeLots(array $allocations): void
    {
        foreach ($allocations as $allocation) {
            /** @var StockLot $lot */
            $lot = StockLot::withoutGlobalScopes()->lockForUpdate()->findOrFail($allocation['lot']->id);
            $quantity = $allocation['quantity'];

            if ((float) $lot->quantity_remaining + 0.0001 < $quantity) {
                throw new RuntimeException("Stock lot #{$lot->id} no longer has enough quantity.");
            }

            $lot->decrement('quantity_remaining', $quantity);
        }
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function transition(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        string $fromState,
        string $toState,
        ?int $inspectionId = null,
        ?int $ncrId = null,
    ): Collection {
        $this->assertState($fromState);
        $this->assertState($toState);

        if ($quantity <= 0 || $fromState === $toState) {
            return new Collection();
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $fromState, $toState, $inspectionId, $ncrId) {
            $remaining = $quantity;
            $lots = StockLot::withoutGlobalScopes()
                ->where('company_id', $this->companyId())
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('quality_state', $fromState)
                ->where('quantity_remaining', '>', 0)
                ->when($inspectionId !== null, fn ($query) => $query->where('quality_inspection_id', $inspectionId))
                ->when($ncrId !== null, fn ($query) => $query->where('non_conformance_report_id', $ncrId))
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $movedLots = new Collection();
            foreach ($lots as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $take = min($remaining, (float) $lot->quantity_remaining);
                $remaining -= $take;

                if (abs($take - (float) $lot->quantity_remaining) < 0.0001) {
                    $lot->auditAs(auth()->id())->update([
                        'quality_state' => $toState,
                        'quality_inspection_id' => $inspectionId ?? $lot->quality_inspection_id,
                        'non_conformance_report_id' => $ncrId ?? $lot->non_conformance_report_id,
                    ]);
                    $movedLots->push($lot->fresh());
                    continue;
                }

                $lot->decrement('quantity_remaining', $take);
                $movedLots->push(StockLot::create([
                    'company_id' => $lot->company_id,
                    'product_id' => $lot->product_id,
                    'warehouse_id' => $lot->warehouse_id,
                    'received_at' => $lot->received_at,
                    'quantity_received' => $take,
                    'quantity_remaining' => $take,
                    'quality_state' => $toState,
                    'is_legacy' => $lot->is_legacy,
                    'unit_cost' => $lot->unit_cost,
                    'sourceable_type' => $lot->sourceable_type,
                    'sourceable_id' => $lot->sourceable_id,
                    'quality_inspection_id' => $inspectionId ?? $lot->quality_inspection_id,
                    'non_conformance_report_id' => $ncrId ?? $lot->non_conformance_report_id,
                    'parent_stock_lot_id' => $lot->id,
                ]));
            }

            if ($remaining > 0.0001) {
                throw new RuntimeException("Insufficient {$fromState} stock for this quality transition.");
            }

            $this->decrease($product, $warehouse, $fromState, $quantity);
            $this->increase($product, $warehouse, $toState, $quantity);

            return $movedLots;
        });
    }

    private function lockBalance(Product $product, Warehouse $warehouse, string $state): StockQualityBalance
    {
        $companyId = $this->companyId();

        $balance = StockQualityBalance::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quality_state', $state)
            ->lockForUpdate()
            ->first();

        return $balance ?? StockQualityBalance::create([
            'company_id' => $companyId,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quality_state' => $state,
            'quantity' => 0,
        ]);
    }

    private function assertState(string $state): void
    {
        if (! in_array($state, StockQualityBalance::STATES, true)) {
            throw new RuntimeException("Unknown stock quality state \"{$state}\".");
        }
    }

    private function companyId(): int
    {
        return $this->currentCompany->id()
            ?? throw new RuntimeException('A company context is required for stock quality operations.');
    }
}
