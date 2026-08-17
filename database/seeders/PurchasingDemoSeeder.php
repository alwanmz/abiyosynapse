<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseRequestService;
use App\Services\Purchasing\SupplierInvoiceService;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo Fase 4 Purchasing cycle: a Purchase Request restocking the
 * two raw materials the blueprint's MRP example (§7) shows as short for a
 * 100-unit CHR-X1 run (Busa, Cat) is submitted, approved, converted to a
 * PO, approved and sent, received, put away through Incoming QC (fully
 * accepted — the reject scenario already has a live example from Fase
 * 3.5's Production Order QC demo), and invoiced with an auto-matched
 * 3-way match — so every Purchasing list/detail page has a real non-empty
 * example instead of being empty on a fresh install.
 *
 * Requires MasterDataDemoSeeder (product/supplier/tax code) to have run
 * first. Idempotent via a fixed PR number.
 */
class PurchasingDemoSeeder extends Seeder
{
    private const PR_NUMBER = 'PR-DEMO-01';

    /**
     * product code => quantity to restock.
     *
     * @var array<string, float>
     */
    private const LINES = [
        'MAT-BUSA' => 40,
        'MAT-CAT' => 20,
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        if (PurchaseRequest::where('company_id', $company->id)->where('number', self::PR_NUMBER)->exists()) {
            return;
        }

        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'WH-RM')->first();
        $supplier = Supplier::where('company_id', $company->id)->where('code', 'SUP-0001')->first();
        $taxCode = TaxCode::where('company_id', $company->id)->where('code', 'PPN11')->first();
        $user = User::first();

        if (! $warehouse || ! $supplier || ! $taxCode) {
            return;
        }

        $pr = PurchaseRequest::create([
            'company_id' => $company->id,
            'number' => self::PR_NUMBER,
            'warehouse_id' => $warehouse->id,
            'notes' => 'Restock demo: Busa & Cat (shortage per contoh MRP blueprint §7).',
            'status' => 'draft',
            'requested_by' => $user?->id,
        ]);

        $prLines = [];
        foreach (self::LINES as $productCode => $quantity) {
            $product = Product::where('company_id', $company->id)->where('code', $productCode)->first();

            if (! $product) {
                continue;
            }

            $prLines[$productCode] = $pr->lines()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        if ($prLines === []) {
            return;
        }

        $prService = app(PurchaseRequestService::class);
        $pr = $prService->submit($pr->fresh());
        $pr = $prService->approve($pr, $user ?? User::factory()->create());

        $poLineInputs = [];
        foreach ($prLines as $productCode => $prLine) {
            $product = Product::where('company_id', $company->id)->where('code', $productCode)->first();

            $poLineInputs[] = [
                'purchase_request_line_id' => $prLine->id,
                'quantity' => (float) $prLine->quantity,
                'unit_price' => (float) $product->standard_cost,
                'tax_code_id' => $taxCode->id,
            ];
        }

        $poService = app(PurchaseOrderService::class);
        $order = $poService->createFromRequest($pr, $supplier->id, $warehouse->id, $poLineInputs, $user);
        $order = $poService->submitForApproval($order);
        $order = $poService->approve($order, $user ?? User::first());
        $order = $poService->send($order);

        $grService = app(GoodsReceiptService::class);
        $receiveLines = $order->lines->map(fn ($line) => [
            'purchase_order_line_id' => $line->id,
            'quantity_received' => (float) $line->quantity,
        ])->all();

        $receipt = $grService->receive($order->fresh(), $receiveLines, $user);

        $putAwayLines = $receipt->lines->map(fn ($line) => [
            'goods_receipt_line_id' => $line->id,
            'quantity_accepted' => (float) $line->quantity_received,
        ])->all();

        $grService->inspectAndPutAway($receipt, $putAwayLines);

        $invoiceLines = $order->fresh('lines')->lines->map(fn ($line) => [
            'purchase_order_line_id' => $line->id,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
        ])->all();

        app(SupplierInvoiceService::class)->create(
            $order->fresh(),
            [
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($supplier->payment_term_days ?? 30)->toDateString(),
            ],
            $invoiceLines,
            $user,
        );
    }
}
