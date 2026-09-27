<?php

namespace App\Services\Documents;

use App\Models\ApPayment;
use App\Models\ArReceipt;
use App\Models\BankReconciliation;
use App\Models\Bom;
use App\Models\CashTransaction;
use App\Models\DeliveryOrder;
use App\Models\FixedAsset;
use App\Models\GoodsReceipt;
use App\Models\MaintenanceWorkOrder;
use App\Models\NonConformanceReport;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\QualityInspection;
use App\Models\Routing;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SupplierInvoice;
use InvalidArgumentException;

class DocumentDefinitionRegistry
{
    /**
     * The registry is deliberately the single source of truth for both PDF
     * and XLSX output. Relations are eager-loaded here to avoid the output
     * layer silently producing incomplete documents.
     *
     * @var array<string, array{model: class-string, relations: array<int, string>, orientation: string, line_source: string}>
     */
    private const DEFINITIONS = [
        'purchase_request' => ['model' => PurchaseRequest::class, 'relations' => ['warehouse', 'requester', 'approver', 'creator', 'lines.product.baseUnitOfMeasure'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'purchase_order' => ['model' => PurchaseOrder::class, 'relations' => ['supplier', 'warehouse', 'purchaseRequest', 'creator', 'approver', 'lines.product.baseUnitOfMeasure', 'lines.taxCode'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'goods_receipt' => ['model' => GoodsReceipt::class, 'relations' => ['purchaseOrder.supplier', 'warehouse', 'receiver', 'lines.product.baseUnitOfMeasure', 'inspections.product'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'supplier_invoice' => ['model' => SupplierInvoice::class, 'relations' => ['purchaseOrder', 'supplier', 'creator', 'lines.product.baseUnitOfMeasure'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'sales_order' => ['model' => SalesOrder::class, 'relations' => ['customer', 'warehouse', 'creator', 'approver', 'lines.product.baseUnitOfMeasure', 'lines.taxCode'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'delivery_order' => ['model' => DeliveryOrder::class, 'relations' => ['salesOrder.customer', 'warehouse', 'shipper', 'lines.product.baseUnitOfMeasure'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'sales_invoice' => ['model' => SalesInvoice::class, 'relations' => ['salesOrder', 'customer', 'creator', 'lines.product.baseUnitOfMeasure'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'sales_return' => ['model' => SalesReturn::class, 'relations' => ['salesInvoice.customer', 'warehouse', 'creator', 'lines.product.baseUnitOfMeasure'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'ar_receipt' => ['model' => ArReceipt::class, 'relations' => ['customer', 'bankAccount', 'creator', 'lines.salesInvoice'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'ap_payment' => ['model' => ApPayment::class, 'relations' => ['supplier', 'bankAccount', 'creator', 'lines.supplierInvoice'], 'orientation' => 'portrait', 'line_source' => 'lines'],
        'cash_transaction' => ['model' => CashTransaction::class, 'relations' => ['bankAccount', 'counterAccount', 'creator'], 'orientation' => 'portrait', 'line_source' => 'none'],
        'bank_reconciliation' => ['model' => BankReconciliation::class, 'relations' => ['bankAccount', 'creator', 'lines.cashTransaction'], 'orientation' => 'landscape', 'line_source' => 'lines'],
        'production_order' => ['model' => ProductionOrder::class, 'relations' => ['product.baseUnitOfMeasure', 'bom', 'routing', 'warehouse', 'creator', 'components.component.baseUnitOfMeasure', 'operations.workCenter', 'inspections.product'], 'orientation' => 'landscape', 'line_source' => 'production'],
        'bom' => ['model' => Bom::class, 'relations' => ['product', 'lines.component', 'lines.unitOfMeasure'], 'orientation' => 'landscape', 'line_source' => 'bom'],
        'routing' => ['model' => Routing::class, 'relations' => ['product', 'operations.workCenter'], 'orientation' => 'landscape', 'line_source' => 'routing'],
        'qc_inspection' => ['model' => QualityInspection::class, 'relations' => ['product', 'inspector', 'inspectable', 'nonConformanceReport'], 'orientation' => 'portrait', 'line_source' => 'quality'],
        'ncr' => ['model' => NonConformanceReport::class, 'relations' => ['inspection.product', 'inspection.inspectable', 'creator', 'approver'], 'orientation' => 'portrait', 'line_source' => 'ncr'],
        'fixed_asset' => ['model' => FixedAsset::class, 'relations' => ['assetAccount', 'sourceAccount', 'creator', 'activator', 'disposer', 'depreciations'], 'orientation' => 'portrait', 'line_source' => 'asset'],
        'maintenance_work_order' => ['model' => MaintenanceWorkOrder::class, 'relations' => ['equipment', 'creator', 'assignee', 'completer'], 'orientation' => 'portrait', 'line_source' => 'maintenance'],
    ];

    /** @return array{model: class-string, relations: array<int, string>, orientation: string, line_source: string} */
    public function get(string $type): array
    {
        return self::DEFINITIONS[$type] ?? throw new InvalidArgumentException("Unsupported document type [{$type}].");
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function title(string $type, string $language): string
    {
        $titles = [
            'purchase_request' => ['id' => 'Permintaan Pembelian', 'en' => 'Purchase Request', 'zh' => '采购申请', 'ja' => '購買依頼', 'ko' => '구매 요청'],
            'purchase_order' => ['id' => 'Pesanan Pembelian', 'en' => 'Purchase Order', 'zh' => '采购订单', 'ja' => '発注書', 'ko' => '구매 주문'],
            'goods_receipt' => ['id' => 'Penerimaan Barang', 'en' => 'Goods Receipt', 'zh' => '收货单', 'ja' => '入荷伝票', 'ko' => '입고 전표'],
            'supplier_invoice' => ['id' => 'Faktur Pemasok', 'en' => 'Supplier Invoice', 'zh' => '供应商发票', 'ja' => '仕入先請求書', 'ko' => '공급업체 송장'],
            'sales_order' => ['id' => 'Pesanan Penjualan', 'en' => 'Sales Order', 'zh' => '销售订单', 'ja' => '販売注文', 'ko' => '판매 주문'],
            'delivery_order' => ['id' => 'Surat Jalan', 'en' => 'Delivery Order', 'zh' => '送货单', 'ja' => '出荷伝票', 'ko' => '출고 전표'],
            'sales_invoice' => ['id' => 'Faktur Penjualan', 'en' => 'Sales Invoice', 'zh' => '销售发票', 'ja' => '売上請求書', 'ko' => '판매 송장'],
            'sales_return' => ['id' => 'Retur Penjualan', 'en' => 'Sales Return', 'zh' => '销售退货', 'ja' => '販売返品', 'ko' => '판매 반품'],
            'ar_receipt' => ['id' => 'Penerimaan Piutang', 'en' => 'AR Receipt', 'zh' => '应收款收款', 'ja' => '売掛金入金', 'ko' => '미수금 수금'],
            'ap_payment' => ['id' => 'Pembayaran Utang', 'en' => 'AP Payment', 'zh' => '应付款付款', 'ja' => '買掛金支払', 'ko' => '미지급금 지급'],
            'cash_transaction' => ['id' => 'Transaksi Kas/Bank', 'en' => 'Cash/Bank Transaction', 'zh' => '现金/银行交易', 'ja' => '現金・銀行取引', 'ko' => '현금/은행 거래'],
            'bank_reconciliation' => ['id' => 'Rekonsiliasi Bank', 'en' => 'Bank Reconciliation', 'zh' => '银行对账', 'ja' => '銀行照合', 'ko' => '은행 조정'],
            'production_order' => ['id' => 'Perintah Produksi', 'en' => 'Production Order', 'zh' => '生产订单', 'ja' => '製造指図', 'ko' => '생산 주문'],
            'bom' => ['id' => 'Bill of Materials', 'en' => 'Bill of Materials', 'zh' => '物料清单', 'ja' => '部品表', 'ko' => '자재 명세서'],
            'routing' => ['id' => 'Routing Produksi', 'en' => 'Production Routing', 'zh' => '生产工艺路线', 'ja' => '製造工程', 'ko' => '생산 라우팅'],
            'qc_inspection' => ['id' => 'Inspeksi Kualitas', 'en' => 'Quality Inspection', 'zh' => '质量检验', 'ja' => '品質検査', 'ko' => '품질 검사'],
            'ncr' => ['id' => 'Laporan Ketidaksesuaian', 'en' => 'Non-Conformance Report', 'zh' => '不合格报告', 'ja' => '不適合報告', 'ko' => '부적합 보고서'],
            'fixed_asset' => ['id' => 'Register Aset Tetap', 'en' => 'Fixed Asset Register', 'zh' => '固定资产登记', 'ja' => '固定資産台帳', 'ko' => '고정 자산 대장'],
            'maintenance_work_order' => ['id' => 'Perintah Kerja Maintenance', 'en' => 'Maintenance Work Order', 'zh' => '维护工单', 'ja' => '保全作業指図', 'ko' => '유지보수 작업 지시'],
        ];

        return $titles[$type][$language] ?? $titles[$type]['en'] ?? ucwords(str_replace('_', ' ', $type));
    }
}
