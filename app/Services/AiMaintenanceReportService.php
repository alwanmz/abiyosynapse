<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AiMaintenanceReportService
{
    private const STATUS_RESULT_OPTIONS = ['Berhasil', 'Sebagian', 'Gagal', 'Perlu Tindak Lanjut'];

    public function __construct(protected AiService $aiService) {}

    /**
     * Draft a maintenance report summary + work items from tickets that were
     * marked "done" for the given client/project within the period.
     *
     * @return array{summary: string, items: array<int, array{ticket_id: int, category: string, found_at: ?string, description: string, resolution: string, status_result: string, resolved_at: ?string}>}
     */
    public function draftFromDoneTickets(int $clientId, ?int $projectId, string $periodStart, string $periodEnd): array
    {
        $tickets = Ticket::where('client_id', $clientId)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->where('status', 'done')
            ->whereBetween('updated_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
            ->with('taskType:id,nama')
            ->orderBy('updated_at')
            ->limit(50)
            ->get();

        if ($tickets->isEmpty()) {
            return ['summary' => '', 'items' => []];
        }

        $validTicketIds = $tickets->pluck('id')->all();

        $payload = $tickets->map(fn (Ticket $t) => [
            'ticket_id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'title' => $t->title,
            'description' => mb_substr((string) $t->description, 0, 500),
            'review_notes' => mb_substr((string) $t->review_notes, 0, 500),
            'category' => $t->taskType?->nama,
            'found_at' => optional($t->created_at)->toDateString(),
            'resolved_at' => optional($t->resolved_at ?? $t->closed_at ?? $t->updated_at)->toDateString(),
        ])->values()->all();

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statusList = implode(', ', self::STATUS_RESULT_OPTIONS);

        $prompt = <<<PROMPT
Anda adalah asisten PM yang menyusun draft Laporan Maintenance untuk klien, berdasarkan daftar tiket yang sudah selesai dikerjakan pada suatu periode.

Daftar Tiket Selesai (JSON):
{$payloadJson}

Tugas:
1. Buat "summary": ringkasan eksekutif 2-4 kalimat, profesional, Bahasa Indonesia, merangkum pekerjaan pemeliharaan pada periode ini berdasarkan tiket-tiket di atas. Jangan mengarang informasi di luar data yang diberikan.
2. Buat "items": satu entri untuk SETIAP tiket di atas (jangan menggabungkan, jangan menambah tiket baru). Setiap entri berisi:
   - "ticket_id": WAJIB persis salah satu ticket_id dari data di atas.
   - "category": kategori singkat pekerjaan (pakai field category dari data jika ada, atau simpulkan dari title/description bila kosong).
   - "found_at": tanggal temuan, format YYYY-MM-DD. Gunakan field found_at dari data jika ada.
   - "description": uraian temuan/keluhan awal, padat, 1-2 kalimat, Bahasa Indonesia (berdasarkan title/description tiket).
   - "resolution": uraian penyelesaian yang dilakukan, padat, 1-2 kalimat, Bahasa Indonesia (berdasarkan review_notes bila ada, atau simpulkan dari description bila review_notes kosong).
   - "status_result": pilih salah satu persis dari: {$statusList}. Karena tiket ini sudah berstatus selesai, gunakan "Berhasil" kecuali deskripsi tiket jelas menunjukkan hasil sebagian/gagal/perlu tindak lanjut.
   - "resolved_at": tanggal penyelesaian, format YYYY-MM-DD. Gunakan field resolved_at dari data jika ada.

Format Output HARUS JSON object murni, tanpa markdown, tanpa penjelasan lain:
{
  "summary": "...",
  "items": [
    {"ticket_id": 123, "category": "...", "found_at": "2026-08-01", "description": "...", "resolution": "...", "status_result": "Berhasil", "resolved_at": "2026-08-02"}
  ]
}
PROMPT;

        try {
            $answer = $this->aiService->generateText($prompt);
        } catch (RuntimeException $e) {
            Log::warning('AI maintenance report draft failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $decoded = $this->decodeJsonObject($answer);

        return [
            'summary' => $this->cleanText($decoded['summary'] ?? null, 10000) ?? '',
            'items' => $this->sanitizeItems($decoded['items'] ?? [], $validTicketIds),
        ];
    }

    /**
     * @param  array<int, int>  $validTicketIds
     * @return array<int, array{ticket_id: int, category: string, found_at: ?string, description: string, resolution: string, status_result: string, resolved_at: ?string}>
     */
    private function sanitizeItems(mixed $items, array $validTicketIds): array
    {
        if (! is_array($items)) {
            return [];
        }

        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ticketId = (int) ($item['ticket_id'] ?? 0);
            if (! in_array($ticketId, $validTicketIds, true) || isset($seen[$ticketId])) {
                continue;
            }

            $description = $this->cleanText($item['description'] ?? null, 2000);
            if ($description === null) {
                continue;
            }

            $seen[$ticketId] = true;

            $statusResult = $item['status_result'] ?? null;
            $statusResult = in_array($statusResult, self::STATUS_RESULT_OPTIONS, true)
                ? $statusResult
                : 'Berhasil';

            $result[] = [
                'ticket_id' => $ticketId,
                'category' => $this->cleanText($item['category'] ?? null, 100) ?? '',
                'found_at' => $this->cleanDate($item['found_at'] ?? null),
                'description' => $description,
                'resolution' => $this->cleanText($item['resolution'] ?? null, 2000) ?? '',
                'status_result' => $statusResult,
                'resolved_at' => $this->cleanDate($item['resolved_at'] ?? null),
            ];
        }

        return $result;
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    private function cleanDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function decodeJsonObject(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('AI tidak mengembalikan draft laporan yang valid.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI tidak mengembalikan draft laporan yang valid: '.json_last_error_msg());
        }

        return $decoded;
    }
}
