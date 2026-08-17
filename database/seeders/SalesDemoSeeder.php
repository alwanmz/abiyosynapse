<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Fase 5 Sales cycle: a Sales Order for 5 units of CHR-X1 to
 * the demo customer is approved, delivered (issuing FG stock and posting
 * COGS), invoiced (posting AR/Revenue/PPN Keluaran), and 1 unit is
 * returned (reversing stock and the GL at the original cost basis) — so
 * every Sales list/detail page has a real non-empty example instead of
 * being empty on a fresh install.
 *
 * Requires MasterDataDemoSeeder to have run first (product/customer/tax
 * code) and enough CHR-X1 finished-goods stock to exist already (from
 * ManufacturingDemoSeeder / QualityDemoSeeder production runs). Idempotent
 * via a fixed SO number.
 */
class SalesDemoSeeder extends Seeder
{
    private const SO_NUMBER = 'SO-DEMO-01';
    private const QUANTITY = 5;
    private const RETURN_QUANTITY = 1;

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (SalesOrder::where('company_id', $company->id)->where('number', self::SO_NUMBER)->exists()) {
            return;
        }

        $product = Product::where('company_id', $company->id)->where('code', 'CHR-X1')->first();
        // CHR-X1 finished-goods stock lands in WH-RM, not FG-01 — Fase 3's
        // ProductionOrder only has a single warehouse_id (used for both
        // consuming raw materials and receiving output), and raw material
        // stock only exists in WH-RM, so every demo production run
        // (including QualityDemoSeeder) uses WH-RM end to end.
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'WH-RM')->first();
        $customer = Customer::where('company_id', $company->id)->where('code', 'CUST-0001')->first();
        $taxCode = TaxCode::where('company_id', $company->id)->where('code', 'PPN11')->first();
        $user = User::first();

        if (! $product || ! $warehouse || ! $customer || ! $taxCode) {
            return;
        }

        $stock = \App\Models\StockLevel::where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $stock || (float) $stock->quantity_on_hand < self::QUANTITY) {
            return;
        }

        $order = SalesOrder::create([
            'company_id' => $company->id,
            'number' => self::SO_NUMBER,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
            'status' => 'draft',
            'created_by' => $user?->id,
        ]);

        $soLine = $order->lines()->create([
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'quantity' => self::QUANTITY,
            'unit_price' => (float) $product->selling_price,
        ]);

        $soService = app(SalesOrderService::class);
        $order = $soService->submitForApproval($order->fresh());
        $order = $soService->approve($order, $user ?? User::factory()->create());
        $soService->recalculateTotals($order);
        $order = $order->fresh();

        $doService = app(DeliveryOrderService::class);
        $delivery = $doService->create($order, [
            ['sales_order_line_id' => $soLine->id, 'quantity' => self::QUANTITY],
        ], $user);
        $doService->ship($delivery, $user);

        $invoice = app(SalesInvoiceService::class)->create(
            $order->fresh(),
            [
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($customer->payment_term_days ?? 30)->toDateString(),
            ],
            [['sales_order_line_id' => $soLine->id, 'quantity' => self::QUANTITY]],
            $user,
        );

        $invoiceLine = $invoice->lines->first();

        app(SalesReturnService::class)->create(
            $invoice,
            [
                'warehouse_id' => $warehouse->id,
                'return_date' => now()->toDateString(),
                'reason' => 'Demo: pelanggan mengembalikan 1 unit karena cacat produksi.',
            ],
            [['sales_invoice_line_id' => $invoiceLine->id, 'quantity' => self::RETURN_QUANTITY]],
            $user,
        );
    }
}
