<?php

namespace App\Exports;

use App\Models\MaintenanceReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Client-facing spreadsheet of a single maintenance report: a short header
 * block with the report metadata, then the work items table.
 */
class MaintenanceReportExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    /**
     * Baris tempat header tabel item berada. Dikunci sebagai konstanta karena
     * styles() bisa dipanggil sebelum array(), jadi tidak boleh dihitung ulang
     * saat runtime — blok metadata di atas tabel selalu 9 baris.
     */
    private const TABLE_HEADER_ROW = 10;

    public function __construct(private MaintenanceReport $report) {}

    public function array(): array
    {
        $report = $this->report->loadMissing(['client', 'project', 'items']);

        $rows = [
            ['LAPORAN PEMELIHARAAN SISTEM'],
            [],
            ['Nomor Laporan', $report->report_number],
            ['Judul', $report->title],
            ['Klien', $report->client?->nama ?? '-'],
            ['Proyek', $report->project?->name ?? '-'],
            ['Periode', $report->period_start?->format('d/m/Y') . ' - ' . $report->period_end?->format('d/m/Y')],
            ['Ringkasan', $report->summary ?: '-'],
            [],
        ];

        // Jaga agar blok metadata di atas tetap sinkron dengan TABLE_HEADER_ROW.
        $rows[] = ['No', 'Kategori', 'Detail Pekerjaan', 'Hasil Test', 'Catatan'];

        foreach ($report->items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item->category ?: '-',
                $item->description,
                $item->status_result ?: '-',
                $item->notes ?: '-',
            ];
        }

        if ($report->items->isEmpty()) {
            $rows[] = ['-', '-', 'Belum ada item pekerjaan.', '-', '-'];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 22,
            'C' => 55,
            'D' => 16,
            'E' => 32,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);
        $sheet->getStyle('E')->getAlignment()->setWrapText(true);

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A3:A8' => ['font' => ['bold' => true]],
            self::TABLE_HEADER_ROW => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EFEFEF'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Laporan Maintenance';
    }
}
