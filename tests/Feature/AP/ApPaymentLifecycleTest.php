<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AP\ApPaymentService;
use App\Services\CashBank\BankAccountService;
use App\Services\CurrentCompany;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\SupplierInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Fase 8 lifecycle: an AP Payment pays money to a supplier against one or
 * more matched Supplier Invoices, posting AP debit / Bank credit (the
 * mirror image of Fase 7's ArReceiptService) and updating each invoice's
 * paid_amount so outstandingAmount() reflects reality.
 */
beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);
    (new ChartOfAccountsSeeder())->run();

    $this->user = User::factory()->create();

    $uom = UnitOfMeasure::factory()->for($this->company)->create();
    $this->product = Product::factory()->for($this->company)->create([
        'base_uom_id' => $uom->id,
        'type' => 'purchased',
        'make_or_buy' => 'buy',
    ]);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
    $this->supplier = Supplier::factory()->for($this->company)->create();
    $this->taxCode = TaxCode::factory()->for($this->company)->create(['rate' => 11]);

    $this->bankAccount = app(BankAccountService::class)->create([
        'code' => 'BCA-001',
        'name' => 'BCA',
        'type' => 'bank',
    ]);
});

function makeMatchedInvoice(Company $company, Supplier $supplier, Warehouse $warehouse, Product $product, TaxCode $taxCode, User $user, float $quantity, float $unitPrice)
{
    $order = PurchaseOrder::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => $quantity]], $user);
    $receiptLine = $receipt->lines->first();
    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => $quantity]]);

    return app(SupplierInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['purchase_order_line_id' => $poLine->id, 'quantity' => $quantity, 'unit_price' => $unitPrice]],
        $user,
    );
}

test('a full payment against a single matched invoice posts AP debit / bank credit and marks it fully paid', function () {
    $invoice = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 100, 25000); // subtotal 2,500,000 + 11% = 2,775,000
    expect($invoice->status)->toBe('matched');

    $payment = app(ApPaymentService::class)->create(
        [
            'supplier_id' => $this->supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 2775000,
            'reference' => 'TRF-001',
        ],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 2775000]],
        $this->user,
    );

    expect((float) $payment->amount)->toBe(2775000.0);
    expect($invoice->fresh()->isFullyPaid())->toBeTrue();
    expect($invoice->fresh()->outstandingAmount())->toBe(0.0);

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\ApPayment::class)
        ->where('sourceable_id', $payment->id)
        ->with('lines.account')
        ->first();

    $apLine = $journal->lines->firstWhere('account.code', '2.1.1');
    $bankLine = $journal->lines->firstWhere('account_id', $this->bankAccount->account_id);

    expect((float) $apLine->debit)->toBe(2775000.0);
    expect((float) $bankLine->credit)->toBe(2775000.0);
});

test('a partial payment leaves the invoice partially outstanding', function () {
    $invoice = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 100, 25000); // total 2,775,000

    app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 1000000],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 1000000]],
        $this->user,
    );

    $invoice->refresh();
    expect($invoice->isFullyPaid())->toBeFalse();
    expect($invoice->outstandingAmount())->toBe(2775000.0 - 1000000.0);
});

test('a single payment can apply to multiple invoices at once', function () {
    $invoice1 = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 40, 25000); // subtotal 1,000,000 + 11% = 1,110,000
    $invoice2 = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 60, 25000); // subtotal 1,500,000 + 11% = 1,665,000

    $payment = app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 2775000],
        [
            ['supplier_invoice_id' => $invoice1->id, 'amount_applied' => 1110000],
            ['supplier_invoice_id' => $invoice2->id, 'amount_applied' => 1665000],
        ],
        $this->user,
    );

    expect($payment->lines)->toHaveCount(2);
    expect($invoice1->fresh()->isFullyPaid())->toBeTrue();
    expect($invoice2->fresh()->isFullyPaid())->toBeTrue();
});

test('applying more than an invoice outstanding amount throws', function () {
    $invoice = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 10, 25000); // total 277,500

    app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 500000],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 500000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a payment amount that does not match the sum of applied lines throws', function () {
    $invoice = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 100, 25000); // total 2,775,000

    app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 2000000],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 1000000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('paying a disputed invoice throws because it never had AP posted against it', function () {
    $order = PurchaseOrder::factory()->for($this->company)->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $poLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 100, 'unit_price' => 25000]);

    $grService = app(GoodsReceiptService::class);
    $receipt = $grService->receive($order, [['purchase_order_line_id' => $poLine->id, 'quantity_received' => 100]], $this->user);
    $receiptLine = $receipt->lines->first();
    // Only 90 accepted (10 rejected) — a subsequent invoice for the full 100 disputes.
    $grService->inspectAndPutAway($receipt, [['goods_receipt_line_id' => $receiptLine->id, 'quantity_accepted' => 90]]);

    $invoice = app(SupplierInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['purchase_order_line_id' => $poLine->id, 'quantity' => 100, 'unit_price' => 25000]],
        $this->user,
    );

    expect($invoice->status)->toBe('disputed');

    app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 1000000],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 1000000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a zero or negative amount applied to an invoice throws', function () {
    $invoice = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 10, 25000);

    app(ApPaymentService::class)->create(
        ['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 0],
        [['supplier_invoice_id' => $invoice->id, 'amount_applied' => 0]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('payment numbers are gap-safe', function () {
    $invoice1 = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 10, 25000);
    $invoice2 = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 10, 25000);
    $service = app(ApPaymentService::class);

    $p1 = $service->create(['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 277500], [['supplier_invoice_id' => $invoice1->id, 'amount_applied' => 277500]], $this->user);
    $p2 = $service->create(['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 277500], [['supplier_invoice_id' => $invoice2->id, 'amount_applied' => 277500]], $this->user);

    expect($p1->number)->not->toBe($p2->number);

    \App\Models\JournalEntry::where('sourceable_type', \App\Models\ApPayment::class)->where('sourceable_id', $p1->id)->first()->lines()->delete();
    \App\Models\JournalEntry::where('sourceable_type', \App\Models\ApPayment::class)->where('sourceable_id', $p1->id)->delete();
    $p1->lines()->delete();
    $p1->delete();

    $invoice3 = makeMatchedInvoice($this->company, $this->supplier, $this->warehouse, $this->product, $this->taxCode, $this->user, 10, 25000);
    $p3 = $service->create(['supplier_id' => $this->supplier->id, 'bank_account_id' => $this->bankAccount->id, 'payment_date' => now()->toDateString(), 'amount' => 277500], [['supplier_invoice_id' => $invoice3->id, 'amount_applied' => 277500]], $this->user);

    expect($p3->number)->not->toBe($p2->number);
});
