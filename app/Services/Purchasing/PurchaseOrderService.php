<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives a Purchase Order through Draft -> Approval -> Approved -> Sent ->
 * Partial/Received -> Closed (blueprint §8, §17). Receiving itself (and the
 * resulting inventory/GL postings) is handled by GoodsReceiptService — this
 * service only owns the PO document lifecycle and its line totals.
 */
class PurchaseOrderService
{
    /**
     * Convert approved Purchase Request lines into a new draft PO. Each
     * line's remaining (quantity - converted_quantity) becomes the PO line
     * quantity, and the source lines are marked converted so a PR can be
     * split across multiple POs (e.g. different suppliers per product)
     * without double-ordering the same shortage.
     */
    public function createFromRequest(
        \App\Models\PurchaseRequest $request,
        int $supplierId,
        int $warehouseId,
        array $lineInputs,
        ?User $creator = null,
    ): PurchaseOrder {
        if (! $request->isApproved()) {
            throw new RuntimeException("Purchase request {$request->number} must be approved before it can be converted to a PO.");
        }

        return DB::transaction(function () use ($request, $supplierId, $warehouseId, $lineInputs, $creator) {
            $order = PurchaseOrder::create([
                'number' => $this->nextNumber(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'purchase_request_id' => $request->id,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'created_by' => $creator?->id,
            ]);

            foreach ($lineInputs as $input) {
                /** @var PurchaseRequestLine $prLine */
                $prLine = PurchaseRequestLine::where('purchase_request_id', $request->id)
                    ->where('id', $input['purchase_request_line_id'])
                    ->firstOrFail();

                $quantity = (float) $input['quantity'];

                if ($quantity > $prLine->remainingQuantity() + 0.0001) {
                    throw new RuntimeException("Cannot order more than the remaining requested quantity for product #{$prLine->product_id}.");
                }

                $order->lines()->create([
                    'product_id' => $prLine->product_id,
                    'tax_code_id' => $input['tax_code_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => (float) $input['unit_price'],
                ]);

                $prLine->increment('converted_quantity', $quantity);
            }

            $this->recalculateTotals($order);

            $allConverted = $request->lines->every(fn (PurchaseRequestLine $line) => $line->fresh()->remainingQuantity() <= 0.0001);
            if ($allConverted) {
                $request->update(['status' => 'converted']);
            }

            return $order->fresh('lines');
        });
    }

    public function submitForApproval(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->isEditable()) {
            throw new RuntimeException("Purchase order {$order->number} is not editable.");
        }

        $order->update(['status' => 'approval']);

        return $order->fresh();
    }

    public function approve(PurchaseOrder $order, User $approver): PurchaseOrder
    {
        if ($order->status !== 'approval') {
            throw new RuntimeException("Purchase order {$order->number} must be pending approval before it can be approved.");
        }

        $order->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    public function send(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'approved') {
            throw new RuntimeException("Purchase order {$order->number} must be approved before it can be sent.");
        }

        $order->update(['status' => 'sent', 'sent_at' => now()]);

        return $order->fresh();
    }

    public function close(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->isApproved()) {
            throw new RuntimeException("Purchase order {$order->number} cannot be closed from its current status.");
        }

        $order->update(['status' => 'closed', 'closed_at' => now()]);

        return $order->fresh();
    }

    public function recalculateTotals(PurchaseOrder $order): void
    {
        $order->load('lines.taxCode');

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($order->lines as $line) {
            $lineSubtotal = $line->lineSubtotal();
            $subtotal += $lineSubtotal;
            $taxTotal += $lineSubtotal * ((float) ($line->taxCode->rate ?? 0) / 100);
        }

        $order->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ]);
    }

    private function nextNumber(): string
    {
        $prefix = 'PO-' . now()->format('Y') . '-';

        $lastNumber = PurchaseOrder::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
