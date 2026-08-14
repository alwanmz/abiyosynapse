<?php

namespace App\Services;

use App\Models\MaintenanceReport;
use Illuminate\Support\Carbon;

/**
 * Sequential maintenance-report numbering, following the same locked-lookup
 * approach as TicketNumberingService so concurrent creates cannot collide.
 */
class MaintenanceReportNumberingService
{
    /**
     * Must be called inside a DB::transaction. Produces MR-{YYYYMM}-{NNNN},
     * with the sequence restarting each month.
     */
    public function nextReportNumber(Carbon $periodStart): string
    {
        $prefix = 'MR-' . $periodStart->format('Ym');

        $last = MaintenanceReport::where('report_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $sequence = 1;
        if ($last && preg_match('/-(\d+)$/', $last->report_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (MaintenanceReport::where('report_number', $number)->exists());

        return $number;
    }

    /**
     * Formal letter number: {seq:3digit}/LRMS-{client_code}/SKI/{roman_month}/{year}.
     * Sequence restarts each calendar year, scoped per client (LRMS = Laporan
     * Ringkasan Maintenance Sistem), mirroring the manually-issued letters.
     */
    public function nextLetterNumber(string $clientCode, Carbon $letterDate): string
    {
        $year = $letterDate->format('Y');
        $suffix = 'LRMS-' . strtoupper($clientCode) . '/SKI';

        $last = MaintenanceReport::where('letter_number', 'like', '%/' . $suffix . '/%/' . $year)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $sequence = 1;
        if ($last && preg_match('/^(\d+)\//', $last->letter_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        $roman = $this->toRomanMonth((int) $letterDate->format('n'));

        do {
            $number = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT) . '/' . $suffix . '/' . $roman . '/' . $year;
            $sequence++;
        } while (MaintenanceReport::where('letter_number', $number)->exists());

        return $number;
    }

    private function toRomanMonth(int $month): string
    {
        $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romans[$month - 1] ?? 'I';
    }
}
