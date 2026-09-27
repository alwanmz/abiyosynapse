<?php

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentDefinitionRegistry;
use App\Services\Documents\DocumentExcelExportService;
use App\Services\Documents\DocumentOutputService;
use App\Services\CurrentCompany;
use PhpOffice\PhpSpreadsheet\IOFactory;

function makePurchaseOrderForDocumentTest(User $user): PurchaseOrder
{
    $company = $user->currentCompany()->firstOrFail();
    $supplier = Supplier::factory()->for($company)->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    return PurchaseOrder::create([
        'company_id' => $company->id,
        'number' => 'PO-DOC-0001',
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => '2026-09-18',
        'currency_code' => 'USD',
        'exchange_rate' => '16000',
        'status' => 'approved',
        'subtotal' => '1000.00',
        'tax_total' => '0.00',
        'total' => '1000.00',
        'subtotal_base' => '16000000.000000',
        'tax_total_base' => '0.000000',
        'total_base' => '16000000.000000',
        'created_by' => $user->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);
}

test('document registry exposes every planned document type', function (): void {
    $types = app(DocumentDefinitionRegistry::class)->types();

    expect($types)->toHaveCount(19)
        ->toContain('cash_transaction')
        ->toContain('maintenance_work_order')
        ->toContain('qc_inspection');
});

test('document snapshot and PDF use the same company-scoped source', function (): void {
    $user = User::factory()->create();
    $company = $user->currentCompany()->firstOrFail();
    app(CurrentCompany::class)->set($company);
    $order = makePurchaseOrderForDocumentTest($user);

    $snapshot = app(DocumentOutputService::class)->snapshot('purchase_order', $order->id);

    expect($snapshot['header']['number'])->toBe('PO-DOC-0001')
        ->and($snapshot['header']['currency'])->toBe('USD')
        ->and($snapshot['header']['total_base'])->toBe('16000000.000000')
        ->and($snapshot['approval']['is_approved'])->toBeTrue();

    $response = $this->actingAs($user)->get("/documents/purchase_order/{$order->id}/print");

    expect($response->status())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $this->assertDatabaseHas('audit_logs', [
        'company_id' => $company->id,
        'event' => 'document_printed',
        'auditable_id' => $order->id,
    ]);
});

test('document workbook has numeric values, audit sheet, and export event', function (): void {
    $user = User::factory()->create();
    $company = $user->currentCompany()->firstOrFail();
    app(CurrentCompany::class)->set($company);
    $order = makePurchaseOrderForDocumentTest($user);

    $response = app(DocumentExcelExportService::class)->export('purchase_order', $order->id);
    ob_start();
    $response->sendContent();
    $bytes = ob_get_clean();
    $path = storage_path('app/document-output-test.xlsx');
    file_put_contents($path, $bytes);

    $workbook = IOFactory::load($path);

    expect($workbook->getSheetNames())->toBe(['Document', 'Audit'])
        ->and($workbook->getSheetByName('Document')->getCell('B8')->getValue())->toBe(16000000.0);

    $this->assertDatabaseHas('audit_logs', [
        'company_id' => $company->id,
        'event' => 'document_exported',
        'auditable_id' => $order->id,
    ]);

    @unlink($path);
});

test('document output cannot read another company document', function (): void {
    $user = User::factory()->create();
    $firstCompany = $user->currentCompany()->firstOrFail();
    app(CurrentCompany::class)->set($firstCompany);

    $otherCompany = Company::factory()->create();
    $otherSupplier = Supplier::factory()->for($otherCompany)->create();
    $otherWarehouse = Warehouse::factory()->for($otherCompany)->create();
    $otherOrder = PurchaseOrder::create([
        'company_id' => $otherCompany->id,
        'number' => 'PO-OTHER-0001',
        'supplier_id' => $otherSupplier->id,
        'warehouse_id' => $otherWarehouse->id,
        'order_date' => '2026-09-18',
        'status' => 'draft',
    ]);

    expect(fn () => app(DocumentOutputService::class)->snapshot('purchase_order', $otherOrder->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
