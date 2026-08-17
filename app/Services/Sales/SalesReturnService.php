<?php

namespace App\Services\Sales;

use App\Models\Account;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a Sales Return against a posted Sales Invoice (blueprint §6,
 * mirrors the purchasing side's return-to-supplier concept but for
 * customers). Returned quantity is capped at each invoice line's
 * not-yet-returned quantity. Returning goods does two things at once:
 *
 *   1. Receives the returned quantity back into Finished Goods stock at
 *      the SAME unit_cost the original sale shipped at (snapshotted on
 *      the invoice line by SalesInvoiceService) — reversing the COGS
 *      entry at its original cost basis rather than current stock cost,
 *      which could have drifted since the sale.
 *   2. Reverses AR/Revenue/PPN Keluaran for the returned amount (credit
 *      AR since the customer no longer owes for the returned units,
 *      debit Revenue to back out the recognized sale) and reverses COGS
 *      (credit COGS, debit Finished Goods) for the same units.
 */
class SalesReturnService
{
    private const AR_ACCOUNT_CODE = '1.1.3';
    private const REVENUE_ACCOUNT_CODE = '4.1';
    private const OUTPUT_TAX_ACCOUNT_CODE = '2.1.3';
    private const COGS_ACCOUNT_CODE = '5.1';
    private const FINISHED_GOODS_ACCOUNT_CODE = '1.1.6';
    private const QUANTITY_TOLERANCE = 0.0001;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly InventoryValuationService $valuation,
        private readonly JournalPostingService $posting,
    ) {
    }

    public function create(SalesInvoice $invoice, array $input, array $lineInputs, ?User $creator = null): SalesReturn
    {
        return DB::transaction(function () use ($invoice, $input, $lineInputs, $creator) {
            $return = SalesReturn::create([
                'number' => $this->nextNumber(),
                'sales_invoice_id' => $invoice->id,
                'warehouse_id' => $input['warehouse_id'],
                'return_date' => $input['return_date'],
                'reason' => $input['reason'] ?? null,
                'created_by' => $creator?->id,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $totalCost = 0.0;

            foreach ($lineInputs as $lineInput) {
                /** @var SalesInvoiceLine $invoiceLine */
                $invoiceLine = SalesInvoiceLine::where('sales_invoice_id', $invoice->id)
                    ->where('id', $lineInput['sales_invoice_line_id'])
                    ->firstOrFail();

                $quantity = (float) $lineInput['quantity'];

                if ($quantity > $invoiceLine->remainingToReturn() + self::QUANTITY_TOLERANCE) {
                    throw new RuntimeException("Cannot return more than the remaining returnable quantity for product #{$invoiceLine->product_id}.");
                }

                $lineSubtotal = $quantity * (float) $invoiceLine->unit_price;
                $taxAmount = (float) $invoiceLine->quantity > 0
                    ? $quantity * ((float) $invoiceLine->tax_amount / (float) $invoiceLine->quantity)
                    : 0.0;

                $return->lines()->create([
                    'sales_invoice_line_id' => $invoiceLine->id,
                    'product_id' => $invoiceLine->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $invoiceLine->unit_price,
                    'tax_amount' => $taxAmount,
                    'unit_cost' => $invoiceLine->unit_cost,
                ]);

                $this->valuation->receive(
                    $invoiceLine->product,
                    $return->warehouse,
                    $quantity,
                    (float) $invoiceLine->unit_cost,
                    $return,
                    'in',
                    "Sales return {$return->number}",
                );

                $subtotal += $lineSubtotal;
                $taxTotal += $taxAmount;
                $totalCost += $quantity * (float) $invoiceLine->unit_cost;
            }

            $return->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
            ]);

            $lines = [
                ['account_id' => $this->accountId(self::REVENUE_ACCOUNT_CODE), 'debit' => $subtotal],
            ];

            if ($taxTotal > 0) {
                $lines[] = ['account_id' => $this->accountId(self::OUTPUT_TAX_ACCOUNT_CODE), 'debit' => $taxTotal];
            }

            $lines[] = ['account_id' => $this->accountId(self::AR_ACCOUNT_CODE), 'credit' => $subtotal + $taxTotal];

            $this->posting->post(
                description: "Sales return {$return->number}",
                lines: $lines,
                sourceable: $return,
            );

            if ($totalCost > 0) {
                $this->posting->post(
                    description: "COGS reversal for sales return {$return->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_CODE), 'debit' => $totalCost],
                        ['account_id' => $this->accountId(self::COGS_ACCOUNT_CODE), 'credit' => $totalCost],
                    ],
                    sourceable: $return,
                );
            }

            return $return->fresh('lines');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'SR-' . now()->format('Y') . '-';

        $lastNumber = SalesReturn::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(string $code): int
    {
        $companyId = $this->currentCompany->id();
        $account = Account::where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Chart of accounts is missing the expected account \"{$code}\" for sales postings.");
        }

        return $account->id;
    }
}
