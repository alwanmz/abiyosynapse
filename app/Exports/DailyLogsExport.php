<?php

namespace App\Exports;

use App\Models\DailyLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyLogsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    private int $row = 0;

    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return DailyLog::with(['user:id,name', 'ticket:id,ticket_number,title'])
            ->when($this->filters['user_id'] ?? null, fn($q, $u) => $q->where('user_id', $u))
            ->when($this->filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('log_date', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('log_date', '<=', $d))
            ->orderBy('log_date', 'desc')
            ->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor Log',
            'Tanggal',
            'User',
            'Tiket Terkait',
            'Kategori',
            'Durasi (menit)',
            'Mood',
            'Tingkat Energi',
            'Deskripsi',
        ];
    }

    public function map($log): array
    {
        $this->row++;

        return [
            $this->row,
            $log->log_number,
            $log->log_date->format('d/m/Y'),
            $log->user?->name ?? '-',
            $log->ticket ? "{$log->ticket->ticket_number} - {$log->ticket->title}" : '-',
            $log->category ?? '-',
            $log->duration_minutes ?? '-',
            $log->mood ?? '-',
            $log->energy_level ?? '-',
            $log->description,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
