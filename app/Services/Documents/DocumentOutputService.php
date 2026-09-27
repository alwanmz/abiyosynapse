<?php

namespace App\Services\Documents;

use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

class DocumentOutputService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly DocumentDefinitionRegistry $registry,
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshot(string $type, int $documentId): array
    {
        $company = $this->currentCompany->get();
        if (! $company) {
            throw new RuntimeException('Cannot output a document without a company context.');
        }

        $definition = $this->registry->get($type);
        $model = $definition['model'];
        /** @var Model $document */
        $document = $model::query()
            ->where('company_id', $company->id)
            ->findOrFail($documentId);
        $document->load($definition['relations']);

        $language = in_array($company->reporting_language, ['id', 'en', 'zh', 'ja', 'ko'], true)
            ? $company->reporting_language
            : 'id';
        $currency = $this->firstAttribute($document, ['currency_code', 'cost_currency_code']) ?: $company->currency;
        $date = $this->firstAttribute($document, [
            'document_date', 'order_date', 'invoice_date', 'received_date', 'delivery_date',
            'return_date', 'receipt_date', 'payment_date', 'transaction_date', 'statement_date',
            'acquisition_date', 'inspected_at', 'scheduled_at', 'created_at',
        ]);

        return [
            'type' => $type,
            'title' => $this->registry->title($type, $language),
            'orientation' => $definition['orientation'],
            'company' => $company,
            'document' => $document,
            'header' => [
                'number' => $this->firstAttribute($document, ['number']) ?: '#' . $document->getKey(),
                'status' => $this->firstAttribute($document, ['status']) ?: '—',
                'date' => $this->formatDate($date),
                'reference' => $this->firstAttribute($document, ['reference', 'supplier_reference', 'notes']),
                'party' => $this->partyName($document),
                'warehouse' => $this->relationValue($document, 'warehouse')?->code,
                'currency' => $currency,
                'exchange_rate' => $this->firstAttribute($document, ['exchange_rate', 'cost_exchange_rate']),
                'base_currency' => $company->currency,
                'total_base' => $this->firstAttribute($document, ['total_base', 'amount_base', 'cost_base', 'actual_total_cost_base']),
            ],
            'sections' => $this->sections($type, $document, $language),
            'totals' => $this->totals($document, $language),
            'approval' => [
                'prepared_by' => $this->relationValue($document, 'creator')?->name ?? $this->relationValue($document, 'requester')?->name,
                'approved_by' => $this->relationValue($document, 'approver')?->name,
                'approved_at' => $this->formatDate($document->approved_at ?? null),
                'is_approved' => filled($document->approved_at ?? null) && filled($document->approved_by ?? null),
            ],
            'labels' => $this->labels($language),
            'language' => $language,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sections(string $type, Model $document, string $language): array
    {
        $label = fn (string $key): string => $this->labels($language)[$key] ?? ucfirst(str_replace('_', ' ', $key));

        return match ($type) {
            'production_order' => [
                ['title' => $label('components'), 'columns' => $this->columns($label, ['description', 'required', 'issued', 'uom']), 'rows' => $document->components->map(fn ($line) => [
                    'description' => $line->component?->name ?: '—',
                    'required' => $line->required_quantity,
                    'issued' => $line->issued_quantity,
                    'uom' => $line->component?->baseUnitOfMeasure?->code ?: '—',
                ])->all()],
                ['title' => $label('operations'), 'columns' => $this->columns($label, ['sequence', 'operation', 'work_center', 'planned_minutes', 'actual_minutes', 'output', 'status']), 'rows' => $document->operations->map(fn ($line) => [
                    'sequence' => $line->sequence,
                    'operation' => $line->name,
                    'work_center' => $line->workCenter?->code ?: '—',
                    'planned_minutes' => $line->planned_minutes,
                    'actual_minutes' => $line->actual_minutes,
                    'output' => $line->output_quantity,
                    'status' => $line->status,
                ])->all()],
            ],
            'bom' => [[
                'title' => $label('components'),
                'columns' => $this->columns($label, ['sequence', 'description', 'quantity_per_batch', 'uom', 'scrap']),
                'rows' => $document->lines->map(fn ($line) => [
                    'sequence' => $line->sequence,
                    'description' => $line->component?->name ?: '—',
                    'quantity_per_batch' => $line->quantity_per_batch,
                    'uom' => $line->unitOfMeasure?->code ?: '—',
                    'scrap' => $line->scrap_percentage,
                ])->all(),
            ]],
            'routing' => [[
                'title' => $label('operations'),
                'columns' => $this->columns($label, ['sequence', 'operation', 'work_center', 'setup_minutes', 'run_minutes', 'inspection_point']),
                'rows' => $document->operations->map(fn ($line) => [
                    'sequence' => $line->sequence,
                    'operation' => $line->name,
                    'work_center' => $line->workCenter?->code ?: '—',
                    'setup_minutes' => $line->setup_minutes,
                    'run_minutes' => $line->run_minutes_per_unit,
                    'inspection_point' => $line->is_inspection_point ? $label('yes') : $label('no'),
                ])->all(),
            ]],
            'qc_inspection' => [[
                'title' => $label('inspection_result'),
                'columns' => $this->columns($label, ['description', 'quantity_inspected', 'quantity_passed', 'quantity_failed', 'result']),
                'rows' => [[
                    'description' => $document->product?->name ?: '—',
                    'quantity_inspected' => $document->quantity_inspected,
                    'quantity_passed' => $document->quantity_passed,
                    'quantity_failed' => $document->quantity_failed,
                    'result' => $document->result,
                ]],
            ]],
            'ncr' => [[
                'title' => $label('non_conformance'),
                'columns' => $this->columns($label, ['description', 'status', 'disposition', 'corrective_action']),
                'rows' => [[
                    'description' => $document->description,
                    'status' => $document->status,
                    'disposition' => $document->disposition ?: '—',
                    'corrective_action' => $document->corrective_action ?: '—',
                ]],
            ]],
            'fixed_asset' => [[
                'title' => $label('asset_summary'),
                'columns' => $this->columns($label, ['description', 'cost', 'accumulated_depreciation', 'book_value', 'status']),
                'rows' => [[
                    'description' => $document->name,
                    'cost' => $document->acquisition_cost,
                    'accumulated_depreciation' => $document->accumulated_depreciation,
                    'book_value' => max(0, (float) $document->acquisition_cost - (float) $document->accumulated_depreciation),
                    'status' => $document->status,
                ]],
            ]],
            'maintenance_work_order' => [[
                'title' => $label('maintenance_summary'),
                'columns' => $this->columns($label, ['description', 'type', 'priority', 'downtime_minutes', 'cost_base', 'status']),
                'rows' => [[
                    'description' => $document->equipment?->name ?: '—',
                    'type' => $document->type,
                    'priority' => $document->priority,
                    'downtime_minutes' => $document->downtime_minutes,
                    'cost_base' => $document->cost_base,
                    'status' => $document->status,
                ]],
            ]],
            'cash_transaction' => [[
                'title' => $label('transaction'),
                'columns' => $this->columns($label, ['bank_account', 'type', 'counter_account', 'description', 'reference', 'amount']),
                'rows' => [[
                    'bank_account' => $document->bankAccount?->name ?: '—',
                    'type' => $document->type === 'in' ? $label('inflow') : $label('outflow'),
                    'counter_account' => $document->counterAccount?->code ?: '—',
                    'description' => $document->description ?: '—',
                    'reference' => $document->reference ?: '—',
                    'amount' => $document->amount,
                ]],
            ]],
            'bank_reconciliation' => [[
                'title' => $label('transactions'),
                'columns' => $this->columns($label, ['transaction', 'date', 'description', 'amount', 'cleared']),
                'rows' => $document->lines->map(fn ($line) => [
                    'transaction' => $line->cashTransaction?->number ?: '—',
                    'date' => $this->formatDate($line->cashTransaction?->transaction_date),
                    'description' => $line->cashTransaction?->description ?: '—',
                    'amount' => $line->cashTransaction?->amount,
                    'cleared' => $line->is_cleared ? $label('yes') : $label('no'),
                ])->all(),
            ]],
            default => [$this->standardSection($document, $label)],
        };
    }

    /** @return array<string, mixed> */
    private function standardSection(Model $document, callable $label): array
    {
        $lines = method_exists($document, 'lines') ? $document->lines : collect();

        return [
            'title' => $label('details'),
            'columns' => $this->columns($label, ['description', 'quantity', 'uom', 'unit_price', 'tax', 'total']),
            'rows' => $lines->map(function ($line) {
                return [
                    'description' => $line->description ?: $line->product?->name ?: $line->item_name ?: $line->name ?: $line->salesInvoice?->number ?: $line->supplierInvoice?->number ?: '—',
                    'quantity' => $this->firstAttribute($line, ['quantity', 'received_quantity', 'accepted_quantity', 'amount_applied']),
                    'uom' => $line->product?->baseUnitOfMeasure?->code ?: '—',
                    'unit_price' => $this->firstAttribute($line, ['unit_price', 'unit_cost']),
                    'tax' => $line->tax_amount,
                    'total' => $this->firstAttribute($line, ['line_total', 'total', 'amount', 'amount_applied']),
                ];
            })->all(),
        ];
    }

    /** @return array<int, array{key: string, label: string}> */
    private function columns(callable $label, array $keys): array
    {
        return array_map(fn (string $key): array => ['key' => $key, 'label' => $label($key)], $keys);
    }

    /** @return array<int, array{label: string, value: mixed}> */
    private function totals(Model $document, string $language): array
    {
        $labels = $this->labels($language);
        $fields = [
            'subtotal' => 'subtotal',
            'tax_total' => 'tax_total',
            'total' => 'total',
            'total_base' => 'total_base',
            'statement_balance' => 'statement_balance',
        ];

        $totals = [];
        foreach ($fields as $field => $attribute) {
            if ($document->getAttribute($attribute) !== null) {
                $totals[] = ['label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)), 'value' => $document->getAttribute($attribute)];
            }
        }

        if ($totals === [] && $document->getAttribute('amount') !== null) {
            $totals[] = ['label' => $labels['amount'], 'value' => $document->getAttribute('amount')];
        }

        return $totals;
    }

    private function partyName(Model $document): ?string
    {
        foreach (['supplier', 'customer', 'equipment', 'product', 'bankAccount'] as $relation) {
            $related = $this->relationValue($document, $relation);
            if ($related?->name) {
                return $related->name;
            }
        }

        return null;
    }

    private function relationValue(Model $model, string $relation): ?Model
    {
        if (! method_exists($model, $relation) && ! $model->relationLoaded($relation)) {
            return null;
        }

        $related = $model->getRelationValue($relation);

        return $related instanceof Model ? $related : null;
    }

    private function firstAttribute(Model $model, array $attributes): mixed
    {
        foreach ($attributes as $attribute) {
            $value = $model->getAttribute($attribute);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return $value ? (string) $value : null;
    }

    /** @return array<string, string> */
    public function labels(string $language): array
    {
        $common = [
            'number' => ['id' => 'Nomor', 'en' => 'Number', 'zh' => '编号', 'ja' => '番号', 'ko' => '번호'],
            'status' => ['id' => 'Status', 'en' => 'Status', 'zh' => '状态', 'ja' => 'ステータス', 'ko' => '상태'],
            'date' => ['id' => 'Tanggal', 'en' => 'Date', 'zh' => '日期', 'ja' => '日付', 'ko' => '날짜'],
            'party' => ['id' => 'Pihak', 'en' => 'Party', 'zh' => '往来方', 'ja' => '取引先', 'ko' => '거래처'],
            'warehouse' => ['id' => 'Gudang', 'en' => 'Warehouse', 'zh' => '仓库', 'ja' => '倉庫', 'ko' => '창고'],
            'currency' => ['id' => 'Mata Uang', 'en' => 'Currency', 'zh' => '货币', 'ja' => '通貨', 'ko' => '통화'],
            'exchange_rate' => ['id' => 'Kurs', 'en' => 'Exchange Rate', 'zh' => '汇率', 'ja' => '為替レート', 'ko' => '환율'],
            'base_currency' => ['id' => 'Mata Uang Dasar', 'en' => 'Base Currency', 'zh' => '本位币', 'ja' => '基準通貨', 'ko' => '기준 통화'],
            'base_total' => ['id' => 'Total Base', 'en' => 'Base Total', 'zh' => '本位币合计', 'ja' => '基準通貨合計', 'ko' => '기준 합계'],
            'reference' => ['id' => 'Referensi', 'en' => 'Reference', 'zh' => '参考', 'ja' => '参照', 'ko' => '참조'],
            'approval' => ['id' => 'Approval', 'en' => 'Approval', 'zh' => '审批', 'ja' => '承認', 'ko' => '승인'],
            'prepared_by' => ['id' => 'Disiapkan oleh', 'en' => 'Prepared by', 'zh' => '编制人', 'ja' => '作成者', 'ko' => '작성자'],
            'approved_by' => ['id' => 'Disetujui oleh', 'en' => 'Approved by', 'zh' => '批准人', 'ja' => '承認者', 'ko' => '승인자'],
            'approved_at' => ['id' => 'Waktu Approval', 'en' => 'Approved at', 'zh' => '批准时间', 'ja' => '承認日時', 'ko' => '승인 시간'],
            'approved' => ['id' => 'DISETUJUI', 'en' => 'APPROVED', 'zh' => '已批准', 'ja' => '承認済み', 'ko' => '승인됨'],
            'not_approved' => ['id' => 'BELUM DISETUJUI', 'en' => 'NOT APPROVED', 'zh' => '未批准', 'ja' => '未承認', 'ko' => '미승인'],
            'print' => ['id' => 'Cetak', 'en' => 'Print', 'zh' => '打印', 'ja' => '印刷', 'ko' => '인쇄'],
            'export_id' => ['id' => 'ID Export', 'en' => 'Export ID', 'zh' => '导出编号', 'ja' => '出力ID', 'ko' => '내보내기 ID'],
            'generated_at' => ['id' => 'Dibuat pada', 'en' => 'Generated at', 'zh' => '生成时间', 'ja' => '生成日時', 'ko' => '생성 시간'],
            'details' => ['id' => 'Detail', 'en' => 'Details', 'zh' => '明细', 'ja' => '明細', 'ko' => '상세'],
            'transaction' => ['id' => 'Transaksi', 'en' => 'Transaction', 'zh' => '交易', 'ja' => '取引', 'ko' => '거래'],
            'transactions' => ['id' => 'Transaksi', 'en' => 'Transactions', 'zh' => '交易', 'ja' => '取引', 'ko' => '거래'],
            'bank_account' => ['id' => 'Rekening Bank', 'en' => 'Bank Account', 'zh' => '银行账户', 'ja' => '銀行口座', 'ko' => '은행 계좌'],
            'counter_account' => ['id' => 'Akun Lawan', 'en' => 'Counter Account', 'zh' => '对方科目', 'ja' => '相手勘定', 'ko' => '상대 계정'],
            'cleared' => ['id' => 'Terekonsiliasi', 'en' => 'Cleared', 'zh' => '已勾销', 'ja' => '照合済み', 'ko' => '조정 완료'],
            'inflow' => ['id' => 'Masuk', 'en' => 'Inflow', 'zh' => '流入', 'ja' => '入金', 'ko' => '입금'],
            'outflow' => ['id' => 'Keluar', 'en' => 'Outflow', 'zh' => '流出', 'ja' => '出金', 'ko' => '출금'],
            'components' => ['id' => 'Komponen', 'en' => 'Components', 'zh' => '组件', 'ja' => '部品', 'ko' => '구성품'],
            'operations' => ['id' => 'Operasi', 'en' => 'Operations', 'zh' => '工序', 'ja' => '工程', 'ko' => '작업'],
            'inspection_result' => ['id' => 'Hasil Inspeksi', 'en' => 'Inspection Result', 'zh' => '检验结果', 'ja' => '検査結果', 'ko' => '검사 결과'],
            'non_conformance' => ['id' => 'Ketidaksesuaian', 'en' => 'Non-Conformance', 'zh' => '不合格', 'ja' => '不適合', 'ko' => '부적합'],
            'asset_summary' => ['id' => 'Ringkasan Aset', 'en' => 'Asset Summary', 'zh' => '资产摘要', 'ja' => '資産概要', 'ko' => '자산 요약'],
            'maintenance_summary' => ['id' => 'Ringkasan Maintenance', 'en' => 'Maintenance Summary', 'zh' => '维护摘要', 'ja' => '保全概要', 'ko' => '유지보수 요약'],
            'description' => ['id' => 'Deskripsi / Item', 'en' => 'Description / Item', 'zh' => '描述 / 项目', 'ja' => '説明 / 項目', 'ko' => '설명 / 항목'],
            'quantity' => ['id' => 'Qty', 'en' => 'Qty', 'zh' => '数量', 'ja' => '数量', 'ko' => '수량'],
            'uom' => ['id' => 'UOM', 'en' => 'UOM', 'zh' => '单位', 'ja' => '単位', 'ko' => '단위'],
            'unit_price' => ['id' => 'Harga Satuan', 'en' => 'Unit Price', 'zh' => '单价', 'ja' => '単価', 'ko' => '단가'],
            'tax' => ['id' => 'Pajak', 'en' => 'Tax', 'zh' => '税额', 'ja' => '税額', 'ko' => '세금'],
            'total' => ['id' => 'Total', 'en' => 'Total', 'zh' => '合计', 'ja' => '合計', 'ko' => '합계'],
            'subtotal' => ['id' => 'Subtotal', 'en' => 'Subtotal', 'zh' => '小计', 'ja' => '小計', 'ko' => '소계'],
            'tax_total' => ['id' => 'Total Pajak', 'en' => 'Tax Total', 'zh' => '税额合计', 'ja' => '税額合計', 'ko' => '세금 합계'],
            'total_base' => ['id' => 'Total Mata Uang Dasar', 'en' => 'Base Currency Total', 'zh' => '本位币合计', 'ja' => '基準通貨合計', 'ko' => '기준 통화 합계'],
            'amount' => ['id' => 'Jumlah', 'en' => 'Amount', 'zh' => '金额', 'ja' => '金額', 'ko' => '금액'],
            'statement_balance' => ['id' => 'Saldo Rekening Koran', 'en' => 'Statement Balance', 'zh' => '对账单余额', 'ja' => '明細残高', 'ko' => '명세서 잔액'],
            'required' => ['id' => 'Dibutuhkan', 'en' => 'Required', 'zh' => '需求', 'ja' => '必要量', 'ko' => '필요량'],
            'issued' => ['id' => 'Dikeluarkan', 'en' => 'Issued', 'zh' => '已领料', 'ja' => '払出済', 'ko' => '불출량'],
            'sequence' => ['id' => 'Urutan', 'en' => 'Sequence', 'zh' => '顺序', 'ja' => '順序', 'ko' => '순서'],
            'operation' => ['id' => 'Operasi', 'en' => 'Operation', 'zh' => '工序', 'ja' => '工程', 'ko' => '작업'],
            'work_center' => ['id' => 'Work Center', 'en' => 'Work Center', 'zh' => '工作中心', 'ja' => 'ワークセンター', 'ko' => '작업장'],
            'planned_minutes' => ['id' => 'Menit Rencana', 'en' => 'Planned Minutes', 'zh' => '计划分钟', 'ja' => '計画分', 'ko' => '계획 분'],
            'actual_minutes' => ['id' => 'Menit Aktual', 'en' => 'Actual Minutes', 'zh' => '实际分钟', 'ja' => '実績分', 'ko' => '실제 분'],
            'output' => ['id' => 'Output', 'en' => 'Output', 'zh' => '产出', 'ja' => '出来高', 'ko' => '생산량'],
            'scrap' => ['id' => 'Scrap %', 'en' => 'Scrap %', 'zh' => '报废 %', 'ja' => 'スクラップ %', 'ko' => '스크랩 %'],
            'quantity_per_batch' => ['id' => 'Qty per Batch', 'en' => 'Qty per Batch', 'zh' => '每批数量', 'ja' => 'バッチ数量', 'ko' => '배치 수량'],
            'setup_minutes' => ['id' => 'Setup Menit', 'en' => 'Setup Minutes', 'zh' => '准备分钟', 'ja' => '段取り分', 'ko' => '준비 분'],
            'run_minutes' => ['id' => 'Run Menit/Unit', 'en' => 'Run Minutes/Unit', 'zh' => '运行分钟/单位', 'ja' => '実行分/単位', 'ko' => '실행 분/단위'],
            'inspection_point' => ['id' => 'Titik Inspeksi', 'en' => 'Inspection Point', 'zh' => '检验点', 'ja' => '検査ポイント', 'ko' => '검사 지점'],
            'yes' => ['id' => 'Ya', 'en' => 'Yes', 'zh' => '是', 'ja' => 'はい', 'ko' => '예'],
            'no' => ['id' => 'Tidak', 'en' => 'No', 'zh' => '否', 'ja' => 'いいえ', 'ko' => '아니오'],
            'quantity_inspected' => ['id' => 'Qty Inspeksi', 'en' => 'Qty Inspected', 'zh' => '检验数量', 'ja' => '検査数量', 'ko' => '검사 수량'],
            'quantity_passed' => ['id' => 'Qty Lolos', 'en' => 'Qty Passed', 'zh' => '合格数量', 'ja' => '合格数量', 'ko' => '합격 수량'],
            'quantity_failed' => ['id' => 'Qty Gagal', 'en' => 'Qty Failed', 'zh' => '不合格数量', 'ja' => '不合格数量', 'ko' => '불합격 수량'],
            'result' => ['id' => 'Hasil', 'en' => 'Result', 'zh' => '结果', 'ja' => '結果', 'ko' => '결과'],
            'disposition' => ['id' => 'Disposition', 'en' => 'Disposition', 'zh' => '处置', 'ja' => '処置', 'ko' => '처리'],
            'corrective_action' => ['id' => 'Tindakan Korektif', 'en' => 'Corrective Action', 'zh' => '纠正措施', 'ja' => '是正措置', 'ko' => '시정 조치'],
            'cost' => ['id' => 'Biaya', 'en' => 'Cost', 'zh' => '成本', 'ja' => '取得原価', 'ko' => '원가'],
            'accumulated_depreciation' => ['id' => 'Akumulasi Penyusutan', 'en' => 'Accumulated Depreciation', 'zh' => '累计折旧', 'ja' => '減価償却累計', 'ko' => '감가상각 누계'],
            'book_value' => ['id' => 'Nilai Buku', 'en' => 'Book Value', 'zh' => '账面价值', 'ja' => '帳簿価額', 'ko' => '장부가액'],
            'type' => ['id' => 'Tipe', 'en' => 'Type', 'zh' => '类型', 'ja' => '種別', 'ko' => '유형'],
            'priority' => ['id' => 'Prioritas', 'en' => 'Priority', 'zh' => '优先级', 'ja' => '優先度', 'ko' => '우선순위'],
            'downtime_minutes' => ['id' => 'Downtime Menit', 'en' => 'Downtime Minutes', 'zh' => '停机分钟', 'ja' => '停止分', 'ko' => '다운타임 분'],
            'cost_base' => ['id' => 'Biaya Base', 'en' => 'Base Cost', 'zh' => '本位币成本', 'ja' => '基準通貨コスト', 'ko' => '기준 비용'],
        ];

        return array_map(fn (array $translations): string => $translations[$language] ?? $translations['en'], $common);
    }
}
