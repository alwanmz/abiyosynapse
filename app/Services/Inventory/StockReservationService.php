<?php

namespace App\Services\Inventory;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockReservationService
{
    public function __construct(
        private readonly StockQualityService $quality,
    ) {
    }

    public function reserve(DeliveryOrder $delivery): void
    {
        if (! $delivery->isDraft()) {
            throw new RuntimeException("Delivery order {$delivery->number} is not reservable.");
        }

        DB::transaction(function () use ($delivery): void {
            $delivery->loadMissing('lines.product', 'warehouse');

            if ($delivery->reservations()->where('status', 'reserved')->exists()) {
                return;
            }

            foreach ($delivery->lines as $line) {
                if ($line->product->type === 'service') {
                    continue;
                }

                foreach ($this->quality->allocateLots($line->product, $delivery->warehouse, (float) $line->quantity) as $allocation) {
                    StockReservation::create([
                        'company_id' => $delivery->company_id,
                        'product_id' => $line->product_id,
                        'warehouse_id' => $delivery->warehouse_id,
                        'stock_lot_id' => $allocation['lot']->id,
                        'delivery_order_id' => $delivery->id,
                        'delivery_order_line_id' => $line->id,
                        'quantity' => $allocation['quantity'],
                        'status' => 'reserved',
                    ]);
                }
            }
        });
    }

    /** @return array<int, array{lot: \App\Models\StockLot, quantity: float}> */
    public function allocationsFor(DeliveryOrderLine $line): array
    {
        return $line->reservations()
            ->where('status', 'reserved')
            ->with('lot')
            ->get()
            ->map(fn (StockReservation $reservation) => [
                'lot' => $reservation->lot,
                'quantity' => (float) $reservation->quantity,
            ])
            ->filter(fn (array $allocation) => $allocation['lot'] !== null)
            ->values()
            ->all();
    }

    public function consume(DeliveryOrder $delivery): void
    {
        $delivery->reservations()
            ->where('status', 'reserved')
            ->update(['status' => 'consumed', 'updated_at' => now()]);
    }

    public function release(DeliveryOrder $delivery): void
    {
        $delivery->reservations()
            ->where('status', 'reserved')
            ->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
    }
}
