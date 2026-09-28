<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\CurrencyDocumentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

/**
 * Drives Supplier Invoice -> 3-Way Match -> AP (blueprint §8, §12).
 *
 * The 3-way match compares, per line: invoice quantity/price against the
 * PO's ordered quantity/price, and against the total accepted quantity
 * already put away via Goods Receipt (Fase 3.5 Incoming QC already
 * excluded rejects from that accepted total, so a supplier can't be paid
 * for units that never passed inspection). A match within tolerance posts
 * GRNI (debit) / Accounts Payable (credit) plus PPN Masukan (debit) for
 * the tax portion; a mismatch flags the invoice as disputed and withholds
 * the AP posting until someone resolves it.
 */
class SupplierInvoiceService
{
    private const GRNI_ACCOUNT_ROLE = AccountRole::Grni;
    private const AP_ACCOUNT_ROLE = AccountRole::AccountsPayable;
    private const INPUT_TAX_ACCOUNT_ROLE = AccountRole::InputTax;
    private const QUANTITY_TOLERANCE = 0.0001;
    private const PRICE_TOLERANCE = 0.01;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly JournalPostingService $posting,
        private readonly CurrencyDocumentService $currencyDocuments,
    ) {
    }

    public function create(PurchaseOrder $order, array $input, array $lineInputs, ?User $creator = null): SupplierInvoice
    {
        return DB::transaction(function () use ($order, $input, $lineInputs, $creator) {
            $currencyCode = $order->currency_code ?: $this->currentCompany->get()?->currency ?? 'IDR';
            $exchangeRate = $order->exchange_rate ?: '1';
            if (! $order->currency_code || ! $order->exchange_rate) {
                $order->forceFill(['currency_code' => $currencyCode, 'exchange_rate' => $exchangeRate])->save();
            }
            $invoice = SupplierInvoice::create([
                'number' => $this->nextNumber(),
                'supplier_reference' => $input['supplier_reference'] ?? null,
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'invoice_date' => $input['invoice_date'],
                'due_date' => $input['due_date'],
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'status' => 'pending_match',
                'created_by' => $creator?->id,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lineInputs as $lineInput) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = PurchaseOrderLine::where('purchase_order_id', $order->id)
                    ->where('id', $lineInput['purchase_order_line_id'])
                    ->firstOrFail();

                $quantity = (float) $lineInput['quantity'];

                if ($quantity > $poLine->remainingToInvoice() + self::QUANTITY_TOLERANCE) {
                    throw new RuntimeException("Cannot invoice more than the received, uninvoiced quantity for product #{$poLine->product_id}.");
                }

                $unitPrice = (float) $lineInput['unit_price'];
                $lineSubtotal = $quantity * $unitPrice;
                $taxRate = (float) ($poLine->taxCode->rate ?? 0);
                $taxAmount = $lineSubtotal * ($taxRate / 100);

                $invoice->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $poLine->product_id,
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
                ]);

                $poLine->increment('invoiced_quantity', $quantity);

                $subtotal += $lineSubtotal;
                $taxTotal += $taxAmount;
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

            return $this->match($invoice->fresh('lines.purchaseOrderLine'));
        });
    }

    /**
     * Compares each invoice line against its PO line's price and the
     * total accepted (post-QC) quantity from Goods Receipt. Within
     * tolerance -> posts AP and marks matched. Outside tolerance -> marks
     * disputed with a note explaining the mismatch, no posting made.
     */
    public function match(SupplierInvoice $invoice): SupplierInvoice
    {
        $mismatches = [];

        foreach ($invoice->lines as $line) {
            /** @var PurchaseOrderLine $poLine */
            $poLine = $line->purchaseOrderLine;

            if (abs((float) $line->unit_price - (float) $poLine->unit_price) > self::PRICE_TOLERANCE) {
                $mismatches[] = "Product #{$line->product_id}: invoice price {$line->unit_price} does not match PO price {$poLine->unit_price}.";
            }

            $acceptedQuantity = (float) GoodsReceiptLine::where('purchase_order_line_id', $poLine->id)
                ->sum('quantity_accepted');

            if ((float) $line->quantity > $acceptedQuantity + self::QUANTITY_TOLERANCE) {
                $mismatches[] = "Product #{$line->product_id}: invoice quantity {$line->quantity} exceeds accepted goods receipt quantity {$acceptedQuantity}.";
            }
        }

        if ($mismatches !== []) {
            $invoice->update([
                'status' => 'disputed',
                'dispute_notes' => implode(' ', $mismatches),
            ]);

            return $invoice->fresh();
        }

        return DB::transaction(function () use ($invoice) {
            $currency = $invoice->currency_code;
            $lines = [
                ['account_id' => $this->accountId(self::GRNI_ACCOUNT_ROLE), 'debit' => (float) $invoice->subtotal_base, 'amount_currency' => (float) $invoice->subtotal, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate],
            ];

            if ((float) $invoice->tax_total > 0) {
                $lines[] = ['account_id' => $this->accountId(self::INPUT_TAX_ACCOUNT_ROLE), 'debit' => (float) $invoice->tax_total_base, 'amount_currency' => (float) $invoice->tax_total, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate];
            }

            $lines[] = ['account_id' => $this->accountId(self::AP_ACCOUNT_ROLE), 'credit' => (float) $invoice->total_base, 'amount_currency' => -(float) $invoice->total, 'currency_code' => $currency, 'exchange_rate' => $invoice->exchange_rate];

            $this->posting->post(
                description: "Supplier invoice {$invoice->number}",
                lines: $lines,
                sourceable: $invoice,
            );

            $invoice->update(['status' => 'matched', 'dispute_notes' => null]);

            return $invoice->fresh();
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'SINV-' . now()->format('Y') . '-';

        $lastNumber = SupplierInvoice::where('number', 'like', $prefix . '%')
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
