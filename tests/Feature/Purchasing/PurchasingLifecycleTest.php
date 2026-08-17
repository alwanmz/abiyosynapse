<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseRequestService;
use App\Services\Purchasing\SupplierInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Full Fase 4 lifecycle: MRP Shortage -> Purchase Request -> Approval ->
 * PO -> Goods Receipt -> Quality Incoming -> Put Away -> Supplier Invoice
 * -> 3-Way Match -> AP (blueprint §8). Uses a 100-unit purchase of the raw
 * material "Besi Hollow" at Rp 25.000/kg as the worked example, with a
 * deliberate partial QC reject (95 accepted / 5 rejected) so the "AP only
 * for accepted units" rule has something to actually test.
 */
beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $this->user = User::factory()->create();

    $uomKg = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'KG']);
    $this->product = Product::factory()->for($this->company)->create([
        'base_uom_id' => $uomKg->id,
        'type' => 'purchased',
        'make_or_buy' => 'buy',
    ]);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
    $this->supplier = Supplier::factory()->for($this->company)->create();
    $this->taxCode = TaxCode::factory()->for($this->company)->create(['rate' => 11]);
});

test('a purchase request moves through submit and approve', function () {
    $pr = PurchaseRequest::factory()->for($this->company)->create(['warehouse_id' => $this->warehouse->id]);
    $pr->lines()->create(['product_id' => $this->product->id, 'quantity' => 100]);

    $service = app(PurchaseRequestService::class);

    $submitted = $service->submit($pr);
    expect($submitted->status)->toBe('submitted');

    $approved = $service->approve($submitted, $this->user);
    expect($approved->status)->toBe('approved');
    expect($approved->approved_by)->toBe($this->user->id);
});

test('submitting a non-draft purchase request throws', function () {
    $pr = PurchaseRequest::factory()->for($this->company)->create(['status' => 'approved']);

    app(PurchaseRequestService::class)->submit($pr);
})->throws(RuntimeException::class);

test('an approved purchase request converts into a draft purchase order and marks itself converted', function () {
    $pr = PurchaseRequest::factory()->for($this->company)->create(['warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $line = $pr->lines()->create(['product_id' => $this->product->id, 'quantity' => 100]);

    $order = app(PurchaseOrderService::class)->createFromRequest(
        $pr,
        $this->supplier->id,
        $this->warehouse->id,
        [['purchase_request_line_id' => $line->id, 'quantity' => 100, 'unit_price' => 25000, 'tax_code_id' => $this->taxCode->id]],
        $this->user,
    );

    expect($order->status)->toBe('draft');
    expect($order->lines)->toHaveCount(1);
    expect((float) $order->subtotal)->toBe(2500000.0);
    expect((float) $order->tax_total)->toBe(275000.0);
    expect((float) $order->total)->toBe(2775000.0);
    expect($pr->fresh()->status)->toBe('converted');
});

test('ordering more than the remaining requested quantity throws', function () {
    $pr = PurchaseRequest::factory()->for($this->company)->create(['warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $line = $pr->lines()->create(['product_id' => $this->product->id, 'quantity' => 100]);

    app(PurchaseOrderService::class)->createFromRequest(
        $pr,
        $this->supplier->id,
        $this->warehouse->id,
        [['purchase_request_line_id' => $line->id, 'quantity' => 150, 'unit_price' => 25000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a purchase order moves through approval submit approve send', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id]);
    $service = app(PurchaseOrderService::class);

    $order = $service->submitForApproval($order);
    expect($order->status)->toBe('approval');

    $order = $service->approve($order, $this->user);
    expect($order->status)->toBe('approved');

    $order = $service->send($order);
    expect($order->status)->toBe('sent');
    expect($order->sent_at)->not->toBeNull();
});

test('sending a non-approved purchase order throws', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create(['status' => 'draft']);

    app(PurchaseOrderService::class)->send($order);
})->throws(RuntimeException::class);

test('full lifecycle: receive goods, incoming QC with partial reject, only accepted quantity is put away and posted', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create([
        'product_id' => $this->product->id,
        'tax_code_id' => $this->taxCode->id,
        'quantity' => 100,
        'unit_price' => 25000,
    ]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [
        ['purchase_order_line_id' => $poLine->id, 'quantity_received' => 100],
    ], $this->user);

    expect($receipt->status)->toBe('pending_inspection');
    expect((float) $poLine->fresh()->received_quantity)->toBe(100.0);
    expect($order->fresh()->status)->toBe('received');

    $stockBefore = \App\Models\StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect($stockBefore)->toBeNull();

    $receiptLine = $receipt->lines->first();
    $receipt = $grService->inspectAndPutAway($receipt, [
        ['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 95],
    ]);

    expect($receipt->status)->toBe('put_away');

    $stockAfter = \App\Models\StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $stockAfter->quantity_on_hand)->toBe(95.0);

    $inspection = \App\Models\QualityInspection::where('inspectable_type', \App\Models\GoodsReceiptLine::class)->where('inspectable_id', $receiptLine->id)->first();
    expect($inspection)->not->toBeNull();
    expect($inspection->type)->toBe('incoming');
    expect($inspection->result)->toBe('fail');

    $ncr = \App\Models\NonConformanceReport::where('quality_inspection_id', $inspection->id)->first();
    expect($ncr)->not->toBeNull();

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\GoodsReceipt::class)->where('sourceable_id', $receipt->id)->with('lines.account')->first();
    expect($journal)->not->toBeNull();

    $rawMaterial = $journal->lines->firstWhere('account.code', '1.1.4');
    $grni = $journal->lines->firstWhere('account.code', '2.1.2');
    expect((float) $rawMaterial->debit)->toBe(95 * 25000.0);
    expect((float) $grni->credit)->toBe(95 * 25000.0);
});

test('putting away an already put-away receipt throws', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $poLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 25000]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => 10]], $this->user);
    $receiptLine = $receipt->lines->first();
    $receipt = $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 10]]);

    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 10]]);
})->throws(RuntimeException::class);

test('supplier invoice matching the PO and accepted goods receipt quantity posts AP automatically', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create([
        'product_id' => $this->product->id,
        'tax_code_id' => $this->taxCode->id,
        'quantity' => 100,
        'unit_price' => 25000,
    ]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => 100]], $this->user);
    $receiptLine = $receipt->lines->first();
    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 100]]);

    $invoice = app(SupplierInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['purchase_order_line_id' => $poLine->id, 'quantity' => 100, 'unit_price' => 25000]],
        $this->user,
    );

    expect($invoice->status)->toBe('matched');
    expect((float) $invoice->subtotal)->toBe(2500000.0);
    expect((float) $invoice->tax_total)->toBe(275000.0);
    expect((float) $invoice->total)->toBe(2775000.0);

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SupplierInvoice::class)->where('sourceable_id', $invoice->id)->with('lines.account')->first();
    expect($journal)->not->toBeNull();

    $grni = $journal->lines->firstWhere('account.code', '2.1.2');
    $inputTax = $journal->lines->firstWhere('account.code', '1.1.7');
    $ap = $journal->lines->firstWhere('account.code', '2.1.1');
    expect((float) $grni->debit)->toBe(2500000.0);
    expect((float) $inputTax->debit)->toBe(275000.0);
    expect((float) $ap->credit)->toBe(2775000.0);
});

test('invoicing more than the accepted goods receipt quantity disputes the invoice instead of posting AP', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 25000,
    ]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => 100]], $this->user);
    $receiptLine = $receipt->lines->first();
    // Only 90 accepted (10 rejected by incoming QC).
    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 90]]);

    $invoice = app(SupplierInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        // Supplier invoiced for the full 100, but only 90 were accepted.
        [['purchase_order_line_id' => $poLine->id, 'quantity' => 100, 'unit_price' => 25000]],
        $this->user,
    );

    expect($invoice->status)->toBe('disputed');
    expect($invoice->dispute_notes)->not->toBeNull();

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SupplierInvoice::class)->where('sourceable_id', $invoice->id)->first();
    expect($journal)->toBeNull();
});

test('a price mismatch between invoice and PO disputes the invoice', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 100, 'unit_price' => 25000]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => 100]], $this->user);
    $receiptLine = $receipt->lines->first();
    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 100]]);

    $invoice = app(SupplierInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['purchase_order_line_id' => $poLine->id, 'quantity' => 100, 'unit_price' => 26000]],
        $this->user,
    );

    expect($invoice->status)->toBe('disputed');
});
