<?php

namespace App\Http\Controllers;

use App\Services\AuditTrailService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class DocumentPrintController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const DOCUMENTS = [
        'purchase_request' => \App\Models\PurchaseRequest::class,
        'purchase_order' => \App\Models\PurchaseOrder::class,
        'goods_receipt' => \App\Models\GoodsReceipt::class,
        'supplier_invoice' => \App\Models\SupplierInvoice::class,
        'sales_order' => \App\Models\SalesOrder::class,
        'delivery_order' => \App\Models\DeliveryOrder::class,
        'sales_invoice' => \App\Models\SalesInvoice::class,
        'sales_return' => \App\Models\SalesReturn::class,
        'ar_receipt' => \App\Models\ArReceipt::class,
        'ap_payment' => \App\Models\ApPayment::class,
        'bank_reconciliation' => \App\Models\BankReconciliation::class,
        'production_order' => \App\Models\ProductionOrder::class,
        'bom' => \App\Models\Bom::class,
        'routing' => \App\Models\Routing::class,
        'qc_inspection' => \App\Models\QualityInspection::class,
        'ncr' => \App\Models\NonConformanceReport::class,
        'fixed_asset' => \App\Models\FixedAsset::class,
        'maintenance_work_order' => \App\Models\MaintenanceWorkOrder::class,
    ];

    public function __invoke(
        string $type,
        int $document,
        CurrentCompany $currentCompany,
        AuditTrailService $audit,
    ): Response {
        $class = self::DOCUMENTS[$type] ?? null;
        $company = $currentCompany->get();
        if (! $class || ! $company) {
            abort(404);
        }

        $documentModel = $class::query()->where('company_id', $company->id)->findOrFail($document);
        $this->loadRelations($documentModel);
        $lines = method_exists($documentModel, 'lines') ? $documentModel->lines : collect();
        $run = $audit->record($documentModel, 'document_printed', null, [
            'document_type' => $type,
            'format' => 'pdf',
        ], null, auth()->id());

        $pdf = Pdf::loadView('documents.print', [
            'company' => $company,
            'document' => $documentModel,
            'documentType' => $type,
            'lines' => $lines,
            'run' => $run,
            'language' => $company->reporting_language ?: 'id',
        ])->setPaper('a4', $this->orientation($type));

        return $pdf->download('nexumi-' . $type . '-' . ($documentModel->number ?: $documentModel->getKey()) . '.pdf');
    }

    private function loadRelations(Model $document): void
    {
        $relations = ['lines', 'supplier', 'customer', 'warehouse', 'product', 'equipment', 'workCenter', 'fixedAsset', 'inspection', 'creator', 'approver'];
        foreach ($relations as $relation) {
            if (method_exists($document, $relation)) {
                $document->load($relation);
            }
        }
    }

    private function orientation(string $type): string
    {
        return in_array($type, ['bank_reconciliation', 'production_order', 'bom', 'routing'], true)
            ? 'landscape'
            : 'portrait';
    }
}
