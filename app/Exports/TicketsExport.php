<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    private int $row = 0;

    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return Ticket::with([
            'project:id,name',
            'assignedUser:id,name',
            'reporter:id,name',
            'taskType:id,nama',
        ])
            ->when($this->filters['project_id'] ?? null, fn($q, $p) => $q->where('project_id', $p))
            ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($this->filters['priority'] ?? null, fn($q, $p) => $q->where('priority', $p))
            ->when($this->filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor Tiket',
            'Judul',
            'Proyek',
            'Jenis Tugas',
            'Tipe',
            'Prioritas',
            'Status',
            'Dilaporkan Oleh',
            'Ditugaskan Ke',
            'Estimasi Jam',
            'Aktual Jam',
            'Tanggal Tenggat',
            'Tanggal Dibuat',
        ];
    }

    public function map($ticket): array
    {
        $this->row++;

        return [
            $this->row,
            $ticket->ticket_number,
            $ticket->title,
            $ticket->project?->name ?? '-',
            $ticket->taskType?->nama ?? '-',
            ucfirst($ticket->type ?? '-'),
            ucfirst($ticket->priority ?? '-'),
            ucfirst(str_replace('-', ' ', $ticket->status)),
            $ticket->reporter?->name ?? '-',
            $ticket->assignedUser?->name ?? '-',
            $ticket->estimated_hours ?? '-',
            $ticket->actual_hours ?? '-',
            $ticket->due_date?->format('d/m/Y') ?? '-',
            $ticket->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
