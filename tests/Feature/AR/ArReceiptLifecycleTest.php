<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AR\ArReceiptService;
use App\Services\CashBank\BankAccountService;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesReturnService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Fase 7 lifecycle: an AR Receipt collects money against one or more
 * posted Sales Invoices, posting Bank debit / AR credit and updating
 * each invoice's paid_amount so outstandingAmount() reflects reality.
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
        'type' => 'manufactured',
        'standard_cost' => 750000,
    ]);
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
    $this->customer = Customer::factory()->for($this->company)->create();
    $this->taxCode = TaxCode::factory()->for($this->company)->create(['rate' => 11]);

    app(InventoryValuationService::class)->receive($this->product, $this->warehouse, 20, 750000);

    $this->bankAccount = app(BankAccountService::class)->create([
        'code' => 'BCA-001',
        'name' => 'BCA',
        'type' => 'bank',
    ]);
});

function makeInvoice(Company $company, Customer $customer, Warehouse $warehouse, Product $product, TaxCode $taxCode, User $user, float $quantity, float $unitPrice)
{
    $order = SalesOrder::factory()->for($company)->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'approved',
    ]);
    $soLine = $order->lines()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ]);

    $doService = app(DeliveryOrderService::class);
    $delivery = $doService->create($order, [['sales_order_line_id' => $soLine->id, 'quantity' => $quantity]], $user);
    $doService->ship($delivery, $user);

    return app(SalesInvoiceService::class)->create(
        $order->fresh(),
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['sales_order_line_id' => $soLine->id, 'quantity' => $quantity]],
        $user,
    );
}

test('a full payment against a single invoice posts bank debit / AR credit and marks the invoice fully paid', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 5, 1200000); // subtotal 6,000,000 + 11% tax = 6,660,000

    $receipt = app(ArReceiptService::class)->create(
        [
            'customer_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
            'receipt_date' => now()->toDateString(),
            'amount' => 6660000,
            'reference' => 'TRF-001',
        ],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 6660000]],
        $this->user,
    );

    expect((float) $receipt->amount)->toBe(6660000.0);
    expect($invoice->fresh()->isFullyPaid())->toBeTrue();
    expect($invoice->fresh()->outstandingAmount())->toBe(0.0);

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\ArReceipt::class)
        ->where('sourceable_id', $receipt->id)
        ->with('lines.account')
        ->first();

    $bankLine = $journal->lines->firstWhere('account_id', $this->bankAccount->account_id);
    $arLine = $journal->lines->firstWhere('account.code', '1.1.3');

    expect((float) $bankLine->debit)->toBe(6660000.0);
    expect((float) $arLine->credit)->toBe(6660000.0);
});

test('a partial payment leaves the invoice partially outstanding', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 5, 1200000); // total 6,660,000

    app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 3000000],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 3000000]],
        $this->user,
    );

    $invoice->refresh();
    expect($invoice->isFullyPaid())->toBeFalse();
    expect($invoice->outstandingAmount())->toBe(6660000.0 - 3000000.0);
});

test('a single receipt can apply to multiple invoices at once', function () {
    $invoice1 = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 2, 1000000); // subtotal 2,000,000 + 11% = 2,220,000
    $invoice2 = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 3, 1000000); // subtotal 3,000,000 + 11% = 3,330,000

    $receipt = app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 5550000],
        [
            ['sales_invoice_id' => $invoice1->id, 'amount_applied' => 2220000],
            ['sales_invoice_id' => $invoice2->id, 'amount_applied' => 3330000],
        ],
        $this->user,
    );

    expect($receipt->lines)->toHaveCount(2);
    expect($invoice1->fresh()->isFullyPaid())->toBeTrue();
    expect($invoice2->fresh()->isFullyPaid())->toBeTrue();

    $journal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\ArReceipt::class)
        ->where('sourceable_id', $receipt->id)
        ->with('lines.account')
        ->first();
    $bankLine = $journal->lines->firstWhere('account_id', $this->bankAccount->account_id);
    expect((float) $bankLine->debit)->toBe(5550000.0);
});

test('applying more than an invoice outstanding amount throws', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 1, 1000000); // total 1,110,000

    app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 2000000],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 2000000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a receipt amount that does not match the sum of applied lines throws', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 5, 1200000); // total 6,660,000

    app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 5000000],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 3000000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a sales return reduces the outstanding amount even without any receipt', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 5, 1200000); // total 6,660,000
    $invoiceLine = $invoice->lines->first();

    app(SalesReturnService::class)->create(
        $invoice,
        ['warehouse_id' => $this->warehouse->id, 'return_date' => now()->toDateString()],
        [['sales_invoice_line_id' => $invoiceLine->id, 'quantity' => 1]], // 1 of 5 units = 1,332,000 (subtotal 1,200,000 + 11%)
        $this->user,
    );

    $invoice->refresh();
    expect($invoice->outstandingAmount())->toBe(6660000.0 - 1332000.0);
});

test('a receipt cannot exceed the outstanding amount after a partial return has already reduced it', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 5, 1200000); // total 6,660,000
    $invoiceLine = $invoice->lines->first();

    app(SalesReturnService::class)->create(
        $invoice,
        ['warehouse_id' => $this->warehouse->id, 'return_date' => now()->toDateString()],
        [['sales_invoice_line_id' => $invoiceLine->id, 'quantity' => 1]], // returns 1,332,000
        $this->user,
    );

    // Outstanding is now 5,328,000 — try to collect the full original total instead.
    app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 6660000],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 6660000]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('a zero or negative amount applied to an invoice throws', function () {
    $invoice = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 1, 1000000);

    app(ArReceiptService::class)->create(
        ['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 0],
        [['sales_invoice_id' => $invoice->id, 'amount_applied' => 0]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('receipt numbers are gap-safe', function () {
    $invoice1 = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 1, 1000000);
    $invoice2 = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 1, 1000000);
    $service = app(ArReceiptService::class);

    $r1 = $service->create(['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 1110000], [['sales_invoice_id' => $invoice1->id, 'amount_applied' => 1110000]], $this->user);
    $r2 = $service->create(['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 1110000], [['sales_invoice_id' => $invoice2->id, 'amount_applied' => 1110000]], $this->user);

    expect($r1->number)->not->toBe($r2->number);

    \App\Models\JournalEntry::where('sourceable_type', \App\Models\ArReceipt::class)->where('sourceable_id', $r1->id)->first()->lines()->delete();
    \App\Models\JournalEntry::where('sourceable_type', \App\Models\ArReceipt::class)->where('sourceable_id', $r1->id)->delete();
    $r1->lines()->delete();
    $r1->delete();

    $invoice3 = makeInvoice($this->company, $this->customer, $this->warehouse, $this->product, $this->taxCode, $this->user, 1, 1000000);
    $r3 = $service->create(['customer_id' => $this->customer->id, 'bank_account_id' => $this->bankAccount->id, 'receipt_date' => now()->toDateString(), 'amount' => 1110000], [['sales_invoice_id' => $invoice3->id, 'amount_applied' => 1110000]], $this->user);

    expect($r3->number)->not->toBe($r2->number);
});
