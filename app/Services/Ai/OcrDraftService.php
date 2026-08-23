<?php

namespace App\Services\Ai;

use App\Models\AiActionRun;
use App\Models\AiDocument;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrencyDocumentService;
use App\Services\CurrentCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OcrDraftService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CurrencyDocumentService $currencyDocuments,
    ) {
    }

    public function createDraft(AiDocument $document, array $payload, User $user): array
    {
        if ($document->status !== 'review') {
            throw new RuntimeException('Dokumen harus berada pada status review sebelum dibuat menjadi draft.');
        }

        return DB::transaction(function () use ($document, $payload, $user) {
            $result = match ($document->document_type) {
                'supplier_invoice' => $this->supplierInvoice($payload, $user),
                'purchase_request' => $this->purchaseRequest($payload, $user),
                'goods_receipt' => $this->goodsReceipt($payload, $user),
                'sales_order' => $this->salesOrder($payload, $user),
                'sales_invoice' => $this->salesInvoice($payload, $user),
                default => throw new RuntimeException('Tipe dokumen ini belum memiliki mapper draft ERP.'),
            };

            $run = AiActionRun::create([
                'user_id' => $user->id,
                'tool' => 'ocr_create_' . $document->document_type,
                'instruction' => 'User mengonfirmasi hasil review OCR untuk membuat draft.',
                'status' => 'executed',
                'arguments' => ['document_id' => $document->id, 'document_type' => $document->document_type],
                'result' => $result,
                'confirmed_at' => now(),
                'executed_at' => now(),
            ]);

            $document->update([
                'status' => 'accepted',
                'normalized_payload' => $payload,
            ]);
            app(\App\Services\AuditTrailService::class)->record($document, 'document_accepted', ['status' => 'review'], ['status' => 'accepted', 'result' => $result], null, $user->id);

            return [...$result, 'ai_action_run_id' => $run->id];
        });
    }

    private function supplierInvoice(array $payload, User $user): array
    {
        $po = $this->purchaseOrder($payload);
        $supplier = $this->supplier($payload['supplier_code'] ?? null);
        if ($supplier && $supplier->id !== $po->supplier_id) {
            throw new RuntimeException('Kode supplier OCR tidak cocok dengan supplier pada PO.');
        }

        $date = $payload['document_date'] ?: now()->toDateString();
        $currency = $this->currency($payload['currency_code'] ?? null, $po->currency_code);
        $lines = $this->purchaseLines($payload, $po, true);
        [$subtotal, $taxTotal] = $this->totals($lines);
        $this->assertTotal($payload, $subtotal, $taxTotal);
        $invoice = \App\Models\SupplierInvoice::create([
            'number' => $this->nextNumber('supplier_invoices', 'SINV'),
            'supplier_reference' => $payload['document_number'] ?? null,
            'purchase_order_id' => $po->id,
            'supplier_id' => $po->supplier_id,
            'invoice_date' => $date,
            'due_date' => $payload['due_date'] ?: $date,
            'currency_code' => $currency['currency_code'],
            'exchange_rate' => $currency['exchange_rate'],
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $this->add($subtotal, $taxTotal),
            'subtotal_base' => $this->base($subtotal, $currency, $date),
            'tax_total_base' => $this->base($taxTotal, $currency, $date),
            'total_base' => $this->base($this->add($subtotal, $taxTotal), $currency, $date),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        foreach ($lines as $line) {
            $invoice->lines()->create([
                'purchase_order_line_id' => $line['source_id'],
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'unit_price_base' => $this->base($line['unit_price'], $currency, $date),
                'tax_amount' => $line['tax_amount'],
                'tax_amount_base' => $this->base($line['tax_amount'], $currency, $date),
            ]);
        }

        return ['type' => 'supplier_invoice', 'id' => $invoice->id, 'number' => $invoice->number, 'status' => 'draft'];
    }

    private function purchaseRequest(array $payload, User $user): array
    {
        $warehouse = $this->warehouse($payload['warehouse_code'] ?? null);
        $lines = $this->masterLines($payload);
        $request = PurchaseRequest::create([
            'number' => $this->nextNumber('purchase_requests', 'PR'),
            'warehouse_id' => $warehouse->id,
            'notes' => 'Draft dibuat dari OCR ' . ($payload['document_number'] ?? 'dokumen'),
            'status' => 'draft',
            'requested_by' => $user->id,
        ]);
        foreach ($lines as $line) {
            $request->lines()->create(['product_id' => $line['product_id'], 'quantity' => $line['quantity']]);
        }

        return ['type' => 'purchase_request', 'id' => $request->id, 'number' => $request->number, 'status' => 'draft'];
    }

    private function goodsReceipt(array $payload, User $user): array
    {
        $po = $this->purchaseOrder($payload);
        $lines = $this->purchaseLines($payload, $po, false);
        $receipt = GoodsReceipt::create([
            'number' => $this->nextNumber('goods_receipts', 'GR'),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $po->warehouse_id,
            'received_date' => $payload['document_date'] ?: now()->toDateString(),
            'status' => 'draft',
            'received_by' => $user->id,
        ]);
        foreach ($lines as $line) {
            $receipt->lines()->create([
                'purchase_order_line_id' => $line['source_id'],
                'product_id' => $line['product_id'],
                'quantity_received' => $line['quantity'],
                'unit_cost' => $line['unit_price'],
            ]);
        }

        return ['type' => 'goods_receipt', 'id' => $receipt->id, 'number' => $receipt->number, 'status' => 'draft'];
    }

    private function salesOrder(array $payload, User $user): array
    {
        $customer = $this->customer($payload['customer_code'] ?? null);
        $warehouse = $this->warehouse($payload['warehouse_code'] ?? null);
        $currency = $this->currency($payload['currency_code'] ?? null, $customer->currency_code);
        $date = $payload['document_date'] ?: now()->toDateString();
        $lines = $this->masterLines($payload, true);
        [$subtotal, $taxTotal] = $this->totals($lines);
        $this->assertTotal($payload, $subtotal, $taxTotal);
        $order = SalesOrder::create([
            'number' => $this->nextNumber('sales_orders', 'SO'),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => $date,
            'requested_delivery_date' => $payload['due_date'] ?: null,
            'currency_code' => $currency['currency_code'],
            'exchange_rate' => $currency['exchange_rate'],
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $this->add($subtotal, $taxTotal),
            'subtotal_base' => $this->base($subtotal, $currency, $date),
            'tax_total_base' => $this->base($taxTotal, $currency, $date),
            'total_base' => $this->base($this->add($subtotal, $taxTotal), $currency, $date),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        foreach ($lines as $line) {
            $order->lines()->create([
                'product_id' => $line['product_id'],
                'tax_code_id' => $line['tax_code_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'unit_price_base' => $this->base($line['unit_price'], $currency, $date),
            ]);
        }

        return ['type' => 'sales_order', 'id' => $order->id, 'number' => $order->number, 'status' => 'draft'];
    }

    private function salesInvoice(array $payload, User $user): array
    {
        $so = $this->salesOrderReference($payload);
        $customer = $this->customer($payload['customer_code'] ?? null);
        if ($customer && $customer->id !== $so->customer_id) {
            throw new RuntimeException('Kode customer OCR tidak cocok dengan customer pada SO.');
        }
        $currency = $this->currency($payload['currency_code'] ?? null, $so->currency_code);
        $date = $payload['document_date'] ?: now()->toDateString();
        $lines = $this->salesLines($payload, $so);
        [$subtotal, $taxTotal] = $this->totals($lines);
        $this->assertTotal($payload, $subtotal, $taxTotal);
        $invoice = SalesInvoice::create([
            'number' => $this->nextNumber('sales_invoices', 'SO-INV'),
            'sales_order_id' => $so->id,
            'customer_id' => $so->customer_id,
            'invoice_date' => $date,
            'due_date' => $payload['due_date'] ?: $date,
            'currency_code' => $currency['currency_code'],
            'exchange_rate' => $currency['exchange_rate'],
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $this->add($subtotal, $taxTotal),
            'subtotal_base' => $this->base($subtotal, $currency, $date),
            'tax_total_base' => $this->base($taxTotal, $currency, $date),
            'total_base' => $this->base($this->add($subtotal, $taxTotal), $currency, $date),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        foreach ($lines as $line) {
            $invoice->lines()->create([
                'sales_order_line_id' => $line['source_id'],
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'unit_price_base' => $this->base($line['unit_price'], $currency, $date),
                'tax_amount' => $line['tax_amount'],
                'tax_amount_base' => $this->base($line['tax_amount'], $currency, $date),
                'unit_cost' => $line['unit_cost'],
                'unit_cost_base' => $this->base($line['unit_cost'], ['currency_code' => $this->currentCompany->get()?->currency ?? 'IDR', 'exchange_rate' => '1', 'effective_date' => $date], $date),
            ]);
        }

        return ['type' => 'sales_invoice', 'id' => $invoice->id, 'number' => $invoice->number, 'status' => 'draft'];
    }

    private function purchaseOrder(array $payload): PurchaseOrder
    {
        $number = trim((string) ($payload['purchase_order_number'] ?? ''));
        if ($number === '') throw new RuntimeException('Nomor PO wajib diisi untuk draft ini.');
        return PurchaseOrder::with('lines.taxCode')->where('number', $number)->firstOrFail();
    }

    private function salesOrderReference(array $payload): SalesOrder
    {
        $number = trim((string) ($payload['sales_order_number'] ?? $payload['purchase_order_number'] ?? ''));
        if ($number === '') throw new RuntimeException('Nomor SO wajib diisi untuk draft invoice penjualan.');
        return SalesOrder::with('lines.taxCode')->where('number', $number)->firstOrFail();
    }

    private function purchaseLines(array $payload, PurchaseOrder $po, bool $invoice): array
    {
        $result = [];
        foreach ($payload['lines'] ?? [] as $line) {
            $product = $this->product($line['product_code'] ?? null);
            $poLine = $po->lines->first(fn ($row) => $row->product_id === $product->id);
            if (! $poLine) throw new RuntimeException("Produk {$product->code} tidak ditemukan pada PO.");
            $quantity = $this->positive($line['quantity'] ?? '0');
            $limit = $invoice ? $poLine->remainingToInvoice() : $poLine->remainingToReceive();
            if ($quantity > $limit + 0.0001) throw new RuntimeException("Kuantitas {$product->code} melebihi sisa PO.");
            $unitPrice = $this->positive($line['unit_price'] ?? $poLine->unit_price);
            $taxRate = (float) ($poLine->taxCode?->rate ?? 0);
            $taxAmount = $this->multiply($this->multiply($quantity, $unitPrice), (string) ($taxRate / 100));
            $result[] = ['source_id' => $poLine->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'tax_amount' => $invoice ? $taxAmount : '0'];
        }
        if ($result === []) throw new RuntimeException('Minimal satu baris dokumen harus valid.');
        return $result;
    }

    private function salesLines(array $payload, SalesOrder $so): array
    {
        $result = [];
        foreach ($payload['lines'] ?? [] as $line) {
            $product = $this->product($line['product_code'] ?? null);
            $soLine = $so->lines->first(fn ($row) => $row->product_id === $product->id);
            if (! $soLine) throw new RuntimeException("Produk {$product->code} tidak ditemukan pada SO.");
            $quantity = $this->positive($line['quantity'] ?? '0');
            if ($quantity > $soLine->remainingToInvoice() + 0.0001) throw new RuntimeException("Kuantitas {$product->code} melebihi kuantitas yang dapat diinvoicing pada SO.");
            $unitPrice = $this->positive($line['unit_price'] ?? $soLine->unit_price);
            $taxAmount = $this->multiply($this->multiply($quantity, $unitPrice), (string) (((float) ($soLine->taxCode?->rate ?? 0)) / 100));
            $result[] = ['source_id' => $soLine->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'tax_amount' => $taxAmount, 'tax_code_id' => $soLine->tax_code_id, 'unit_cost' => (string) $product->standard_cost];
        }
        if ($result === []) throw new RuntimeException('Minimal satu baris invoice harus valid.');
        return $result;
    }

    private function masterLines(array $payload, bool $withTax = false): array
    {
        $result = [];
        foreach ($payload['lines'] ?? [] as $line) {
            $product = $this->product($line['product_code'] ?? null);
            $quantity = $this->positive($line['quantity'] ?? '0');
            $unitPrice = $withTax
                ? $this->positive($line['unit_price'] ?? $product->selling_price ?? '0')
                : $this->nonNegative($line['unit_price'] ?? '0');
            $taxCode = $withTax ? $this->taxCode($line['tax_code'] ?? null) : null;
            $taxAmount = $withTax ? $this->multiply($this->multiply($quantity, $unitPrice), (string) (((float) ($taxCode?->rate ?? 0)) / 100)) : '0';
            $result[] = ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'tax_code_id' => $taxCode?->id, 'tax_amount' => $taxAmount];
        }
        if ($result === []) throw new RuntimeException('Minimal satu baris dokumen harus valid.');
        return $result;
    }

    private function totals(array $lines): array
    {
        $subtotal = '0'; $tax = '0';
        foreach ($lines as $line) { $subtotal = $this->add($subtotal, $this->multiply($line['quantity'], $line['unit_price'])); $tax = $this->add($tax, $line['tax_amount'] ?? '0'); }
        return [$subtotal, $tax];
    }

    private function assertTotal(array $payload, string $subtotal, string $tax): void
    {
        if ($payload['total'] === null || $payload['total'] === '') return;
        $expected = $this->add($subtotal, $tax);
        if (abs((float) $this->subtract((string) $payload['total'], $expected)) > 0.02) throw new RuntimeException('Total OCR berbeda dari kalkulasi server. Periksa kembali baris dan pajak sebelum membuat draft.');
    }

    private function currency(?string $code, ?string $fallback): array { return $this->currencyDocuments->resolve($code, now()->toDateString(), $fallback); }
    private function product(?string $code): Product { if (! $code) throw new RuntimeException('Kode produk wajib diisi; AI tidak boleh menebak master data.'); return Product::where('code', trim($code))->firstOrFail(); }
    private function supplier(?string $code): ?Supplier { return $code ? Supplier::where('code', trim($code))->first() : null; }
    private function customer(?string $code): ?Customer { if (! $code) throw new RuntimeException('Kode customer wajib diisi; AI tidak boleh menebak master data.'); return Customer::where('code', trim($code))->firstOrFail(); }
    private function warehouse(?string $code): Warehouse { if (! $code) throw new RuntimeException('Kode gudang wajib diisi; AI tidak boleh menebak master data.'); return Warehouse::where('code', trim($code))->firstOrFail(); }
    private function taxCode(?string $code): ?TaxCode { return $code ? TaxCode::where('code', trim($code))->firstOrFail() : null; }
    private function positive(string|int|float $value): string { if ((float) $value <= 0) throw new RuntimeException('Kuantitas dan harga harus lebih besar dari nol.'); return BigDecimal::of((string) $value)->toScale(6, RoundingMode::HALF_UP)->__toString(); }
    private function nonNegative(string|int|float $value): string { if ((float) $value < 0) throw new RuntimeException('Harga tidak boleh negatif.'); return BigDecimal::of((string) $value)->toScale(6, RoundingMode::HALF_UP)->__toString(); }
    private function add(string $a, string $b): string { return BigDecimal::of($a)->plus($b)->toScale(6, RoundingMode::HALF_UP)->__toString(); }
    private function subtract(string $a, string $b): string { return BigDecimal::of($a)->minus($b)->toScale(6, RoundingMode::HALF_UP)->__toString(); }
    private function multiply(string $a, string $b): string { return BigDecimal::of($a)->multipliedBy($b)->toScale(6, RoundingMode::HALF_UP)->__toString(); }
    private function base(string $amount, array $currency, string $date): string { return $this->currencyDocuments->baseAmount($amount, [...$currency, 'effective_date' => $date]); }
    private function nextNumber(string $table, string $prefix): string { $prefix = $prefix . '-' . now()->format('Y') . '-'; $last = DB::table($table)->where('number', 'like', $prefix . '%')->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')->value('number'); return $prefix . str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 6, '0', STR_PAD_LEFT); }
}
