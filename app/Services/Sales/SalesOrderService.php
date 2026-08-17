<?php

namespace App\Services\Sales;

use App\Models\SalesOrder;
use App\Models\User;
use RuntimeException;

/**
 * Drives a Sales Order through Draft -> Approval -> Approved -> Partial/
 * Fulfilled -> Closed (blueprint §6, §17, mirrors PurchaseOrderService).
 * Delivering (and the resulting inventory/GL postings) is handled by
 * DeliveryOrderService — this service only owns the SO document lifecycle
 * and its line totals.
 */
class SalesOrderService
{
    public function submitForApproval(SalesOrder $order): SalesOrder
    {
        if (! $order->isEditable()) {
            throw new RuntimeException("Sales order {$order->number} is not editable.");
        }

        $order->update(['status' => 'approval']);

        return $order->fresh();
    }

    public function approve(SalesOrder $order, User $approver): SalesOrder
    {
        if ($order->status !== 'approval') {
            throw new RuntimeException("Sales order {$order->number} must be pending approval before it can be approved.");
        }

        $order->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    public function close(SalesOrder $order): SalesOrder
    {
        if (! $order->isApproved()) {
            throw new RuntimeException("Sales order {$order->number} cannot be closed from its current status.");
        }

        $order->update(['status' => 'closed', 'closed_at' => now()]);

        return $order->fresh();
    }

    public function recalculateTotals(SalesOrder $order): void
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
}
