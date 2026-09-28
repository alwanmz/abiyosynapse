<?php

namespace App\Services\Sales;

use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Inventory\StockReservationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

/**
 * Drives Delivery Order creation and shipment (blueprint §6, §12, mirrors
 * GoodsReceiptService but simpler — no inspection step on the outbound
 * side). create() records the delivery lines against an approved SO
 * without touching stock/GL yet (status draft); ship() issues the actual
 * Finished Goods stock via InventoryValuationService::issue() and posts
 * COGS (debit) / Finished Goods (credit) using the cost returned by the
 * valuation layer, then bumps each SalesOrderLine.delivered_quantity so
 * the SO's partial/fulfilled status and the eventual Sales Invoice's
 * remaining-to-invoice cap both see the true delivered quantity.
 */
class DeliveryOrderService
{
    private const FINISHED_GOODS_ACCOUNT_ROLE = AccountRole::FinishedGoodsInventory;
    private const COGS_ACCOUNT_ROLE = AccountRole::Cogs;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly InventoryValuationService $valuation,
        private readonly StockReservationService $reservations,
        private readonly JournalPostingService $posting,
    ) {
    }

    public function create(SalesOrder $order, array $lineInputs, ?User $creator = null): DeliveryOrder
    {
        if (! $order->isApproved()) {
            throw new RuntimeException("Sales order {$order->number} must be approved before it can be delivered.");
        }

        return DB::transaction(function () use ($order, $lineInputs) {
            $delivery = DeliveryOrder::create([
                'number' => $this->nextNumber(),
                'sales_order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id,
                'delivery_date' => now()->toDateString(),
                'status' => 'draft',
            ]);

            foreach ($lineInputs as $input) {
                /** @var SalesOrderLine $soLine */
                $soLine = SalesOrderLine::where('sales_order_id', $order->id)
                    ->where('id', $input['sales_order_line_id'])
                    ->firstOrFail();

                $quantity = (float) $input['quantity'];

                if ($quantity > $soLine->remainingToDeliver() + 0.0001) {
                    throw new RuntimeException("Cannot deliver more than the remaining ordered quantity for product #{$soLine->product_id}.");
                }

                $line = $delivery->lines()->create([
                    'sales_order_line_id' => $soLine->id,
                    'product_id' => $soLine->product_id,
                    'quantity' => $quantity,
                ]);

                $line->load('product');
                if (! $line->product->isActive()) {
                    throw new RuntimeException("Inactive product \"{$line->product->name}\" cannot be added to a delivery order.");
                }
            }

            $delivery = $delivery->fresh(['lines.product', 'warehouse']);
            $this->reservations->reserve($delivery);

            return $delivery->fresh(['lines.reservations', 'warehouse']);
        });
    }

    public function ship(DeliveryOrder $delivery, ?User $shipper = null): DeliveryOrder
    {
        if (! $delivery->isDraft()) {
            throw new RuntimeException("Delivery order {$delivery->number} has already been shipped.");
        }

        return DB::transaction(function () use ($delivery, $shipper) {
            $delivery->loadMissing(['lines.product', 'lines.salesOrderLine', 'lines.reservations.lot', 'warehouse', 'salesOrder.lines']);
            $this->reservations->reserve($delivery);
            $totalCost = 0.0;

            foreach ($delivery->lines as $line) {
                $allocations = $this->reservations->allocationsFor($line);
                if ($line->product->type !== 'service' && $allocations === []) {
                    throw new RuntimeException("Delivery order {$delivery->number} has no approved stock reservation for \"{$line->product->name}\".");
                }

                if ($line->product->type === 'service') {
                    $line->salesOrderLine->increment('delivered_quantity', (float) $line->quantity);
                    continue;
                }

                $result = $this->valuation->issue(
                    $line->product,
                    $delivery->warehouse,
                    (float) $line->quantity,
                    $delivery,
                    'out',
                    "Delivery order {$delivery->number} shipped",
                    'approved',
                    $allocations,
                );

                $unitCost = (float) $line->quantity > 0 ? $result['total_cost'] / (float) $line->quantity : 0.0;
                $line->update(['unit_cost' => $unitCost]);

                $line->salesOrderLine->increment('delivered_quantity', (float) $line->quantity);

                $totalCost += $result['total_cost'];
            }

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "Delivery order {$delivery->number} shipped",
                    lines: [
                        ['account_id' => $this->accountId(self::COGS_ACCOUNT_ROLE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_ROLE), 'credit' => $totalCost],
                    ],
                    sourceable: $delivery,
                );
            }

            $order = $delivery->salesOrder;
            $order->update(['status' => $order->fresh('lines')->isFullyDelivered() ? 'fulfilled' : 'partial']);

            $delivery->update(['status' => 'shipped', 'shipped_by' => $shipper?->id]);
            $this->reservations->consume($delivery);

            return $delivery->fresh('lines');
        });
    }

    public function cancel(DeliveryOrder $delivery, User $actor): DeliveryOrder
    {
        if (! $delivery->isDraft()) {
            throw new RuntimeException("Only a draft delivery order can be cancelled.");
        }

        return DB::transaction(function () use ($delivery, $actor): DeliveryOrder {
            $this->reservations->release($delivery);
            $delivery->auditAs($actor)->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return $delivery->fresh('reservations');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'DO-' . now()->format('Y') . '-';

        $lastNumber = DeliveryOrder::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(AccountRole $role): int
    {
        return app(AccountRoleResolver::class)->id($role);
    }
}
