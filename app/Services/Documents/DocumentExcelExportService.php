<?php

namespace App\Services\Documents;

use App\Models\AuditLog;
use App\Services\AuditTrailService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExcelExportService
{
    public function __construct(
        private readonly DocumentOutputService $output,
        private readonly AuditTrailService $audit,
    ) {
    }

    public function export(string $type, int $documentId): StreamedResponse
    {
        $snapshot = $this->output->snapshot($type, $documentId);
        /** @var \Illuminate\Database\Eloquent\Model $document */
        $document = $snapshot['document'];
        $run = $this->audit->record($document, 'document_exported', null, [
            'document_type' => $type,
            'format' => 'xlsx',
        ], null, auth()->id());

        $spreadsheet = new Spreadsheet();
        $this->documentSheet($spreadsheet, $snapshot, $run);
        $this->auditSheet($spreadsheet, $snapshot, $run);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static function () use ($writer): void {
                $writer->save('php://output');
            },
            $this->filename($snapshot, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function documentSheet(Spreadsheet $spreadsheet, array $snapshot, AuditLog $run): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Document');
        $company = $snapshot['company'];
        $header = $snapshot['header'];
        $label = fn (string $key, string $fallback): string => $snapshot['labels'][$key] ?? $fallback;
        $row = 1;

        $sheet->setCellValue('A' . $row, $company->name);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF0F3D5E');
        $logoPath = public_path('apple-touch-icon.png');
        if (is_file($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Nexumi ERP');
            $drawing->setDescription('Nexumi ERP');
            $drawing->setPath($logoPath);
            $drawing->setHeight(28);
            $drawing->setCoordinates('D1');
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension($row)->setRowHeight(30);
        }
        $row++;
        $sheet->setCellValue('A' . $row, $snapshot['title']);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);
        $row += 2;

        $metadata = [
            [$label('number', 'Number'), $header['number'], $label('status', 'Status'), $header['status']],
            [$label('date', 'Date'), $header['date'] ?? '—', $label('party', 'Party'), $header['party'] ?? '—'],
            [$label('warehouse', 'Warehouse'), $header['warehouse'] ?? '—', $label('currency', 'Currency'), $header['currency']],
            [$label('exchange_rate', 'Exchange Rate'), $header['exchange_rate'] ?? '—', $label('base_currency', 'Base Currency'), $header['base_currency']],
            [$label('base_total', 'Base Total'), $header['total_base'] ?? '—', $label('export_id', 'Export ID'), $run->id],
        ];
        foreach ($metadata as $values) {
            foreach ($values as $column => $value) {
                $this->setValue($sheet, $column + 1, $row, $value);
            }
            $row++;
        }
        $row++;

        foreach ($snapshot['sections'] as $section) {
            $sheet->setCellValueByColumnAndRow(1, $row, $section['title']);
            $sheet->getStyleByColumnAndRow(1, $row)->getFont()->setBold(true)->getColor()->setARGB('FF0F3D5E');
            $row++;
            $columns = $section['columns'];
            foreach ($columns as $index => $column) {
                $sheet->setCellValueByColumnAndRow($index + 1, $row, $column['label']);
            }
            $this->headerStyle($sheet, $row, count($columns));
            $row++;
            foreach ($section['rows'] as $line) {
                foreach ($columns as $index => $column) {
                    $this->setValue($sheet, $index + 1, $row, $line[$column['key']] ?? null);
                }
                $row++;
            }
            $row++;
        }

        if ($snapshot['totals'] !== []) {
            $sheet->setCellValueByColumnAndRow(1, $row, $label('total', 'Totals'));
            $sheet->getStyleByColumnAndRow(1, $row)->getFont()->setBold(true);
            $row++;
            foreach ($snapshot['totals'] as $total) {
                $sheet->setCellValueByColumnAndRow(1, $row, $total['label']);
                $this->setValue($sheet, 2, $row, $total['value']);
                $row++;
            }
            $row++;
        }

        $sheet->setCellValueByColumnAndRow(1, $row, $label('approval', 'Approval'));
        $sheet->getStyleByColumnAndRow(1, $row)->getFont()->setBold(true);
        $row++;
        $approval = $snapshot['approval'];
        $approvalRows = [
            [$label('prepared_by', 'Prepared by'), $approval['prepared_by'] ?? '—'],
            [$label('approved_by', 'Approved by'), $approval['is_approved'] ? ($approval['approved_by'] ?? '—') : '—'],
            [$label('approved_at', 'Approved at'), $approval['is_approved'] ? ($approval['approved_at'] ?? '—') : '—'],
            [$label('approval', 'Approval') . ' status', $approval['is_approved'] ? $label('approved', 'APPROVED') : $label('not_approved', 'NOT APPROVED')],
        ];
        foreach ($approvalRows as $values) {
            $sheet->setCellValueByColumnAndRow(1, $row, $values[0]);
            $sheet->setCellValueByColumnAndRow(2, $row, $values[1]);
            $row++;
        }

        $lastColumn = max(2, $this->lastColumn($snapshot));
        $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn);
        $sheet->getStyle('A1:' . $lastColumnLetter . $row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A1:' . $lastColumnLetter . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getColumnDimension('A')->setWidth(28);
        for ($column = 2; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $sheet->freezePane('A' . max(1, count($metadata) + 7));
        $sheet->getPageSetup()->setOrientation($snapshot['orientation'] === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->setShowGridlines(false);
        $sheet->getHeaderFooter()->setOddFooter('&L' . $snapshot['title'] . '&RPage &P of &N');
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.35)->setRight(0.35);
        $sheet->getPageSetup()->setPrintArea('A1:' . $lastColumnLetter . $row);
    }

    /** @param array<string, mixed> $snapshot */
    private function auditSheet(Spreadsheet $spreadsheet, array $snapshot, AuditLog $run): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Audit');
        $label = fn (string $key, string $fallback): string => $snapshot['labels'][$key] ?? $fallback;
        $rows = [
            [$label('details', 'Field'), $label('total', 'Value')],
            [$label('export_id', 'Export ID'), $run->id],
            ['Document type', $snapshot['type']],
            [$label('number', 'Document number'), $snapshot['header']['number']],
            ['Company', $snapshot['company']->name],
            [$label('generated_at', 'Generated at'), now()->toDateTimeString()],
            ['Generated by', auth()->user()?->name ?? '—'],
            ['Language', $snapshot['language']],
            [$label('currency', 'Transaction currency'), $snapshot['header']['currency']],
            [$label('exchange_rate', 'Exchange Rate'), $snapshot['header']['exchange_rate'] ?? '—'],
            [$label('base_currency', 'Base Currency'), $snapshot['header']['base_currency']],
            [$label('approval', 'Approval') . ' status', $snapshot['approval']['is_approved'] ? $label('approved', 'APPROVED') : $label('not_approved', 'NOT APPROVED')],
        ];
        foreach ($rows as $row => $values) {
            foreach ($values as $column => $value) {
                $this->setValue($sheet, $column + 1, $row + 1, $value);
            }
        }
        $this->headerStyle($sheet, 1, 2);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(55);
        $sheet->freezePane('A2');
        $sheet->setShowGridlines(false);
    }

    /** @param array<string, mixed> $snapshot */
    private function lastColumn(array $snapshot): int
    {
        return max(2, ...array_map(fn (array $section): int => count($section['columns']), $snapshot['sections']));
    }

    private function setValue(Worksheet $sheet, int $column, int $row, mixed $value): void
    {
        if (is_numeric($value) && ! is_bool($value) && $value !== '') {
            $sheet->setCellValueByColumnAndRow($column, $row, (float) $value);
            return;
        }

        $sheet->setCellValueByColumnAndRow($column, $row, $value ?? '—');
    }

    private function headerStyle(Worksheet $sheet, int $row, int $columns): void
    {
        $end = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $columns));
        $sheet->getStyle('A' . $row . ':' . $end . $row)->getFont()->setBold(true)->getColor()->setARGB('FF0F3D5E');
        $sheet->getStyle('A' . $row . ':' . $end . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCEFF2');
        $sheet->getStyle('A' . $row . ':' . $end . $row)->getAlignment()->setWrapText(true);
    }

    /** @param array<string, mixed> $snapshot */
    private function filename(array $snapshot, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $snapshot['header']['number']) ?: $snapshot['type'];

        return 'nexumi-' . $snapshot['type'] . '-' . $number . '.' . $extension;
    }
}
