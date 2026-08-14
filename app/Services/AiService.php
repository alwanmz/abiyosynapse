<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Shared wrapper around two OpenAI-compatible AI APIs.
 *
 * Text generation  → DeepSeek chat/completions (deepseek-chat)
 * Audio transcribe → Groq audio/transcriptions (whisper-large-v3-turbo)
 *   DeepSeek has no audio API, so voice transcription stays on Groq.
 *
 * Provider can be swapped without touching any module — only this file changes.
 */
class AiService
{
    /**
     * Generate text from a single prompt. Optional context items are appended
     * as additional user messages.
     *
     * @param  array<int, string>  $context
     */
    public function generateText(string $prompt, array $context = [], ?string $model = null): string
    {
        $messages = [['role' => 'user', 'content' => $prompt]];

        foreach ($context as $extra) {
            $messages[] = ['role' => 'user', 'content' => $extra];
        }

        return $this->chatCompletion($messages, $model);
    }

    /**
     * Summarize a longer block of text into a short professional summary.
     */
    public function summarize(string $text, int $maxSentences = 3): string
    {
        $prompt = "Ringkas teks berikut dalam Bahasa Indonesia, maksimal {$maxSentences} kalimat, gaya profesional dan padat. Jangan tambahkan opini atau prefix seperti 'Ringkasan:'. Langsung berikan ringkasannya.\n\n---\n{$text}";

        return $this->generateText($prompt);
    }

    /**
     * Polish raw user-typed/dictated text into clean, professional prose.
     * Used by the "Rapihin pakai AI" button in Daily Logs.
     */
    public function polishText(string $rawText, ?string $context = null): string
    {
        $prompt = "Rapikan catatan kerja berikut menjadi paragraf profesional Bahasa Indonesia yang ringkas dan jelas. Pertahankan semua fakta dan angka. Jangan tambahkan informasi yang tidak ada di sumber. Jangan beri prefix seperti 'Hasil:'. Langsung tulis hasilnya.";

        if ($context) {
            $prompt .= "\n\nKonteks tambahan: {$context}";
        }

        $prompt .= "\n\n---\n{$rawText}";

        return $this->generateText($prompt);
    }

    /**
     * Transcribe an audio file via Groq Whisper. Returns plain text.
     */
    public function transcribeAudio(UploadedFile $audio, string $languageHint = 'id-ID'): string
    {
        $apiKey = $this->groqApiKey();
        $language = substr($languageHint, 0, 2); // 'id-ID' → 'id'

        $response = Http::timeout((int) config('services.groq.timeout', 60))
            ->withToken($apiKey)
            ->attach(
                'file',
                file_get_contents($audio->getRealPath()),
                'recording.' . ($audio->getClientOriginalExtension() ?: 'webm'),
                ['Content-Type' => $audio->getMimeType() ?: 'audio/webm'],
            )
            ->post(config('services.groq.base_url') . '/audio/transcriptions', [
                'model'           => config('services.groq.model_audio', 'whisper-large-v3-turbo'),
                'language'        => $language,
                'response_format' => 'text',
            ]);

        if ($response->failed()) {
            $reason = data_get($response->json(), 'error.message', 'Groq transcription failed (HTTP ' . $response->status() . ').');
            Log::warning('Groq transcription failed', ['status' => $response->status(), 'body' => $response->json()]);
            throw new RuntimeException($reason);
        }

        // response_format=text → plain string body, not JSON
        return trim($response->body());
    }

    /**
     * Higher-level analytical task. Passes formatted data to the pro model.
     */
    public function analyze(string $task, string $payload): string
    {
        $prompt = "Anda adalah asisten analitik untuk aplikasi manajemen proyek. "
            . "Tugas: {$task}\n\nData:\n{$payload}\n\n"
            . "Jawab dalam Bahasa Indonesia, profesional, dan actionable. Gunakan markdown bila membantu.";

        return $this->generateText($prompt, [], config('services.deepseek.model_pro'));
    }

    /**
     * Turn a raw voice transcript into a structured ticket draft.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function draftTicket(string $transcript, array $context): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<'PROMPT'
Anda adalah asisten project management untuk aplikasi PM internal.
Ubah transkrip voice Bahasa Indonesia menjadi draft tiket yang siap direview user.

Aturan penting:
- Jawab HANYA JSON object valid, tanpa markdown dan tanpa penjelasan lain.
- Gunakan ID client, project, timeline, task_type, dan assignee hanya dari konteks valid.
- Client wajib dipilih. Gunakan selected_client_id jika tersedia; jika tidak, cocokkan nama/kode client dari transkrip.
- Proyek opsional. Jika selected_project_id tersedia, utamakan project tersebut.
- Jika transkrip hanya menyebut client tanpa project, isi project_id null dan jangan beri warning.
- Timeline hanya boleh diisi jika project_id valid.
- Assignee/pelaksana opsional. Jika project_id valid, pilih dari members project; jika project_id null, boleh pilih dari all_users. Jika tidak jelas, isi null tanpa warning.
- request_type: gunakan "gratis" bila transkrip menyebut gratis/tidak berbayar; gunakan "berbayar" bila jelas pekerjaan berbayar.
- Jika client tidak yakin, isi client_id null dan tambahkan warning. Field opsional yang tidak disebut cukup diisi null.
- Status default adalah "todo". Jangan gunakan backlog atau Draft.
- Title maksimal 90 karakter, deskripsi profesional dan padat dalam Bahasa Indonesia.
- Tags maksimal 8 item, lowercase, tanpa tanda pagar.

Skema JSON yang wajib dipakai:
{
  "title": string,
  "description": string,
  "client_id": number|null,
  "project_id": number|null,
  "timeline_id": number|null,
  "type": "bug"|"feature"|"task"|"improvement"|"documentation",
  "task_type_id": number|null,
  "request_type": "berbayar"|"gratis"|null,
  "priority": "highest"|"high"|"medium"|"low"|"lowest",
  "status": "todo"|"pending"|"inprogress"|"qa-ready"|"qa-test"|"review"|"done",
  "assigned_to": number|null,
  "due_date": "YYYY-MM-DD"|null,
  "estimated_hours": number|null,
  "story_points": 1|2|3|5|8|13|21|null,
  "tags": string[],
  "warnings": string[]
}
PROMPT;

        $answer = $this->generateText(
            "{$prompt}\n\nTranskrip:\n{$transcript}",
            ["Konteks valid aplikasi:\n{$contextJson}"],
            config('services.deepseek.model'),
        );

        $json = $this->extractJsonObject($answer);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI tidak mengembalikan data tiket yang valid: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Answer AI PM questions using a compact application snapshot.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array{role:string, content:string}>  $history
     */
    public function answerProjectQuestion(string $question, array $context, array $history = []): string
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $historyText = collect($history)
            ->take(-6)
            ->map(fn ($message) => strtoupper((string) ($message['role'] ?? 'user')) . ': ' . trim((string) ($message['content'] ?? '')))
            ->filter(fn ($line) => trim($line) !== '')
            ->implode("\n");

        $prompt = <<<'PROMPT'
Anda adalah asisten PM untuk aplikasi project management internal.
Jawab dalam Bahasa Indonesia yang ringkas, tegas, dan actionable.

Konteks yang bisa tersedia di snapshot mencakup modul dasbor, analitik, perusahaan, klien, tim, pengguna, role/hak akses, proyek, linimasa, tiket, jenis tugas, catatan harian, notulensi rapat, laporan, notifikasi, dan stream aktivitas terbaru.

Aturan:
- Jawab hanya berdasarkan snapshot data yang diberikan.
- Gunakan overview untuk angka total, dan gunakan daftar detail untuk nama/aktivitas/PIC/status terbaru.
- Untuk pertanyaan daftar, tampilkan item yang ada di snapshot dan sebutkan jika daftar dibatasi.
- Jika module_catalog menandai modul restricted atau data bernilai null/kosong, jelaskan bahwa akses/data modul itu belum tersedia untuk viewer saat ini.
- Boleh menyebut nama orang, klien, proyek, nomor tiket, status, prioritas, mood, tag, deadline, dan aktivitas jika ada di snapshot.
- Jangan mengarang angka, deadline, nama, PIC, atau status.
- Jangan memberi instruksi teknis internal seperti SQL, nama tabel, nama field JSON, atau struktur backend.
- Jika pertanyaan meminta tindakan ubah data, jelaskan bahwa chat ini masih mode baca dan beri langkah tindak lanjut yang harus dilakukan user.
- Jika pertanyaan sama sekali tidak berkaitan dengan data aplikasi PM ini (proyek, tiket, tim, user, klien, catatan harian, dll), tolak dengan sopan dan arahkan user untuk bertanya seputar data PM. Contoh: "Maaf, aku hanya bisa bantu seputar data aplikasi PM ini — proyek, tiket, tim, klien, dan sejenisnya."
- Gunakan markdown untuk memformat jawaban: bullet list (`-`) untuk daftar, **bold** untuk nama/angka penting, tabel sederhana jika membandingkan beberapa item. Untuk jawaban singkat satu kalimat, tidak perlu markdown.
PROMPT;

        if ($historyText !== '') {
            $prompt .= "\n\nRiwayat chat singkat:\n{$historyText}";
        }

        $prompt .= "\n\nPertanyaan user:\n{$question}";

        return $this->generateText(
            $prompt,
            ["Snapshot data aplikasi:\n{$contextJson}"],
            config('services.deepseek.model'),
        );
    }

    /**
     * Answer a client's question in the self-service portal, strictly scoped to
     * that client's own tickets. The context passed in MUST already be limited to
     * the logged-in client (no internal comments, no staff PIC / hours / notes).
     */
    public function answerClientTicketQuestion(string $question, array $context, array $history = []): string
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $historyText = collect($history)
            ->take(-6)
            ->map(fn ($message) => strtoupper((string) ($message['role'] ?? 'user')).': '.trim((string) ($message['content'] ?? '')))
            ->filter(fn ($line) => trim($line) !== '')
            ->implode("\n");

        $prompt = <<<'PROMPT'
Anda adalah asisten untuk PORTAL KLIEN sebuah perusahaan pengembang software.
Tugas Anda HANYA membantu klien memahami status & progres tiket milik mereka sendiri, berdasarkan snapshot data yang diberikan.

Aturan wajib:
- Jawab HANYA berdasarkan snapshot tiket milik klien ini. Jangan mengarang status, tanggal, nomor tiket, atau progres.
- JANGAN pernah menyebut nama staf/PIC internal, estimasi/aktual jam kerja, catatan internal, atau data klien lain — informasi itu tidak ada di snapshot dan bersifat rahasia.
- Jika ada komentar, penulisnya hanya boleh disebut sebagai "Tim" (dari perusahaan) atau "Anda" (klien).
- Jika pertanyaan di luar konteks tiket klien ini (misalnya cuaca, pengetahuan umum, data internal perusahaan, atau data klien lain), tolak dengan sopan: "Maaf, saya hanya bisa membantu seputar tiket Anda di portal ini."
- Ini mode baca-saja. Jika klien ingin mengubah/menambah sesuatu, arahkan untuk membuat tiket atau menghubungi tim lewat kolom komentar tiket.
- Jawab dalam Bahasa Indonesia yang ramah, ringkas, dan jelas.
- Gunakan markdown bila membantu: bullet (`-`) untuk daftar tiket, **bold** untuk nomor/status penting. Untuk jawaban satu kalimat, tidak perlu markdown.
PROMPT;

        if ($historyText !== '') {
            $prompt .= "\n\nRiwayat chat singkat:\n{$historyText}";
        }

        $prompt .= "\n\nPertanyaan klien:\n{$question}";

        return $this->generateText(
            $prompt,
            ["Snapshot tiket klien:\n{$contextJson}"],
            config('services.deepseek.model'),
        );
    }

    /**
     * Suggest a category from a free-form description, picked from the given list.
     * Returns the slug exactly as found in $allowed, or null if no good match.
     *
     * @param  array<int, string>  $allowed
     */
    public function suggestCategory(string $description, array $allowed): ?string
    {
        $list = implode(', ', $allowed);
        $prompt = "Pilih satu kategori paling cocok untuk deskripsi kerja berikut. "
            . "Jawab HANYA dengan satu kata dari daftar ini: {$list}. "
            . "Jika tidak ada yang cocok, jawab 'other'.\n\nDeskripsi: {$description}";

        $answer = strtolower(trim($this->generateText($prompt)));
        $answer = trim($answer, "\"' .");

        return in_array($answer, $allowed, true) ? $answer : null;
    }

    /**
     * Extract decisions and action items from a meeting transcript or summary.
     *
     * @return array<int, array{text: string, owner_name: string|null, due_date: string|null}>
     */
    public function extractDecisions(string $text): array
    {
        $prompt = <<<'PROMPT'
Kamu adalah asisten PM yang membantu mengekstrak keputusan dan action items dari notulensi rapat.

Baca teks berikut dan ekstrak semua keputusan yang disepakati, tugas yang harus dikerjakan, dan tindak lanjut yang dijanjikan.

Jawab HANYA dengan JSON array valid, tanpa markdown, tanpa penjelasan lain.
Skema setiap item: {"text": string, "owner_name": string|null, "due_date": "YYYY-MM-DD"|null}

Jika tidak ada keputusan atau action item yang jelas, return: []
PROMPT;

        $answer = $this->generateText(
            "{$prompt}\n\nTeks notulensi:\n{$text}",
            [],
            config('services.deepseek.model'),
        );

        $json = $this->extractJsonArray($answer);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI tidak mengembalikan daftar keputusan yang valid: ' . json_last_error_msg());
        }

        return array_values(
            array_filter($decoded, fn ($item) => is_array($item) && ! empty(trim((string) ($item['text'] ?? ''))))
        );
    }

    /**
     * Returns true when the DeepSeek API key (used by all text features) is
     * configured. Voice transcription has its own Groq key check, since it's
     * a separate provider.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.deepseek.api_key'));
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function apiKey(): string
    {
        $key = config('services.deepseek.api_key');
        if (! $key) {
            throw new RuntimeException('DEEPSEEK_API_KEY belum di-set di file .env. Ambil key di https://platform.deepseek.com/api_keys');
        }

        return $key;
    }

    private function groqApiKey(): string
    {
        $key = config('services.groq.api_key');
        if (! $key) {
            throw new RuntimeException('GROQ_API_KEY belum di-set di file .env. Dipakai khusus untuk transkripsi suara. Ambil key gratis di https://console.groq.com/keys');
        }

        return $key;
    }

    private function extractJsonArray(string $text): string
    {
        $start = strpos($text, '[');
        $end   = strrpos($text, ']');

        if ($start === false || $end === false || $end <= $start) {
            return '[]';
        }

        return substr($text, $start, $end - $start + 1);
    }

    private function extractJsonObject(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('AI tidak mengembalikan JSON tiket.');
        }

        return substr($text, $start, $end - $start + 1);
    }

    /**
     * @param  array<int, array<string, string>>  $messages
     */
    private function chatCompletion(array $messages, ?string $model = null): string
    {
        $apiKey = $this->apiKey();
        $model  = $model ?: config('services.deepseek.model', 'deepseek-v4-flash');

        // DeepSeek v4 models are reasoning models: they spend output tokens on an
        // internal "reasoning_content" pass BEFORE the visible answer, and both
        // share the max_tokens budget. A budget that's too small gets fully
        // consumed by reasoning, leaving `content` empty (finish_reason=length).
        // Keep this generous so the answer always has room after reasoning.
        $maxTokens = (int) config('services.deepseek.max_tokens', 8000);

        $response = Http::timeout((int) config('services.deepseek.timeout', 120))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post(config('services.deepseek.base_url') . '/chat/completions', [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => 0.4,
                'max_tokens'  => $maxTokens,
            ]);

        if ($response->failed()) {
            $body   = $response->json() ?: ['raw' => $response->body()];
            $reason = data_get($body, 'error.message', 'DeepSeek request failed (HTTP ' . $response->status() . ').');
            Log::warning('DeepSeek API call failed', ['status' => $response->status(), 'body' => $body]);
            throw new RuntimeException($reason);
        }

        $json = $response->json();
        $text = data_get($json, 'choices.0.message.content', '');

        if ((string) $text === '') {
            // Almost always finish_reason=length with the whole budget eaten by
            // reasoning — surface a clear, actionable message instead of a blank.
            $finish = data_get($json, 'choices.0.finish_reason');
            Log::warning('DeepSeek returned empty content', [
                'model' => $model,
                'finish_reason' => $finish,
                'usage' => data_get($json, 'usage'),
            ]);

            if ($finish === 'length') {
                throw new RuntimeException('Teks terlalu panjang/kompleks untuk diproses AI saat ini. Coba persingkat teksnya lalu ulangi.');
            }

            throw new RuntimeException('DeepSeek tidak mengembalikan teks.');
        }

        return $text;
    }
}
