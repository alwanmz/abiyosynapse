<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockReservation;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Inventory\InventoryValuationService;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesReturnService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanySeeder;

/**
 * Full Fase 5 lifecycle: SO -> Approval -> Delivery Order (ships FG stock,
 * posts COGS/Finished Goods) -> Sales Invoice (posts AR/Revenue/PPN
 * Keluaran) -> Sales Return (reverses stock + AR/Revenue/COGS at the
 * original cost basis). Uses CHR-X1 at Rp 750.000 standard cost / Rp
 * 1.200.000 selling price as the worked example, mirroring the costing
 * style used throughout Fase 3/3.5/4.
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

    // Seed 20 units of finished goods stock at standard cost so delivery
    // has something real to issue against.
    app(InventoryValuationService::class)->receive($this->product, $this->warehouse, 20, 750000);
});

test('a sales order moves through submit approve', function () {
    $order = SalesOrder::factory()->for($this->company)->create(['customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id]);
    $service = app(SalesOrderService::class);

    $order = $service->submitForApproval($order);
    expect($order->status)->toBe('approval');

    $order = $service->approve($order, $this->user);
    expect($order->status)->toBe('approved');
});

test('approving a non-pending sales order throws', function () {
    $order = SalesOrder::factory()->for($this->company)->create(['status' => 'draft']);

    app(SalesOrderService::class)->approve($order, $this->user);
})->throws(RuntimeException::class);

test('full lifecycle: deliver goods, ship posts COGS/FG, invoice posts AR/Revenue, return reverses everything at original cost', function () {
    $order = SalesOrder::factory()->for($this->company)->create([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $soLine = $order->lines()->create([
        'product_id' => $this->product->id,
        'tax_code_id' => $this->taxCode->id,
        'quantity' => 10,
        'unit_price' => 1200000,
    ]);

    $doService = app(DeliveryOrderService::class);
    $delivery = $doService->create($order, [
        ['sales_order_line_id' => $soLine->id, 'quantity' => 10],
    ], $this->user);

    expect($delivery->status)->toBe('draft');

    $stockBefore = \App\Models\StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $stockBefore->quantity_on_hand)->toBe(20.0);

    $delivery = $doService->ship($delivery, $this->user);

    expect($delivery->status)->toBe('shipped');
    expect((float) $soLine->fresh()->delivered_quantity)->toBe(10.0);
    expect($order->fresh()->status)->toBe('fulfilled');

    $stockAfter = \App\Models\StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $stockAfter->quantity_on_hand)->toBe(10.0);

    $shipJournal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\DeliveryOrder::class)->where('sourceable_id', $delivery->id)->with('lines.account')->first();
    $cogs = $shipJournal->lines->firstWhere('account.code', '5.1');
    $fg = $shipJournal->lines->firstWhere('account.code', '1.1.6');
    expect((float) $cogs->debit)->toBe(10 * 750000.0);
    expect((float) $fg->credit)->toBe(10 * 750000.0);

    $invoice = app(SalesInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['sales_order_line_id' => $soLine->id, 'quantity' => 10]],
        $this->user,
    );

    expect((float) $invoice->subtotal)->toBe(12000000.0);
    expect((float) $invoice->tax_total)->toBe(1320000.0);
    expect((float) $invoice->total)->toBe(13320000.0);

    $arJournal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SalesInvoice::class)->where('sourceable_id', $invoice->id)->where('description', 'like', 'Sales invoice%')->with('lines.account')->first();
    $ar = $arJournal->lines->firstWhere('account.code', '1.1.3');
    $revenue = $arJournal->lines->firstWhere('account.code', '4.1');
    $outputTax = $arJournal->lines->firstWhere('account.code', '2.1.3');
    expect((float) $ar->debit)->toBe(13320000.0);
    expect((float) $revenue->credit)->toBe(12000000.0);
    expect((float) $outputTax->credit)->toBe(1320000.0);

    $cogsJournal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SalesInvoice::class)->where('sourceable_id', $invoice->id)->where('description', 'like', 'COGS for%')->with('lines.account')->first();
    $invoiceCogs = $cogsJournal->lines->firstWhere('account.code', '5.1');
    expect((float) $invoiceCogs->debit)->toBe(10 * 750000.0);

    // Return 2 units.
    $invoiceLine = $invoice->lines->first();
    $return = app(SalesReturnService::class)->create(
        $invoice,
        ['warehouse_id' => $this->warehouse->id, 'return_date' => now()->toDateString(), 'reason' => 'Defective'],
        [['sales_invoice_line_id' => $invoiceLine->id, 'quantity' => 2]],
        $this->user,
    );

    expect((float) $return->subtotal)->toBe(2400000.0);
    expect((float) $return->tax_total)->toBe(264000.0);

    $stockAfterReturn = \App\Models\StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $stockAfterReturn->quantity_on_hand)->toBe(12.0); // 10 remaining + 2 returned

    $returnJournal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SalesReturn::class)->where('sourceable_id', $return->id)->where('description', 'like', 'Sales return%')->with('lines.account')->first();
    $revenueReversal = $returnJournal->lines->firstWhere('account.code', '4.1');
    $arReversal = $returnJournal->lines->firstWhere('account.code', '1.1.3');
    expect((float) $revenueReversal->debit)->toBe(2400000.0);
    expect((float) $arReversal->credit)->toBe(2664000.0);

    $returnCogsJournal = \App\Models\JournalEntry::where('sourceable_type', \App\Models\SalesReturn::class)->where('sourceable_id', $return->id)->where('description', 'like', 'COGS reversal%')->with('lines.account')->first();
    $fgReversal = $returnCogsJournal->lines->firstWhere('account.code', '1.1.6');
    $cogsReversal = $returnCogsJournal->lines->firstWhere('account.code', '5.1');
    expect((float) $fgReversal->debit)->toBe(2 * 750000.0);
    expect((float) $cogsReversal->credit)->toBe(2 * 750000.0);
});

test('delivering more than the remaining ordered quantity throws', function () {
    $order = SalesOrder::factory()->for($this->company)->create([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $soLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 1200000]);

    app(DeliveryOrderService::class)->create($order, [
        ['sales_order_line_id' => $soLine->id, 'quantity' => 15],
    ], $this->user);
})->throws(RuntimeException::class);

test('draft delivery reserves approved lots and cancelling it releases the reservation', function () {
    $order = SalesOrder::factory()->for($this->company)->create([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
    ]);
    $line = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 8, 'unit_price' => 1200000]);

    $service = app(DeliveryOrderService::class);
    $delivery = $service->create($order, [['sales_order_line_id' => $line->id, 'quantity' => 8]], $this->user);

    expect((float) StockReservation::where('delivery_order_id', $delivery->id)->where('status', 'reserved')->sum('quantity'))->toBe(8.0);
    expect(app(\App\Services\Inventory\StockQualityService::class)->availableForSale($this->product, $this->warehouse))->toBe(12.0);

    $cancelled = $service->cancel($delivery, $this->user);
    expect($cancelled->status)->toBe('cancelled');
    expect(StockReservation::where('delivery_order_id', $delivery->id)->where('status', 'reserved')->count())->toBe(0);
    expect(app(\App\Services\Inventory\StockQualityService::class)->availableForSale($this->product, $this->warehouse))->toBe(20.0);
});

test('shipping an already-shipped delivery order throws', function () {
    $order = SalesOrder::factory()->for($this->company)->create(['customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $soLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 1200000]);

    $doService = app(DeliveryOrderService::class);
    $delivery = $doService->create($order, [['sales_order_line_id' => $soLine->id, 'quantity' => 5]], $this->user);
    $doService->ship($delivery, $this->user);

    $doService->ship($delivery, $this->user);
})->throws(RuntimeException::class);

test('invoicing more than the delivered quantity throws', function () {
    $order = SalesOrder::factory()->for($this->company)->create(['customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $soLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 1200000]);

    $doService = app(DeliveryOrderService::class);
    $delivery = $doService->create($order, [['sales_order_line_id' => $soLine->id, 'quantity' => 5]], $this->user);
    $doService->ship($delivery, $this->user);

    app(SalesInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['sales_order_line_id' => $soLine->id, 'quantity' => 10]],
        $this->user,
    );
})->throws(RuntimeException::class);

test('returning more than the invoiced quantity throws', function () {
    $order = SalesOrder::factory()->for($this->company)->create(['customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'approved']);
    $soLine = $order->lines()->create(['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 1200000]);

    $doService = app(DeliveryOrderService::class);
    $delivery = $doService->create($order, [['sales_order_line_id' => $soLine->id, 'quantity' => 5]], $this->user);
    $doService->ship($delivery, $this->user);

    $invoice = app(SalesInvoiceService::class)->create(
        $order,
        ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
        [['sales_order_line_id' => $soLine->id, 'quantity' => 5]],
        $this->user,
    );

    $invoiceLine = $invoice->lines->first();
    app(SalesReturnService::class)->create(
        $invoice,
        ['warehouse_id' => $this->warehouse->id, 'return_date' => now()->toDateString()],
        [['sales_invoice_line_id' => $invoiceLine->id, 'quantity' => 10]],
        $this->user,
    );
})->throws(RuntimeException::class);
