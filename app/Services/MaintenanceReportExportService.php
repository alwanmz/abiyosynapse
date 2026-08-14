<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\MaintenanceReport;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Builds the formal Word (.docx) maintenance report for staff, mirroring the
 * manually-written letter format: company letterhead, formal letter number,
 * "Kepada Yth." recipient block, subject line, opening/closing paragraphs,
 * the findings table, and a signature block with the company stamp.
 *
 * The client-facing spreadsheet lives in App\Exports\MaintenanceReportExport.
 */
class MaintenanceReportExportService
{
    /**
     * Render the report and return the path of a temp .docx file. The caller is
     * responsible for streaming it (and deleting it afterwards).
     */
    public function buildDocx(MaintenanceReport $report): string
    {
        $report->loadMissing(['client', 'project', 'creator', 'items']);
        $company = CompanySetting::current();

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);

        $section = $word->addSection([
            'marginTop' => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(2),
            'marginLeft' => Converter::cmToTwip(2.5),
            'marginRight' => Converter::cmToTwip(2.5),
        ]);

        $this->addLetterhead($section, $company);
        $this->addLetterMeta($section, $report);
        $this->addRecipient($section, $report);
        $this->addGreeting($section);
        $this->addOpeningParagraph($section, $report);
        $this->addItemsTable($section, $report);
        $this->addClosingParagraph($section);
        $this->addSignature($section, $report, $company);

        $path = tempnam(sys_get_temp_dir(), 'mr_') . '.docx';
        $word->save($path, 'Word2007');

        return $path;
    }

    private function addLetterhead($section, CompanySetting $company): void
    {
        $logoPath = $this->localPath($company->logo_path);

        if ($logoPath) {
            $section->addImage($logoPath, [
                'height' => 55,
                'alignment' => Jc::CENTER,
            ]);
        }

        $section->addText(
            $company->nama_perusahaan ?: config('app.name'),
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        foreach (array_filter([
            $company->alamat,
            implode(' | ', array_filter([
                $company->telp ? 'Telp: ' . $company->telp : null,
                $company->email ? 'Email: ' . $company->email : null,
            ])),
            implode(' | ', array_filter([
                $company->website,
                $company->instagram ? 'IG: ' . $company->instagram : null,
                $company->linkedin ? 'LinkedIn: ' . $company->linkedin : null,
            ])),
        ]) as $line) {
            $section->addText($line, ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }

        // Garis pemisah kop surat.
        $section->addTextBreak(1);
        $section->addLine([
            'weight' => 2,
            'width' => 460,
            'height' => 0,
            'color' => '333333',
        ]);
        $section->addTextBreak(1);
    }

    /**
     * Nomor & tanggal surat, sejajar seperti kepala surat formal
     * ("Nomor: 001/LRMS-RHK/SKI/IV/2026" ... tanggal di kanan).
     */
    private function addLetterMeta($section, MaintenanceReport $report): void
    {
        $table = $section->addTable();
        $table->addRow();
        $left = $table->addCell(Converter::cmToTwip(9));
        $left->addText(
            'Nomor : ' . ($report->letter_number ?: $report->report_number),
            null,
            ['spaceAfter' => 0]
        );

        $right = $table->addCell(Converter::cmToTwip(8));
        $right->addText(
            ($report->letter_date ?: $report->created_at)?->translatedFormat('d F Y'),
            null,
            ['alignment' => Jc::END, 'spaceAfter' => 0]
        );

        $section->addText(
            'Perihal : ' . $report->title,
            ['bold' => true],
            ['spaceAfter' => 240]
        );
    }

    /**
     * "Kepada Yth." — pakai override per-laporan bila diisi, jika tidak
     * jatuh ke data direktur default milik klien.
     */
    private function addRecipient($section, MaintenanceReport $report): void
    {
        $client = $report->client;

        $name = $report->recipient_name ?: $client?->director_name;
        $title = $report->recipient_title ?: $client?->director_title;
        $address = $report->recipient_address ?: $client?->alamat;

        $section->addText('Kepada Yth.', null, ['spaceAfter' => 0]);

        if ($name) {
            $section->addText($name, ['bold' => true], ['spaceAfter' => 0]);
        }

        if ($title) {
            $section->addText($title, ['bold' => true], ['spaceAfter' => 0]);
        }

        if (! $name && ! $title) {
            $section->addText(
                $client?->nama ?: '-',
                ['bold' => true],
                ['spaceAfter' => 0]
            );
        }

        if ($address && $address !== '-') {
            $section->addText($address, null, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(1);
    }

    private function addGreeting($section): void
    {
        $section->addText('Dengan hormat,', null, ['spaceAfter' => 0]);
    }

    private function addOpeningParagraph($section, MaintenanceReport $report): void
    {
        $period = $report->period_start?->translatedFormat('d F Y') . ' s.d. ' . $report->period_end?->translatedFormat('d F Y');

        $section->addText(
            "Bersama surat ini kami sampaikan Laporan Ringkasan Kegiatan Maintenance Aplikasi {$report->title} untuk periode {$period}. "
            . 'Laporan ini merupakan bagian dari komitmen kami sebagaimana tercantum dalam perjanjian layanan.',
            null,
            ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
        );

        if ($report->summary) {
            $section->addText($report->summary, null, ['alignment' => Jc::BOTH, 'spaceAfter' => 120]);
        }

        $section->addText(
            'Berikut adalah rincian temuan dan tindak lanjut penyelesaiannya:',
            null,
            ['spaceAfter' => 120]
        );
    }

    private function addItemsTable($section, MaintenanceReport $report): void
    {
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $headerFont = ['bold' => true, 'size' => 9];
        $headerCell = ['bgColor' => 'EFEFEF', 'valign' => 'center'];
        $bodyFont = ['size' => 9];

        $columns = [
            ['label' => 'No', 'width' => 0.8],
            ['label' => 'Tanggal Temuan', 'width' => 2.2],
            ['label' => 'Uraian Temuan', 'width' => 3.8],
            ['label' => 'Uraian Penyelesaian', 'width' => 3.8],
            ['label' => 'Status Penyelesaian', 'width' => 2],
            ['label' => 'Tanggal Penyelesaian', 'width' => 2.2],
            ['label' => 'Keterangan', 'width' => 2.5],
        ];

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($columns as $col) {
            $table->addCell(Converter::cmToTwip($col['width']), $headerCell)
                ->addText($col['label'], $headerFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }

        if ($report->items->isEmpty()) {
            $table->addRow();
            $table->addCell(Converter::cmToTwip(array_sum(array_column($columns, 'width'))), ['gridSpan' => count($columns)])
                ->addText('Belum ada item pekerjaan.', $bodyFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }

        foreach ($report->items as $index => $item) {
            $table->addRow();
            $table->addCell(Converter::cmToTwip($columns[0]['width']))->addText((string) ($index + 1), $bodyFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[1]['width']))->addText($item->found_at?->translatedFormat('d F Y') ?: '-', $bodyFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[2]['width']))->addText($item->description, $bodyFont, ['spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[3]['width']))->addText($item->resolution ?: '-', $bodyFont, ['spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[4]['width']))->addText($item->status_result ?: '-', $bodyFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[5]['width']))->addText($item->resolved_at?->translatedFormat('d F Y') ?: '-', $bodyFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(Converter::cmToTwip($columns[6]['width']))->addText($item->notes ?: '-', $bodyFont, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(2);
    }

    private function addClosingParagraph($section): void
    {
        $section->addText(
            'Demikian laporan ini kami sampaikan sebagai dokumentasi resmi atas kegiatan pemeliharaan sistem yang telah dilaksanakan. '
            . 'Laporan ini dapat digunakan sebagai lampiran pendukung dalam proses penagihan (invoice). '
            . 'Atas perhatian dan kerja sama yang baik, kami ucapkan terima kasih.',
            null,
            ['alignment' => Jc::BOTH, 'spaceAfter' => 240]
        );
    }

    private function addSignature($section, MaintenanceReport $report, CompanySetting $company): void
    {
        $section->addText('Hormat kami,', null, ['spaceAfter' => 0]);
        $section->addTextBreak(1);

        $stampPath = $this->localPath($company->stamp_path);
        $signaturePath = $this->localPath($report->signature_path);

        if ($stampPath) {
            $section->addImage($stampPath, ['height' => 90]);
        }

        if ($signaturePath) {
            $section->addImage($signaturePath, ['height' => 60]);
        }

        if (! $stampPath && ! $signaturePath) {
            $section->addTextBreak(3);
        }

        $section->addText(
            $report->signed_by_name ?: ($report->creator?->name ?? '________________'),
            ['bold' => true, 'underline' => 'single'],
            ['spaceAfter' => 0]
        );
        $section->addText(
            $report->signed_by_role ?: 'Penanggung Jawab',
            null,
            ['spaceAfter' => 0]
        );
    }

    /**
     * PhpWord needs a real filesystem path for images. Returns null when the
     * file is missing so the export never hard-fails on a broken logo.
     */
    private function localPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Nilai lama bisa tersimpan sebagai "/storage/xxx" atau "xxx".
        $relative = ltrim(preg_replace('#^/?storage/#', '', $path), '/');

        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        $full = Storage::disk('public')->path($relative);

        return is_file($full) ? $full : null;
    }
}
