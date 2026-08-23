<?php

namespace App\Services\Sales;

use App\Models\SalesOrder;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\CurrencyDocumentService;
use App\Services\CurrentCompany;
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

        app(ApprovalWorkflowService::class)->assertCanApprove($order, $approver);

        $order->auditAs($approver)->update([
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

        $currencyCode = $order->currency_code ?: app(CurrentCompany::class)->get()?->currency ?? 'IDR';
        $exchangeRate = $order->exchange_rate ?: '1';
        $order->update([
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
            'subtotal_base' => app(CurrencyDocumentService::class)->baseAmount((string) $subtotal, [
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'effective_date' => $order->order_date?->toDateString() ?? now()->toDateString(),
            ]),
            'tax_total_base' => app(CurrencyDocumentService::class)->baseAmount((string) $taxTotal, [
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'effective_date' => $order->order_date?->toDateString() ?? now()->toDateString(),
            ]),
            'total_base' => app(CurrencyDocumentService::class)->baseAmount((string) ($subtotal + $taxTotal), [
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'effective_date' => $order->order_date?->toDateString() ?? now()->toDateString(),
            ]),
        ]);
    }
}
