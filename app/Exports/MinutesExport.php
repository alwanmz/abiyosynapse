<?php

namespace App\Exports;

use App\Models\Minute;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MinutesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    private int $row = 0;

    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return Minute::with(['project:id,name', 'creator:id,name'])
            ->when($this->filters['project_id'] ?? null, fn($q, $p) => $q->where('project_id', $p))
            ->when($this->filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('meeting_date', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('meeting_date', '<=', $d))
            ->orderBy('meeting_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'No',
            'Judul Rapat',
            'Tanggal',
            'Lokasi',
            'Proyek',
            'Dibuat Oleh',
            'Peserta',
            'Jml Keputusan',
            'Ringkasan',
        ];
    }

    public function map($minute): array
    {
        $this->row++;

        $attendees = collect($minute->attendees ?? [])->pluck('name')->join(', ');
        $decisionsCount = count($minute->decisions ?? []);

        return [
            $this->row,
            $minute->title,
            $minute->meeting_date->format('d/m/Y'),
            $minute->location ?? '-',
            $minute->project?->name ?? '-',
            $minute->creator?->name ?? '-',
            $attendees ?: '-',
            $decisionsCount,
            $minute->summary ? mb_substr($minute->summary, 0, 200) : '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
