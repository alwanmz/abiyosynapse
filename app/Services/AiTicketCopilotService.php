<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class AiTicketCopilotService
{
    public function __construct(protected AiService $aiService) {}

    /**
     * Search historical resolved/closed tickets to check if a matching solution already exists.
     *
     * @param string $newDescription
     * @param int|null $clientId
     * @return array{found: bool, ticket_number: ?string, title: ?string, suggestion: ?string}
     */
    public function findSimilarSolution(string $newDescription, ?int $clientId = null): array
    {
        // 1. Fetch up to 10 most recent resolved/closed tickets with review notes or descriptions
        $query = Ticket::whereIn('status', ['resolved', 'closed', 'done'])
            ->where(function ($q) {
                $q->whereNotNull('review_notes')
                  ->orWhereNotNull('description');
            });

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $tickets = $query->orderBy('updated_at', 'desc')->limit(10)->get();

        if ($tickets->isEmpty()) {
            return ['found' => false, 'ticket_number' => null, 'title' => null, 'suggestion' => null];
        }

        // Build context payload for AI matching
        $historicalData = [];
        foreach ($tickets as $t) {
            $historicalData[] = [
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'problem' => mb_substr($t->description ?? '', 0, 300),
                'solution' => mb_substr($t->review_notes ?? $t->description ?? '', 0, 300),
            ];
        }

        $payloadJson = json_encode($historicalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
Anda adalah AI Technical Support Assistant untuk sistem kesehatan dan manajemen proyek.
Tugas Anda adalah memeriksa apakah keluhan baru dari pengguna sudah pernah diselesaikan di riwayat tiket terdahulu.

Deskripsi Keluhan Baru Pengguna:
"{$newDescription}"

Riwayat Tiket Terdahulu (JSON):
{$payloadJson}

Instruksi:
1. Bandingkan keluhan baru dengan riwayat tiket.
2. Jika ada tiket terdahulu yang kendalanya SANGAT MIRIP (kemiripan > 70%) dan memiliki solusi perbaikan yang jelas:
   - Set "found" = true
   - Ambil "ticket_number" dan "title" dari tiket tersebut
   - Buat ringkasan perbaikan yang ramah, padat, dan instruktif (2-3 kalimat) dalam Bahasa Indonesia untuk membantu pengguna memperbaiki kendalanya sendiri.
3. Jika TIDAK ada yang cocok / mirip (> 70%):
   - Set "found" = false

Format Output HARUS JSON murni tanpa markdown triple backticks:
{
  "found": true,
  "ticket_number": "TCK-123",
  "title": "Judul tiket",
  "suggestion": "Solusi saran perbaikan..."
}
PROMPT;

        try {
            $rawResult = $this->aiService->generateText($prompt);
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawResult));
            $data = json_decode($cleanJson, true);

            if (is_array($data) && !empty($data['found']) && !empty($data['suggestion'])) {
                return [
                    'found' => true,
                    'ticket_number' => $data['ticket_number'] ?? null,
                    'title' => $data['title'] ?? null,
                    'suggestion' => $data['suggestion'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('AI Copilot similar ticket search failed', ['error' => $e->getMessage()]);
        }

        return ['found' => false, 'ticket_number' => null, 'title' => null, 'suggestion' => null];
    }
}
