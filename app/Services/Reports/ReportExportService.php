<?php

namespace App\Services\Reports;

use App\Models\ReportExportRun;
use App\Services\AuditTrailService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ReportExportService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly ReportSnapshotService $snapshots,
        private readonly AuditTrailService $audit,
    ) {
    }

    public function pdf(array $parameters): Response
    {
        $snapshot = $this->snapshot($parameters);
        $run = $this->createRun($snapshot, 'pdf', $parameters);
        $view = view('reports.financial-pdf', [
            'snapshot' => $snapshot,
            'run' => $run,
        ])->render();
        $orientation = in_array($snapshot['meta']['report'], ['general_ledger', 'trial_balance'], true)
            ? 'landscape'
            : 'portrait';

        $pdf = Pdf::loadHTML($view)->setPaper('a4', $orientation);
        $this->audit->record($run, 'report_exported', null, [
            'format' => 'pdf',
            'report' => $snapshot['meta']['report'],
        ]);

        return $pdf->download($this->filename($snapshot, 'pdf'));
    }

    public function xlsx(array $parameters): Response
    {
        $snapshot = $this->snapshot($parameters);
        $run = $this->createRun($snapshot, 'xlsx', $parameters);
        $spreadsheet = new Spreadsheet();
        $this->coverSheet($spreadsheet, $snapshot, $run);
        $this->parametersSheet($spreadsheet, $snapshot);
        $this->reportSheet($spreadsheet, $snapshot['meta']['report'], $snapshot['data'], $snapshot['meta']['language']);
        if ($snapshot['previous'] !== null) {
            $this->reportSheet($spreadsheet, 'Comparative', $snapshot['previous']['data'], $snapshot['meta']['language']);
        }
        if (! in_array($snapshot['meta']['report'], ['general_ledger', 'trial_balance'], true)) {
            $trialBalance = $this->snapshots->build(
                'trial_balance',
                $snapshot['meta']['from_date'],
                $snapshot['meta']['to_date'],
                null,
                $snapshot['meta']['presentation_currency'],
                $snapshot['meta']['language'],
                false,
            );
            $this->reportSheet($spreadsheet, 'Trial Balance', $trialBalance['data'], $snapshot['meta']['language']);
        }
        if ($snapshot['meta']['report'] !== 'notes') {
            $notes = $this->snapshots->build(
                'notes', $snapshot['meta']['from_date'], $snapshot['meta']['to_date'], null,
                $snapshot['meta']['presentation_currency'], $snapshot['meta']['language'], false,
                $snapshot['meta']['reporting_standard'],
            );
            $this->reportSheet($spreadsheet, 'Notes', $notes['data'], $snapshot['meta']['language']);
        }
        if ($snapshot['meta']['report'] !== 'general_ledger') {
            $ledger = $this->snapshots->build(
                'general_ledger', $snapshot['meta']['from_date'], $snapshot['meta']['to_date'],
                $parameters['account_id'] ?? null, $snapshot['meta']['presentation_currency'],
                $snapshot['meta']['language'], false, $snapshot['meta']['reporting_standard'],
            );
            $this->reportSheet($spreadsheet, 'General Ledger', $ledger['data'], $snapshot['meta']['language']);
        }
        $this->exchangeRatesSheet($spreadsheet, $snapshot);
        $this->warningsSheet($spreadsheet, $snapshot['warnings'], $snapshot['meta']['language']);

        $this->audit->record($run, 'report_exported', null, [
            'format' => 'xlsx',
            'report' => $snapshot['meta']['report'],
        ]);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $this->filename($snapshot, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(array $parameters): array
    {
        $report = (string) ($parameters['report'] ?? 'trial_balance');
        $fromDate = (string) ($parameters['from_date'] ?? now()->startOfYear()->toDateString());
        $toDate = (string) ($parameters['to_date'] ?? now()->toDateString());

        return $this->snapshots->build(
            $report,
            $fromDate,
            $toDate,
            isset($parameters['account_id']) ? (int) $parameters['account_id'] : null,
            $parameters['presentation_currency'] ?? null,
            $parameters['language'] ?? null,
            filter_var($parameters['comparative'] ?? true, FILTER_VALIDATE_BOOLEAN),
            $parameters['reporting_standard'] ?? null,
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function createRun(array $snapshot, string $format, array $parameters): ReportExportRun
    {
        $company = $this->currentCompany->get();
        if (! $company) {
            throw new RuntimeException('Cannot export a report without a company context.');
        }

        return ReportExportRun::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'report_code' => $snapshot['meta']['report'],
            'format' => $format,
            'reporting_standard' => $snapshot['meta']['reporting_standard'],
            'language' => $snapshot['meta']['language'],
            'functional_currency' => $snapshot['meta']['functional_currency'],
            'presentation_currency' => $snapshot['meta']['presentation_currency'],
            'parameters' => $parameters,
            'status' => 'completed',
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    private function coverSheet(Spreadsheet $spreadsheet, array $snapshot, ReportExportRun $run): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cover');
        $labels = $this->labels($snapshot['meta']['language']);
        $rows = [
            ['Nexumi ERP'],
            [$snapshot['company']['name'] ?? ''],
            [$labels['report'], $snapshot['meta']['report']],
            [$labels['framework'], strtoupper(str_replace('_', ' ', $snapshot['meta']['reporting_standard']))],
            [$labels['period'], $snapshot['meta']['from_date'] . ' - ' . $snapshot['meta']['to_date']],
            [$labels['presentation_currency'], $snapshot['meta']['presentation_currency']],
            [$labels['export_id'], $run->id],
            [$labels['generated_at'], $snapshot['meta']['generated_at']],
            [$labels['disclaimer'], $labels['disclaimer_text']],
        ];
        foreach ($rows as $row => $values) {
            foreach ($values as $column => $value) {
                $sheet->setCellValueByColumnAndRow($column + 1, $row + 1, $value);
            }
        }
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(16);
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(70);
    }

    /** @param array<string, mixed> $snapshot */
    private function parametersSheet(Spreadsheet $spreadsheet, array $snapshot): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Parameters');
        $labels = $this->labels($snapshot['meta']['language']);
        $rows = [
            [$labels['parameter'], $labels['value']],
            [$labels['framework'], $snapshot['meta']['reporting_standard']],
            [$labels['language'], $snapshot['meta']['language']],
            [$labels['functional_currency'], $snapshot['meta']['functional_currency']],
            [$labels['presentation_currency'], $snapshot['meta']['presentation_currency']],
            [$labels['presentation_rate'], $snapshot['meta']['presentation_rate']],
            [$labels['rate_date'], $snapshot['meta']['rate_effective_date']],
            [$labels['comparative'], $snapshot['meta']['comparative_available'] ? $labels['yes'] : $labels['no']],
        ];
        $this->writeRows($sheet, $rows);
    }

    /** @param array<string, mixed> $data */
    private function reportSheet(Spreadsheet $spreadsheet, string $title, array $data, string $language = 'en'): void
    {
        $safeTitle = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $title) ?: 'Report', 0, 31);
        $sheet = $spreadsheet->createSheet()->setTitle($safeTitle);
        $labels = $this->labels($language);
        $rows = [[$labels['section'], $labels['code'], $labels['name'], $labels['amount'], $labels['debit'], $labels['credit'], $labels['balance']]];
        $this->flattenReport($data, $rows);
        $this->writeRows($sheet, $rows, true);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function writeRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $rows, bool $filter = false): void
    {
        foreach ($rows as $row => $values) {
            foreach ($values as $column => $value) {
                $cell = $sheet->getCellByColumnAndRow($column + 1, $row + 1);
                $cell->setValue(is_numeric($value) && ! is_bool($value) ? (float) $value : $value);
            }
        }
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($rows[0])));
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCEFF2');
        $sheet->freezePane('A2');
        if ($filter) {
            $sheet->setAutoFilter('A1:' . $lastColumn . max(1, count($rows)));
        }
        foreach (range(1, count($rows[0])) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }

    /** @param array<string, mixed> $data @param array<int, array<int, mixed>> $rows */
    private function flattenReport(array $data, array &$rows, string $section = ''): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $rows[] = [$section ?: (string) $key, $item['code'] ?? '', $item['name'] ?? '', $item['amount'] ?? '', $item['debit'] ?? '', $item['credit'] ?? '', $item['balance'] ?? ''];
                        }
                    }
                } else {
                    $this->flattenReport($value, $rows, $section ?: (string) $key);
                }
            } elseif (is_scalar($value)) {
                $rows[] = [$section ?: (string) $key, '', '', $value, '', '', ''];
            }
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function exchangeRatesSheet(Spreadsheet $spreadsheet, array $snapshot): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Exchange Rates');
        $labels = $this->labels($snapshot['meta']['language']);
        $this->writeRows($sheet, [
            [$labels['from'], $labels['to'], $labels['rate'], $labels['rate_date'], $labels['source_rate_id'], $labels['status']],
            [$snapshot['meta']['functional_currency'], $snapshot['meta']['presentation_currency'], $snapshot['meta']['presentation_rate'], $snapshot['meta']['rate_effective_date'], $snapshot['meta']['rate_source_id'] ?? '', $labels['approved']],
        ], true);
    }

    /** @param array<int, string> $warnings */
    private function warningsSheet(Spreadsheet $spreadsheet, array $warnings, string $language = 'en'): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Warnings');
        $labels = $this->labels($language);
        $rows = [[$labels['warning']]];
        foreach ($warnings ?: [$labels['no_warnings']] as $warning) {
            $rows[] = [$warning];
        }
        $this->writeRows($sheet, $rows);
    }

    /** @return array<string, string> */
    private function labels(string $language): array
    {
        return [
            'id' => [
                'report' => 'Laporan', 'framework' => 'Standar pelaporan', 'period' => 'Periode', 'presentation_currency' => 'Mata uang penyajian', 'export_id' => 'ID ekspor', 'generated_at' => 'Dibuat pada', 'disclaimer' => 'Catatan', 'disclaimer_text' => 'Laporan dibuat oleh sistem dan wajib ditinjau akuntan sebelum pelaporan resmi.', 'parameter' => 'Parameter', 'value' => 'Nilai', 'language' => 'Bahasa', 'functional_currency' => 'Mata uang fungsional', 'presentation_rate' => 'Kurs penyajian', 'rate_date' => 'Tanggal kurs', 'comparative' => 'Komparatif tersedia', 'yes' => 'Ya', 'no' => 'Tidak', 'section' => 'Bagian', 'code' => 'Kode', 'name' => 'Nama', 'amount' => 'Nilai', 'debit' => 'Debit', 'credit' => 'Kredit', 'balance' => 'Saldo', 'from' => 'Dari', 'to' => 'Ke', 'rate' => 'Kurs', 'source_rate_id' => 'ID sumber kurs', 'status' => 'Status', 'approved' => 'Disetujui', 'warning' => 'Peringatan', 'no_warnings' => 'Tidak ada peringatan sistem.',
            ],
            'en' => [
                'report' => 'Report', 'framework' => 'Reporting standard', 'period' => 'Period', 'presentation_currency' => 'Presentation currency', 'export_id' => 'Export ID', 'generated_at' => 'Generated at', 'disclaimer' => 'Disclaimer', 'disclaimer_text' => 'System-generated report. Accountant review is required before statutory filing.', 'parameter' => 'Parameter', 'value' => 'Value', 'language' => 'Language', 'functional_currency' => 'Functional currency', 'presentation_rate' => 'Presentation rate', 'rate_date' => 'Rate effective date', 'comparative' => 'Comparative available', 'yes' => 'Yes', 'no' => 'No', 'section' => 'Section', 'code' => 'Code', 'name' => 'Name', 'amount' => 'Amount', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance', 'from' => 'From', 'to' => 'To', 'rate' => 'Rate', 'source_rate_id' => 'Source rate ID', 'status' => 'Status', 'approved' => 'Approved', 'warning' => 'Warning', 'no_warnings' => 'No system warnings.',
            ],
            'zh' => [
                'report' => '报告', 'framework' => '报告准则', 'period' => '期间', 'presentation_currency' => '列报货币', 'export_id' => '导出编号', 'generated_at' => '生成时间', 'disclaimer' => '说明', 'disclaimer_text' => '系统生成报告，正式申报前须经会计师审核。', 'parameter' => '参数', 'value' => '值', 'language' => '语言', 'functional_currency' => '功能货币', 'presentation_rate' => '列报汇率', 'rate_date' => '汇率生效日期', 'comparative' => '有比较期间', 'yes' => '是', 'no' => '否', 'section' => '部分', 'code' => '代码', 'name' => '名称', 'amount' => '金额', 'debit' => '借方', 'credit' => '贷方', 'balance' => '余额', 'from' => '从', 'to' => '至', 'rate' => '汇率', 'source_rate_id' => '汇率来源编号', 'status' => '状态', 'approved' => '已批准', 'warning' => '警告', 'no_warnings' => '没有系统警告。',
            ],
            'ja' => [
                'report' => 'レポート', 'framework' => '報告基準', 'period' => '期間', 'presentation_currency' => '表示通貨', 'export_id' => '出力ID', 'generated_at' => '生成日時', 'disclaimer' => '注記', 'disclaimer_text' => 'システム生成レポートです。法定報告前に会計担当者の確認が必要です。', 'parameter' => 'パラメータ', 'value' => '値', 'language' => '言語', 'functional_currency' => '機能通貨', 'presentation_rate' => '表示レート', 'rate_date' => 'レート適用日', 'comparative' => '比較期間あり', 'yes' => 'はい', 'no' => 'いいえ', 'section' => '区分', 'code' => 'コード', 'name' => '名称', 'amount' => '金額', 'debit' => '借方', 'credit' => '貸方', 'balance' => '残高', 'from' => '開始', 'to' => '終了', 'rate' => 'レート', 'source_rate_id' => 'レート元ID', 'status' => 'ステータス', 'approved' => '承認済み', 'warning' => '警告', 'no_warnings' => 'システム警告はありません。',
            ],
            'ko' => [
                'report' => '보고서', 'framework' => '보고 기준', 'period' => '기간', 'presentation_currency' => '표시 통화', 'export_id' => '내보내기 ID', 'generated_at' => '생성 시간', 'disclaimer' => '주의', 'disclaimer_text' => '시스템 생성 보고서입니다. 법정 신고 전 회계 담당자의 검토가 필요합니다.', 'parameter' => '매개변수', 'value' => '값', 'language' => '언어', 'functional_currency' => '기능 통화', 'presentation_rate' => '표시 환율', 'rate_date' => '환율 적용일', 'comparative' => '비교 기간 있음', 'yes' => '예', 'no' => '아니요', 'section' => '구분', 'code' => '코드', 'name' => '이름', 'amount' => '금액', 'debit' => '차변', 'credit' => '대변', 'balance' => '잔액', 'from' => '시작', 'to' => '종료', 'rate' => '환율', 'source_rate_id' => '환율 출처 ID', 'status' => '상태', 'approved' => '승인됨', 'warning' => '경고', 'no_warnings' => '시스템 경고가 없습니다.',
            ],
        ][$language] ?? [];
    }

    /** @param array<string, mixed> $snapshot */
    private function filename(array $snapshot, string $extension): string
    {
        return 'nexumi-' . $snapshot['meta']['report'] . '-' . $snapshot['meta']['to_date'] . '.' . $extension;
    }
}
