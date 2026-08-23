<?php

namespace App\Services\Sales;

use App\Models\Account;
use App\Models\DeliveryOrderLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\CurrencyDocumentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates a Sales Invoice against a Sales Order and immediately posts it
 * (blueprint §6, §14 event mapping: Sales Invoice -> AR/Revenue, COGS ->
 * COGS/Finished Goods Inventory). Unlike the purchasing side there's no
 * 3-way match gate — invoicing is capped at each SO line's delivered-but-
 * not-yet-invoiced quantity (enforced here) and posts unconditionally
 * once created. Each invoice line snapshots unit_cost from its delivered
 * lines' weighted-average cost so a later Sales Return (SalesReturnService)
 * can reverse Revenue/COGS at the same cost basis rather than re-deriving
 * it from current (possibly different) stock cost.
 */
class SalesInvoiceService
{
    private const AR_ACCOUNT_CODE = '1.1.3';
    private const REVENUE_ACCOUNT_CODE = '4.1';
    private const OUTPUT_TAX_ACCOUNT_CODE = '2.1.3';
    private const COGS_ACCOUNT_CODE = '5.1';
    private const FINISHED_GOODS_ACCOUNT_CODE = '1.1.6';
    private const QUANTITY_TOLERANCE = 0.0001;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly JournalPostingService $posting,
        private readonly CurrencyDocumentService $currencyDocuments,
    ) {
    }

    public function create(SalesOrder $order, array $input, array $lineInputs, ?User $creator = null): SalesInvoice
    {
        return DB::transaction(function () use ($order, $input, $lineInputs, $creator) {
            $currencyCode = $order->currency_code ?: $this->currentCompany->get()?->currency ?? 'IDR';
            $exchangeRate = $order->exchange_rate ?: '1';
            if (! $order->currency_code || ! $order->exchange_rate) {
                $order->forceFill(['currency_code' => $currencyCode, 'exchange_rate' => $exchangeRate])->save();
            }
            $invoice = SalesInvoice::create([
                'number' => $this->nextNumber(),
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_date' => $input['invoice_date'],
                'due_date' => $input['due_date'],
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'status' => 'posted',
                'created_by' => $creator?->id,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $totalCost = 0.0;

            foreach ($lineInputs as $lineInput) {
                /** @var SalesOrderLine $soLine */
                $soLine = SalesOrderLine::where('sales_order_id', $order->id)
                    ->where('id', $lineInput['sales_order_line_id'])
                    ->firstOrFail();

                $quantity = (float) $lineInput['quantity'];

                if ($quantity > $soLine->remainingToInvoice() + self::QUANTITY_TOLERANCE) {
                    throw new RuntimeException("Cannot invoice more than the delivered, uninvoiced quantity for product #{$soLine->product_id}.");
                }

                $unitPrice = (float) $soLine->unit_price;
                $lineSubtotal = $quantity * $unitPrice;
                $taxRate = (float) ($soLine->taxCode->rate ?? 0);
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $unitCost = $this->averageDeliveredUnitCost($soLine);

                $invoice->lines()->create([
                    'sales_order_line_id' => $soLine->id,
                    'product_id' => $soLine->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_price_base' => $this->currencyDocuments->baseAmount((string) $unitPrice, [
                        'currency_code' => $currencyCode,
                        'exchange_rate' => $exchangeRate,
                        'effective_date' => $input['invoice_date'],
                    ]),
                    'tax_amount' => $taxAmount,
                    'tax_amount_base' => $this->currencyDocuments->baseAmount((string) $taxAmount, [
                        'currency_code' => $currencyCode,
                        'exchange_rate' => $exchangeRate,
                        'effective_date' => $input['invoice_date'],
                    ]),
                    'unit_cost' => $unitCost,
                    'unit_cost_base' => $unitCost,
                ]);

                $soLine->increment('invoiced_quantity', $quantity);

                $subtotal += $lineSubtotal;
                $taxTotal += $taxAmount;
                $totalCost += $quantity * $unitCost;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
                'subtotal_base' => $this->currencyDocuments->baseAmount((string) $subtotal, [
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'effective_date' => $input['invoice_date'],
                ]),
                'tax_total_base' => $this->currencyDocuments->baseAmount((string) $taxTotal, [
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'effective_date' => $input['invoice_date'],
                ]),
                'total_base' => $this->currencyDocuments->baseAmount((string) ($subtotal + $taxTotal), [
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'effective_date' => $input['invoice_date'],
                ]),
            ]);

            $currency = $invoice->currency_code;
            $lines = [
                ['account_id' => $this->accountId(self::AR_ACCOUNT_CODE), 'debit' => (float) $invoice->total_base, 'amount_currency' => (float) $invoice->total, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate],
                ['account_id' => $this->accountId(self::REVENUE_ACCOUNT_CODE), 'credit' => (float) $invoice->subtotal_base, 'amount_currency' => -(float) $invoice->subtotal, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate],
            ];

            if ($taxTotal > 0) {
                $lines[] = ['account_id' => $this->accountId(self::OUTPUT_TAX_ACCOUNT_CODE), 'credit' => (float) $invoice->tax_total_base, 'amount_currency' => -(float) $invoice->tax_total, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate];
            }

            $this->posting->post(
                description: "Sales invoice {$invoice->number}",
                lines: $lines,
                sourceable: $invoice,
            );

            if ($totalCost > 0) {
                $baseCurrency = $this->currentCompany->get()?->currency ?? 'IDR';
                $this->posting->post(
                    description: "COGS for sales invoice {$invoice->number}",
                    lines: [
                        ['account_id' => $this->accountId(self::COGS_ACCOUNT_CODE), 'debit' => $totalCost, 'amount_currency' => $totalCost, 'currency_code' => $baseCurrency],
                        ['account_id' => $this->accountId(self::FINISHED_GOODS_ACCOUNT_CODE), 'credit' => $totalCost, 'amount_currency' => -$totalCost, 'currency_code' => $baseCurrency],
                    ],
                    sourceable: $invoice,
                );
            }

            return $invoice->fresh('lines');
        });
    }

    /**
     * Weighted-average unit_cost across this SO line's shipped delivery
     * lines (there may be more than one partial delivery). Falls back to
     * the product's standard_cost if nothing has shipped yet — shouldn't
     * happen in practice since invoicing is capped at delivered quantity,
     * but keeps this defensive rather than dividing by zero.
     */
    private function averageDeliveredUnitCost(SalesOrderLine $soLine): float
    {
        $deliveryLines = DeliveryOrderLine::where('sales_order_line_id', $soLine->id)
            ->whereHas('deliveryOrder', fn ($query) => $query->where('status', 'shipped'))
            ->get();

        $totalQuantity = (float) $deliveryLines->sum('quantity');

        if ($totalQuantity <= 0) {
            return (float) $soLine->product->standard_cost;
        }

        $totalCost = $deliveryLines->sum(fn (DeliveryOrderLine $line) => (float) $line->quantity * (float) $line->unit_cost);

        return $totalCost / $totalQuantity;
    }

    private function nextNumber(): string
    {
        $prefix = 'SO-INV-' . now()->format('Y') . '-';

        $lastNumber = SalesInvoice::where('number', 'like', $prefix . '%')
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
