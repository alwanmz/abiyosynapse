<?php

namespace App\Services\Onboarding;

use App\Support\DefaultChartOfAccounts;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoaSpreadsheetService
{
    public const MAX_ROWS = 1000;

    private const HEADERS = ['Kode', 'Nama Akun', 'Tipe', 'Saldo Normal', 'Header', 'Kode Induk', 'Pos Laporan'];

    private const TYPE_LABELS = [
        'asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Modal', 'revenue' => 'Pendapatan', 'expense' => 'Beban',
    ];

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('COA');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F3D5E');

        $row = 2;
        foreach (DefaultChartOfAccounts::accounts() as $account) {
            $sheet->fromArray([
                $account['code'],
                $account['name'],
                self::TYPE_LABELS[$account['type']],
                $account['normal_balance'] === 'debit' ? 'Debit' : 'Kredit',
                $account['is_postable'] ? 'Tidak' : 'Ya',
                $account['parent_code'],
                $account['report_line'],
            ], null, 'A' . $row, true);
            $row++;
        }

        $this->listValidation($sheet, 'C', '"Aset,Kewajiban,Modal,Pendapatan,Beban"');
        $this->listValidation($sheet, 'D', '"Debit,Kredit"');
        $this->listValidation($sheet, 'E', '"Ya,Tidak"');
        $this->listValidation($sheet, 'G', "'Pos Laporan'!\$A\$2:\$A\$" . (count(DefaultChartOfAccounts::lineCodes()) + 1));

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $lines = $spreadsheet->createSheet();
        $lines->setTitle('Pos Laporan');
        $lines->fromArray(['Kode Pos', 'Keterangan', 'Laporan'], null, 'A1');
        $lines->getStyle('A1:C1')->getFont()->setBold(true);
        $row = 2;
        foreach (DefaultChartOfAccounts::reportLineOptions() as $option) {
            $lines->fromArray([
                $option['value'],
                $option['label'],
                $option['report'] === 'balance_sheet' ? 'Neraca (Aset/Kewajiban/Modal)' : 'Laba Rugi (Pendapatan/Beban)',
            ], null, 'A' . $row);
            $row++;
        }
        foreach (range('A', 'C') as $column) {
            $lines->getColumnDimension($column)->setAutoSize(true);
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->fromArray([
            ['Petunjuk pengisian template COA Nexumi'],
            ['1. Isi satu akun per baris di sheet COA. Contoh baris sudah terisi dengan COA standar; ubah atau hapus sesuai kebutuhan.'],
            ['2. Kode: unik, huruf/angka/titik/strip, maksimal 30 karakter.'],
            ['3. Tipe: Aset, Kewajiban, Modal, Pendapatan, atau Beban. Anak akun harus bertipe sama dengan induknya.'],
            ['4. Saldo Normal: Debit atau Kredit. Kosongkan untuk mengikuti tipe akun.'],
            ['5. Header: Ya untuk akun induk/pengelompokan (tidak bisa dipakai transaksi), Tidak untuk akun transaksi.'],
            ['6. Kode Induk: kode akun induk, kosongkan untuk akun level teratas.'],
            ['7. Pos Laporan: pilih dari sheet Pos Laporan untuk akun transaksi. Kosongkan untuk memakai pos "lainnya" sesuai tipe.'],
            ['8. Setelah upload, Anda akan memetakan akun inti (Piutang, Utang, Persediaan, Pendapatan, dst.) sebelum COA disimpan.'],
        ], null, 'A1');
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $guide->getColumnDimension('A')->setWidth(120);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static function () use ($writer): void {
                $writer->save('php://output');
            },
            'template-coa-nexumi.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @return array<int, array<string, mixed>> raw draft rows
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw new RuntimeException('File tidak dapat dibaca. Pastikan formatnya .xlsx, .xls, atau .csv sesuai template.');
        }

        $sheet = $spreadsheet->getSheetByName('COA') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($rows) ?? []);

        $columns = [];
        foreach (['code' => 'kode', 'name' => 'nama akun', 'type' => 'tipe', 'normal_balance' => 'saldo normal', 'is_header' => 'header', 'parent_code' => 'kode induk', 'report_line' => 'pos laporan'] as $key => $label) {
            $index = array_search($label, $header, true);
            if ($index === false && in_array($key, ['code', 'name', 'type'], true)) {
                throw new RuntimeException("Kolom \"{$label}\" tidak ditemukan. Gunakan template COA dari Nexumi.");
            }
            $columns[$key] = $index === false ? null : $index;
        }

        $rows = array_filter($rows, fn (array $row) => trim(implode('', array_map('strval', $row))) !== '');

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Template berisi lebih dari ' . self::MAX_ROWS . ' akun.');
        }

        return array_values(array_map(function (array $row) use ($columns): array {
            $draft = [];
            foreach ($columns as $key => $index) {
                if ($index !== null) {
                    $draft[$key] = is_scalar($row[$index] ?? null) ? (string) $row[$index] : '';
                }
            }

            return $draft;
        }, $rows));
    }

    private function listValidation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $column, string $formula): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setFormula1($formula);
        $sheet->setDataValidation("{$column}2:{$column}" . (self::MAX_ROWS + 1), $validation);
    }
}
