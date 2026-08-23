@php
    $language = in_array($language ?? 'id', ['id', 'en', 'zh', 'ja', 'ko'], true) ? ($language ?? 'id') : 'id';
    $labels = [
        'id' => [
            'number' => 'Nomor', 'status' => 'Status', 'date' => 'Tanggal', 'currency' => 'Mata uang',
            'rate' => 'Kurs', 'total_base' => 'Total base currency', 'detail' => 'Deskripsi / Item',
            'quantity' => 'Qty', 'price' => 'Harga', 'tax' => 'Pajak', 'total' => 'Total',
            'empty' => 'Tidak ada baris detail.', 'subtotal' => 'Subtotal', 'approved' => 'DISETUJUI',
            'prepared' => 'Disiapkan oleh', 'reviewed' => 'Diperiksa oleh', 'approved_by' => 'Disetujui oleh',
            'print' => 'Cetak', 'base' => 'Mata uang dasar',
        ],
        'en' => [
            'number' => 'Number', 'status' => 'Status', 'date' => 'Date', 'currency' => 'Currency',
            'rate' => 'Exchange rate', 'total_base' => 'Base currency total', 'detail' => 'Description / Item',
            'quantity' => 'Qty', 'price' => 'Price', 'tax' => 'Tax', 'total' => 'Total',
            'empty' => 'No detail lines.', 'subtotal' => 'Subtotal', 'approved' => 'APPROVED',
            'prepared' => 'Prepared by', 'reviewed' => 'Reviewed by', 'approved_by' => 'Approved by',
            'print' => 'Print', 'base' => 'Base currency',
        ],
        'zh' => [
            'number' => '编号', 'status' => '状态', 'date' => '日期', 'currency' => '货币',
            'rate' => '汇率', 'total_base' => '本位币总额', 'detail' => '描述 / 项目',
            'quantity' => '数量', 'price' => '价格', 'tax' => '税额', 'total' => '合计',
            'empty' => '没有明细行。', 'subtotal' => '小计', 'approved' => '已批准',
            'prepared' => '编制人', 'reviewed' => '审核人', 'approved_by' => '批准人',
            'print' => '打印', 'base' => '本位币',
        ],
        'ja' => [
            'number' => '番号', 'status' => 'ステータス', 'date' => '日付', 'currency' => '通貨',
            'rate' => '為替レート', 'total_base' => '基準通貨合計', 'detail' => '説明 / 項目',
            'quantity' => '数量', 'price' => '価格', 'tax' => '税額', 'total' => '合計',
            'empty' => '明細はありません。', 'subtotal' => '小計', 'approved' => '承認済み',
            'prepared' => '作成者', 'reviewed' => '確認者', 'approved_by' => '承認者',
            'print' => '印刷', 'base' => '基準通貨',
        ],
        'ko' => [
            'number' => '번호', 'status' => '상태', 'date' => '날짜', 'currency' => '통화',
            'rate' => '환율', 'total_base' => '기준 통화 합계', 'detail' => '설명 / 항목',
            'quantity' => '수량', 'price' => '가격', 'tax' => '세금', 'total' => '합계',
            'empty' => '상세 항목이 없습니다.', 'subtotal' => '소계', 'approved' => '승인됨',
            'prepared' => '작성자', 'reviewed' => '검토자', 'approved_by' => '승인자',
            'print' => '인쇄', 'base' => '기준 통화',
        ],
    ][$language];
    $documentTitles = [
        'id' => ['purchase_order' => 'Pesanan Pembelian', 'sales_order' => 'Pesanan Penjualan', 'goods_receipt' => 'Penerimaan Barang', 'supplier_invoice' => 'Faktur Pemasok', 'sales_invoice' => 'Faktur Penjualan', 'production_order' => 'Perintah Produksi', 'bank_reconciliation' => 'Rekonsiliasi Bank'],
        'en' => ['purchase_order' => 'Purchase Order', 'sales_order' => 'Sales Order', 'goods_receipt' => 'Goods Receipt', 'supplier_invoice' => 'Supplier Invoice', 'sales_invoice' => 'Sales Invoice', 'production_order' => 'Production Order', 'bank_reconciliation' => 'Bank Reconciliation'],
        'zh' => ['purchase_order' => '采购订单', 'sales_order' => '销售订单', 'goods_receipt' => '收货单', 'supplier_invoice' => '供应商发票', 'sales_invoice' => '销售发票', 'production_order' => '生产订单', 'bank_reconciliation' => '银行对账'],
        'ja' => ['purchase_order' => '発注書', 'sales_order' => '販売注文', 'goods_receipt' => '入荷伝票', 'supplier_invoice' => '仕入先請求書', 'sales_invoice' => '売上請求書', 'production_order' => '製造指図', 'bank_reconciliation' => '銀行照合'],
        'ko' => ['purchase_order' => '구매 주문', 'sales_order' => '판매 주문', 'goods_receipt' => '입고 전표', 'supplier_invoice' => '공급업체 송장', 'sales_invoice' => '판매 송장', 'production_order' => '생산 주문', 'bank_reconciliation' => '은행 조정'],
    ][$language];
    $documentTitle = $documentTitles[$documentType] ?? ucwords(str_replace('_', ' ', $documentType));
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ str_replace('_', ' ', $documentType) }}</title>
    <style>
        @page { margin: 34px 38px 42px; }
        body { color: #17202a; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        h1 { color: #0f3d5e; font-size: 17px; margin: 0; }
        h2 { color: #0f3d5e; font-size: 12px; border-bottom: 1px solid #14b8c4; padding-bottom: 4px; margin: 15px 0 7px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #dceff2; color: #0f3d5e; text-align: left; }
        th, td { border-bottom: 1px solid #dbe3e8; padding: 4px 5px; }
        .meta { margin: 12px 0; }
        .meta td { padding: 2px 12px 2px 0; border: 0; }
        .number { text-align: right; white-space: nowrap; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #64748b; font-size: 8px; text-align: center; }
        .stamp { display: inline-block; border: 1px solid #157f4f; color: #157f4f; padding: 8px 14px; transform: rotate(-5deg); font-weight: bold; }
        .signatures { margin-top: 28px; width: 100%; }
        .signatures td { height: 48px; width: 33%; vertical-align: bottom; border: 0; text-align: center; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="footer">Nexumi ERP · {{ $documentTitle }} · {{ $labels['print'] }} #{{ $run->id }}</div>
    <h1>{{ $company->name }}</h1>
    <div class="muted">{{ $company->legal_name ?: $company->address }}</div>
    <h2>{{ $documentTitle }}</h2>
    <table class="meta">
        <tr><td>{{ $labels['number'] }}</td><td><strong>{{ $document->number ?: ('#' . $document->getKey()) }}</strong></td><td>{{ $labels['status'] }}</td><td>{{ $document->status ?: '—' }}</td></tr>
        <tr><td>{{ $labels['date'] }}</td><td>{{ $document->order_date ?: $document->document_date ?: $document->created_at?->toDateString() }}</td><td>{{ $labels['currency'] }}</td><td>{{ $document->currency_code ?: $company->currency }}</td></tr>
        @if ($document->exchange_rate)<tr><td>{{ $labels['rate'] }}</td><td>{{ $document->exchange_rate }}</td><td>{{ $labels['total_base'] }}</td><td>{{ $document->total_base ?: $document->cost_base ?: '—' }}</td></tr>@endif
    </table>

    <table>
        <thead><tr><th>{{ $labels['detail'] }}</th><th class="number">{{ $labels['quantity'] }}</th><th class="number">{{ $labels['price'] }}</th><th class="number">{{ $labels['tax'] }}</th><th class="number">{{ $labels['total'] }}</th></tr></thead>
        <tbody>
        @forelse ($lines as $line)
            <tr>
                <td>{{ $line->description ?: ($line->product?->name ?: $line->item_name ?: '—') }}</td>
                <td class="number">{{ $line->quantity ?: $line->received_quantity ?: '—' }}</td>
                <td class="number">{{ $line->unit_price ?: $line->unit_cost ?: '—' }}</td>
                <td class="number">{{ $line->tax_amount ?: '—' }}</td>
                <td class="number">{{ $line->line_total ?: $line->total ?: $line->amount ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ $labels['empty'] }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="meta">
        <tr><td>{{ $labels['subtotal'] }}</td><td class="number">{{ $document->subtotal ?: '—' }}</td></tr>
        <tr><td>{{ $labels['tax'] }}</td><td class="number">{{ $document->tax_total ?: '—' }}</td></tr>
        <tr><td><strong>{{ $labels['total'] }}</strong></td><td class="number"><strong>{{ $document->total ?: $document->amount ?: $document->cost_base ?: '—' }}</strong></td></tr>
    </table>

    @if ($document->approved_at && $document->approved_by)
        <div class="stamp">{{ $labels['approved'] }}<br><span class="muted">{{ $document->approved_at->toDateString() }}</span></div>
    @endif
    <table class="signatures"><tr><td>{{ $labels['prepared'] }}<br><br>{{ $document->creator?->name ?: '—' }}</td><td>{{ $labels['reviewed'] }}<br><br>________________</td><td>{{ $labels['approved_by'] }}<br><br>{{ $document->approver?->name ?: '________________' }}</td></tr></table>
</body>
</html>
