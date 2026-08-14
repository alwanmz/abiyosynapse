<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    private int $row = 0;

    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return Project::with(['team:id,name', 'client:id,nama', 'projectManager:id,name'])
            ->withCount([
                'tickets',
                'tickets as tickets_done_count' => fn($q) => $q->where('status', 'done'),
            ])
            ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($this->filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Proyek',
            'Nama Proyek',
            'Klien',
            'Tim',
            'Project Manager',
            'Status',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Total Tiket',
            'Tiket Selesai',
            'Jenis Pekerjaan',
        ];
    }

    public function map($project): array
    {
        $this->row++;

        return [
            $this->row,
            $project->kode_project,
            $project->name,
            $project->client?->nama ?? '-',
            $project->team?->name ?? '-',
            $project->projectManager?->name ?? '-',
            ucfirst(str_replace('_', ' ', $project->status)),
            $project->start_date?->format('d/m/Y') ?? '-',
            $project->end_date?->format('d/m/Y') ?? '-',
            $project->tickets_count,
            $project->tickets_done_count,
            $project->jenis_pekerjaan ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
