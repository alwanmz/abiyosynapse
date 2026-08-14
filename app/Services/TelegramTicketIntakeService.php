<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TelegramContact;
use App\Models\TelegramConversationState;
use App\Models\User;
use App\Support\TicketTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Drives the client-facing Telegram bot conversation (verification -> pick
 * category -> describe -> attach files -> submit) and, on submit, creates a
 * Ticket via the same numbering/status logic the web app uses.
 *
 * Runs synchronously inside the webhook request — no queue involved.
 */
class TelegramTicketIntakeService
{
    private const CATEGORY_LABELS = [
        'bug' => 'Bug',
        'feature' => 'Permintaan Baru',
        'task' => 'Lainnya',
    ];

    /** Phrases that signal "I want to report something", in natural Indonesian. */
    private const TICKET_INTENT_KEYWORDS = [
        'tiket', 'lapor', 'laporan', 'bug', 'error', 'eror', 'masalah', 'kendala',
        'komplain', 'komplen', 'keluhan', 'rusak', 'gagal', 'tidak bisa', 'ga bisa',
        'gabisa', 'nggak bisa', 'minta', 'request', 'butuh', 'perlu', 'tolong',
        'bantuan', 'bantu', 'issue', 'trouble',
    ];

    /** Keyword -> category, used to auto-pick a category from free text. */
    private const CATEGORY_KEYWORDS = [
        'bug' => ['bug', 'error', 'eror', 'rusak', 'gagal', 'tidak bisa', 'ga bisa', 'gabisa', 'nggak bisa', 'issue', 'trouble'],
        'feature' => ['minta', 'request', 'permintaan', 'usul', 'usulan', 'fitur baru', 'penambahan', 'tambahkan'],
    ];

    // Kept in sync with App\Observers\TicketObserver::STATUS_LABELS so the
    // labels a client sees here match what staff see in the app.
    private const STATUS_LABELS = [
        'todo' => 'To Do',
        'pending' => 'Menunggu Approval',
        'inprogress' => 'Sedang Dikerjakan',
        'qa-ready' => 'Siap QA',
        'qa-test' => 'QA Test',
        'review' => 'Review',
        'not-appropriate' => 'Belum Sesuai',
        'done' => 'Selesai',
    ];

    /** Failed verification attempts (wrong code, or contact-share mismatch) allowed before a cooldown. */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /** Cooldown window, in seconds, once MAX_VERIFY_ATTEMPTS is hit. */
    private const VERIFY_DECAY_SECONDS = 600;

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TicketNumberingService $ticketNumbering,
    ) {}

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    // ------------------------------------------------------------------
    // Routing
    // ------------------------------------------------------------------

    private function handleMessage(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        if (! $chatId) {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $state = TelegramConversationState::forChat($chatId);
        $contact = $this->activeContact($chatId);

        if (! $contact && isset($message['contact']) && $state->state === TelegramConversationState::AWAITING_PHONE) {
            $this->handlePhoneShare($chatId, $state, $message);

            return;
        }

        if (Str::startsWith($text, '/start') || Str::startsWith($text, '/help')) {
            $this->sendWelcome($chatId, $contact, $state);

            return;
        }

        if (Str::startsWith($text, '/cancel') || Str::startsWith($text, '/batal')) {
            $state->reset();
            $this->notify($chatId, 'Dibatalkan. Ketik /newticket kapan saja untuk mulai lagi.');

            return;
        }

        if (! $contact) {
            if ($state->state === TelegramConversationState::AWAITING_PHONE) {
                $this->notify($chatId, 'Ketuk tombol "📱 Bagikan Nomor HP" di bawah untuk menyelesaikan verifikasi.');

                return;
            }

            $this->handleVerificationAttempt($chatId, $text, $state, $message);

            return;
        }

        if (Str::startsWith($text, '/newticket')) {
            $this->beginTicketFlow($chatId, $state);

            return;
        }

        if (Str::startsWith($text, '/status')) {
            $this->showTicketStatus($chatId, $contact);

            return;
        }

        if (Str::startsWith($text, '/selesai')) {
            $this->finalizeTicket($chatId, $state, $contact);

            return;
        }

        match ($state->state) {
            TelegramConversationState::AWAITING_CATEGORY => $this->handleCategoryTextFallback($chatId, $state, $text),
            TelegramConversationState::AWAITING_DESCRIPTION => $this->handleDescription($chatId, $state, $text, $message),
            TelegramConversationState::AWAITING_ATTACHMENTS => $this->handleAttachmentMessage($chatId, $state, $message),
            default => $this->handleIdleMessage($chatId, $state, $text),
        };
    }

    /**
     * Idle (verified, no flow in progress): understand natural phrasing like
     * "mau buat tiket" / "ada bug nih" the same as /newticket, instead of
     * just telling the user to type a slash command.
     */
    private function handleIdleMessage(int $chatId, TelegramConversationState $state, string $text): void
    {
        if (! $this->looksLikeTicketIntent($text)) {
            $this->notify($chatId, 'Ketik /newticket untuk membuat tiket baru, atau /status untuk cek tiket Anda.');

            return;
        }

        $category = $this->guessCategoryFromText($text);

        if ($category) {
            $state->update([
                'state' => TelegramConversationState::AWAITING_DESCRIPTION,
                'payload' => ['category' => $category],
            ]);

            $label = self::CATEGORY_LABELS[$category];
            $this->notify($chatId, "Baik, saya catat sebagai <b>{$label}</b>. Ceritakan kendala Anda lebih detail:");

            return;
        }

        $this->beginTicketFlow($chatId, $state);
    }

    /**
     * User was shown category buttons but replied with free text instead of
     * tapping one — try to guess the category from their words rather than
     * just repeating "pilih salah satu tombol".
     */
    private function handleCategoryTextFallback(int $chatId, TelegramConversationState $state, string $text): void
    {
        $category = $this->guessCategoryFromText($text);

        if ($category) {
            $this->handleCategorySelection($chatId, $state, $category);

            return;
        }

        $this->notify($chatId, 'Silakan pilih salah satu tombol di atas (Bug / Permintaan Baru / Lainnya) ya.');
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');
        $callbackId = (string) ($callbackQuery['id'] ?? '');

        if (! $chatId || ! $data) {
            return;
        }

        // Acknowledge the tap last, best-effort — a failed/slow "ack" call
        // must never prevent the actual state transition below from running.
        $this->ack($callbackId);

        $contact = $this->activeContact($chatId);
        if (! $contact) {
            $this->notify($chatId, 'Sesi Anda belum terverifikasi. Ketik /start untuk mulai ulang.');

            return;
        }

        $state = TelegramConversationState::forChat($chatId);

        if (Str::startsWith($data, 'cat:') && $state->state === TelegramConversationState::AWAITING_CATEGORY) {
            $category = Str::after($data, 'cat:');
            $this->handleCategorySelection($chatId, $state, $category);
        }

        if (Str::startsWith($data, 'copilot:') && $state->state === TelegramConversationState::AWAITING_COPILOT_CONFIRMATION) {
            $action = Str::after($data, 'copilot:');
            if ($action === 'resolved') {
                $state->reset();
                $this->notify($chatId, "Terima kasih! Senang kendala Anda sudah teratasi tanpa perlu membuat tiket baru. 🎉\n\nKetik /newticket kapan saja jika ada kendala lainnya.");
            } elseif ($action === 'continue') {
                $payload = $state->payload ?? [];
                $state->update([
                    'state' => TelegramConversationState::AWAITING_ATTACHMENTS,
                    'payload' => $payload,
                ]);
                $this->notify($chatId, "Baik, laporan Anda akan diteruskan ke tim dev. Kirim foto/dokumen pendukung jika ada, atau ketik /selesai untuk membuat tiket sekarang.");
            }
        }
    }

    // ------------------------------------------------------------------
    // Verification
    // ------------------------------------------------------------------

    private function sendWelcome(int $chatId, ?TelegramContact $contact, TelegramConversationState $state): void
    {
        if ($contact) {
            $this->notify(
                $chatId,
                "Halo! Anda terverifikasi sebagai <b>{$contact->client->nama}</b>.\n\n"
                    . "Ketik /newticket untuk melaporkan kendala baru.\n"
                    . "Ketik /status untuk melihat status tiket Anda."
            );

            return;
        }

        if ($state->state === TelegramConversationState::AWAITING_PHONE) {
            $this->notifyContactRequest(
                $chatId,
                'Satu langkah lagi: bagikan nomor HP Anda untuk menyelesaikan verifikasi.'
            );

            return;
        }

        $this->notify(
            $chatId,
            "Selamat datang di bot tiket sistemkesehatan.id.\n\nUntuk mulai, masukkan <b>kode verifikasi</b> yang diberikan tim kami kepada perusahaan Anda."
        );
    }

    /**
     * Reply with the client's most recent tickets and their current status —
     * read-only, no way to change anything from the bot (all triage still
     * happens in the web app).
     */
    private function showTicketStatus(int $chatId, ?TelegramContact $contact): void
    {
        if (! $contact) {
            $this->notify($chatId, 'Ketik /start untuk verifikasi dulu sebelum cek status tiket.');

            return;
        }

        $tickets = Ticket::where('client_id', $contact->client_id)
            // Arsip = sudah "ditutup" dari sisi staf, jangan tampil lagi di bot.
            ->whereNull('archived_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['ticket_number', 'title', 'description', 'status', 'updated_at']);

        if ($tickets->isEmpty()) {
            $this->notify($chatId, 'Belum ada tiket yang tercatat untuk perusahaan Anda.');

            return;
        }

        $lines = $tickets->map(function (Ticket $ticket) {
            $label = self::STATUS_LABELS[$ticket->status] ?? $ticket->status;
            // Titles/descriptions are free-text from users — escape before
            // wrapping in HTML tags, otherwise a stray "<" or "&" breaks
            // Telegram's HTML parsing and the whole message fails to send.
            $title = e(Str::limit($ticket->title, 60));
            $description = trim((string) $ticket->description);
            $descriptionLine = $description !== '' ? e(Str::limit($description, 120)) : '-';
            $updatedDate = $ticket->updated_at?->format('d M Y');
            $updatedRelative = $ticket->updated_at?->diffForHumans();

            return "• <b>{$ticket->ticket_number}</b> — <i>{$title}</i>\n   {$descriptionLine}\n   Status: <b>{$label}</b> ({$updatedDate}, {$updatedRelative})";
        })->implode("\n\n");

        $this->notify(
            $chatId,
            "10 tiket terbaru untuk <b>{$contact->client->nama}</b>:\n\n{$lines}"
        );
    }

    private function handleVerificationAttempt(int $chatId, string $text, TelegramConversationState $state, array $message): void
    {
        $code = trim($text);

        if ($code === '') {
            $this->notify($chatId, 'Masukkan kode verifikasi yang diberikan tim kami.');

            return;
        }

        if ($this->tooManyVerifyAttempts($chatId)) {
            $this->notify($chatId, $this->verifyLockedOutMessage($chatId));

            return;
        }

        $client = Client::where('telegram_verification_code', $code)->first();

        if (! $client) {
            // A message like "mau buat tiket" isn't a wrong code — it's clear
            // intent from someone who hasn't verified yet. Guide them instead
            // of the confusing "kode tidak ditemukan" for something that was
            // never meant to be a code. Doesn't count against the rate limit.
            if ($this->looksLikeTicketIntent($code)) {
                $this->notify(
                    $chatId,
                    "Untuk membuat tiket, perusahaan Anda perlu diverifikasi dulu.\n\n"
                        . "Masukkan <b>kode verifikasi</b> yang diberikan tim kami. Belum punya kode? Hubungi tim kami dulu ya."
                );

                return;
            }

            $this->registerFailedVerifyAttempt($chatId);
            $this->notify($chatId, 'Kode tidak ditemukan. Periksa kembali atau hubungi tim kami.');

            return;
        }

        $this->clearVerifyAttempts($chatId);

        $from = $message['from'] ?? [];

        // Codes are single-use: rotate it the moment it's successfully used,
        // right here, so a leaked/forwarded copy of this exact code can
        // never be reused by anyone else — legitimate additional staff at
        // the same client get a fresh code generated for them individually.
        $client->regenerateTelegramVerificationCode();

        // Don't mark verified yet — require the user to confirm their own
        // phone number via Telegram's share-contact button as a second
        // factor first (a shared text code alone can be forwarded to
        // anyone; a self-shared contact card can't).
        $state->update([
            'state' => TelegramConversationState::AWAITING_PHONE,
            'payload' => [
                'client_id' => $client->id,
                'telegram_user_id' => $from['id'] ?? null,
                'telegram_username' => $from['username'] ?? null,
                'telegram_first_name' => $from['first_name'] ?? null,
                'telegram_last_name' => $from['last_name'] ?? null,
            ],
        ]);

        $this->notifyContactRequest(
            $chatId,
            "Kode benar ✅ Satu langkah lagi: bagikan nomor HP Anda untuk menyelesaikan verifikasi sebagai <b>{$client->nama}</b>."
        );
    }

    /**
     * Second verification factor: the user tapped "share contact" and
     * Telegram sent their own phone number (guaranteed to be their own
     * account's number — Telegram doesn't let this button send anyone
     * else's contact). Finalizes the TelegramContact using the client_id +
     * profile info stashed in the conversation payload during code check.
     */
    private function handlePhoneShare(int $chatId, TelegramConversationState $state, array $message): void
    {
        if ($this->tooManyVerifyAttempts($chatId)) {
            $this->notify($chatId, $this->verifyLockedOutMessage($chatId));

            return;
        }

        $contact = $message['contact'] ?? null;
        $from = $message['from'] ?? [];

        if (! $contact || ! isset($contact['phone_number'])) {
            $this->notify($chatId, 'Mohon gunakan tombol "Bagikan Nomor HP" di bawah.');

            return;
        }

        // Guard against someone forwarding a different person's contact
        // card manually instead of tapping the button themselves.
        if (($contact['user_id'] ?? null) !== ($from['id'] ?? null)) {
            $this->registerFailedVerifyAttempt($chatId);
            $this->notify($chatId, 'Mohon bagikan nomor HP Anda sendiri (gunakan tombolnya, jangan kirim kontak lain).');

            return;
        }

        $payload = $state->payload ?? [];
        $clientId = $payload['client_id'] ?? null;

        if (! $clientId) {
            // Payload got lost somehow (e.g. state reset mid-flow) — restart cleanly.
            $state->update(['state' => TelegramConversationState::AWAITING_CODE, 'payload' => []]);
            $this->notify($chatId, 'Sesi verifikasi kedaluwarsa. Masukkan kode verifikasi lagi ya.');

            return;
        }

        $client = Client::find($clientId);
        if (! $client) {
            $state->update(['state' => TelegramConversationState::AWAITING_CODE, 'payload' => []]);
            $this->notify($chatId, 'Terjadi kesalahan, coba verifikasi ulang dari awal.');

            return;
        }

        TelegramContact::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'client_id' => $client->id,
                'telegram_user_id' => $payload['telegram_user_id'] ?? ($from['id'] ?? null),
                'telegram_username' => $payload['telegram_username'] ?? ($from['username'] ?? null),
                'telegram_first_name' => $payload['telegram_first_name'] ?? ($from['first_name'] ?? null),
                'telegram_last_name' => $payload['telegram_last_name'] ?? ($from['last_name'] ?? null),
                'phone_number' => $contact['phone_number'],
                'verified_at' => now(),
                'revoked_at' => null,
            ]
        );

        $this->clearVerifyAttempts($chatId);
        $state->reset();

        $this->notifyRemoveKeyboard(
            $chatId,
            "Terverifikasi sebagai <b>{$client->nama}</b> ✅\n\nKetik /newticket untuk melaporkan kendala."
        );
    }

    // ------------------------------------------------------------------
    // Ticket intake flow
    // ------------------------------------------------------------------

    private function beginTicketFlow(int $chatId, TelegramConversationState $state): void
    {
        $state->update(['state' => TelegramConversationState::AWAITING_CATEGORY, 'payload' => []]);

        $this->notifyKeyboard($chatId, 'Jenis laporan?', [
            ['text' => 'Bug', 'callback_data' => 'cat:bug'],
            ['text' => 'Permintaan Baru', 'callback_data' => 'cat:feature'],
            ['text' => 'Lainnya', 'callback_data' => 'cat:task'],
        ]);
    }

    private function handleCategorySelection(int $chatId, TelegramConversationState $state, string $category): void
    {
        if (! array_key_exists($category, self::CATEGORY_LABELS)) {
            return;
        }

        $state->update([
            'state' => TelegramConversationState::AWAITING_DESCRIPTION,
            'payload' => ['category' => $category],
        ]);

        $this->notify($chatId, 'Ceritakan kendala Anda (boleh cukup 1-2 kalimat):');
    }

    private function handleDescription(int $chatId, TelegramConversationState $state, string $text, array $message): void
    {
        $payload = $state->payload ?? [];
        $payload['attachments'] = $payload['attachments'] ?? [];

        $hasAttachment = $this->processAttachment($chatId, $message, $payload);

        if ($text !== '') {
            $currentTitle = trim((string) ($payload['title_input'] ?? ''));
            if ($currentTitle === '') {
                $payload['title_input'] = $text;
            } elseif (! str_contains($currentTitle, $text)) {
                $payload['title_input'] = $currentTitle . "\n\n" . $text;
            }

            $currentDesc = trim((string) ($payload['description'] ?? ''));
            if ($currentDesc === '') {
                $payload['description'] = $text;
            } elseif (! str_contains($currentDesc, $text)) {
                $payload['description'] = $currentDesc . "\n\n" . $text;
            }
        }

        $effectiveDesc = trim((string) ($payload['title_input'] ?? $payload['description'] ?? ''));

        if ($effectiveDesc === '' && ! $hasAttachment) {
            $this->notify($chatId, 'Deskripsi tidak boleh kosong. Ceritakan kendala Anda (atau kirim foto dengan keterangan):');

            return;
        }

        // Check if AI Copilot finds a matching solution from previous resolved tickets (only if description text exists)
        if ($effectiveDesc !== '') {
            $contact = $this->activeContact($chatId);
            $copilotService = app(\App\Services\AiTicketCopilotService::class);
            $similar = $copilotService->findSimilarSolution($effectiveDesc, $contact?->client_id);

            if ($similar['found'] && !empty($similar['suggestion'])) {
                $payload['copilot'] = $similar;
                $state->update([
                    'state' => TelegramConversationState::AWAITING_COPILOT_CONFIRMATION,
                    'payload' => $payload,
                ]);

                $suggestionEscaped = e($similar['suggestion']);
                $this->notifyKeyboard(
                    $chatId,
                    "🤖 <b>AI Support Copilot</b>:\n\nBerdasarkan riwayat penanganan terdahulu, kendala serupa pernah diselesaikan dengan saran berikut:\n\n💡 <b>Saran Perbaikan</b>:\n{$suggestionEscaped}\n\nApakah saran ini dapat membantu menyelesaikan kendala Anda?",
                    [
                        ['text' => '✅ Ya, Solusi Membantu', 'callback_data' => 'copilot:resolved'],
                        ['text' => '🎫 Tetap Buat Tiket', 'callback_data' => 'copilot:continue'],
                    ]
                );

                return;
            }
        }

        $state->update([
            'state' => TelegramConversationState::AWAITING_ATTACHMENTS,
            'payload' => $payload,
        ]);

        if ($hasAttachment && $effectiveDesc === '') {
            $this->notify(
                $chatId,
                "Foto/lampiran diterima ✅. Silakan ketik deskripsi kendala Anda (atau kirim keterangan):"
            );
        } elseif ($hasAttachment) {
            $this->notify(
                $chatId,
                "Deskripsi dan lampiran diterima ✅. Kirim foto/dokumen pendukung lain jika ada, atau ketik /selesai untuk mengirim tiket sekarang."
            );
        } else {
            $this->notify(
                $chatId,
                "Terima kasih. Kirim foto/dokumen pendukung jika ada, atau ketik /selesai untuk mengirim tiket sekarang."
            );
        }
    }

    private function handleAttachmentMessage(int $chatId, TelegramConversationState $state, array $message): void
    {
        $payload = $state->payload ?? [];

        $success = $this->processAttachment($chatId, $message, $payload);

        $caption = trim((string) ($message['caption'] ?? ''));
        if ($caption !== '') {
            $currentDetails = trim((string) ($payload['details'] ?? ''));
            if ($currentDetails === '') {
                $payload['details'] = $caption;
            } elseif (! str_contains($currentDetails, $caption)) {
                $payload['details'] = $currentDetails . "\n\n" . $caption;
            }

            $currentDesc = trim((string) ($payload['description'] ?? ''));
            if ($currentDesc === '') {
                $payload['description'] = $caption;
            } elseif (! str_contains($currentDesc, $caption)) {
                $payload['description'] = $currentDesc . "\n\n" . $caption;
            }
        }

        if (! $success) {
            if (! $this->extractFileReference($message)) {
                // If user sent text without attachment while in AWAITING_ATTACHMENTS
                $textMsg = trim((string) ($message['text'] ?? ''));
                if ($textMsg !== '') {
                    $currentDetails = trim((string) ($payload['details'] ?? ''));
                    if ($currentDetails === '') {
                        $payload['details'] = $textMsg;
                    } elseif (! str_contains($currentDetails, $textMsg)) {
                        $payload['details'] = $currentDetails . "\n\n" . $textMsg;
                    }

                    $currentDesc = trim((string) ($payload['description'] ?? ''));
                    if ($currentDesc === '') {
                        $payload['description'] = $textMsg;
                    } elseif (! str_contains($currentDesc, $textMsg)) {
                        $payload['description'] = $currentDesc . "\n\n" . $textMsg;
                    }

                    $state->update(['payload' => $payload]);
                    $this->notify($chatId, "Catatan tambahan diterima. Kirim foto/dokumen lagi, atau ketik /selesai untuk mengirim tiket.");

                    return;
                }

                $this->notify(
                    $chatId,
                    'Kirim foto/dokumen, atau ketik /selesai untuk mengirim tiket tanpa lampiran.'
                );
            }

            return;
        }

        $state->update(['payload' => $payload]);

        $count = count($payload['attachments'] ?? []);
        $this->notify($chatId, "Lampiran diterima ({$count}). Kirim lagi atau ketik /selesai.");
    }

    private function processAttachment(int $chatId, array $message, array &$payload): bool
    {
        $fileRef = $this->extractFileReference($message);

        if (! $fileRef) {
            return false;
        }

        [$fileId, $originalName, $mimeType, $size] = $fileRef;

        $allowedMimes = config('tickets.attachments.allowed_mimes', ['jpg', 'jpeg', 'png', 'pdf']);
        $maxBytes = (int) config('tickets.attachments.max_kb', 30 * 1024) * 1024;
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension && ! in_array($extension, $allowedMimes, true)) {
            $this->notify($chatId, "Format .{$extension} tidak didukung. Format yang didukung: " . implode(', ', $allowedMimes));

            return false;
        }

        if ($size && $size > $maxBytes) {
            $this->notify($chatId, 'File terlalu besar (maks ' . round($maxBytes / 1024 / 1024, 1) . ' MB).');

            return false;
        }

        try {
            $downloaded = $this->telegram->downloadFile($fileId);
        } catch (\Throwable $e) {
            Log::warning('Telegram downloadFile failed', ['chat_id' => $chatId, 'msg' => $e->getMessage()]);
            $downloaded = null;
        }

        if (! $downloaded) {
            $this->notify($chatId, 'Gagal mengunduh file itu, coba kirim ulang.');

            return false;
        }

        $path = 'ticket-attachments/telegram-pending/' . $chatId . '/' . Str::random(8) . '-' . $originalName;
        Storage::disk('public')->put($path, $downloaded['contents']);

        $payload['attachments'] = $payload['attachments'] ?? [];
        $payload['attachments'][] = [
            'name' => $originalName,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => strlen($downloaded['contents']),
        ];

        return true;
    }

    /**
     * @return array{0:string,1:string,2:?string,3:?int}|null [file_id, name, mime_type, size]
     */
    private function extractFileReference(array $message): ?array
    {
        if (isset($message['document'])) {
            $doc = $message['document'];

            return [$doc['file_id'], $doc['file_name'] ?? 'document', $doc['mime_type'] ?? null, $doc['file_size'] ?? null];
        }

        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            // Telegram sends multiple sizes; take the largest (last).
            $largest = end($message['photo']);

            return [$largest['file_id'], 'photo.jpg', 'image/jpeg', $largest['file_size'] ?? null];
        }

        return null;
    }

    private function finalizeTicket(int $chatId, TelegramConversationState $state, TelegramContact $contact): void
    {
        $payload = $state->payload ?? [];
        $titleInput = trim((string) ($payload['title_input'] ?? ''));
        $details = trim((string) ($payload['details'] ?? ''));
        $rawDesc = trim((string) ($payload['description'] ?? ''));
        $category = $payload['category'] ?? 'task';

        if ($titleInput === '' && $details === '' && $rawDesc === '') {
            $this->notify($chatId, 'Belum ada laporan yang sedang dibuat. Ketik /newticket untuk mulai.');

            return;
        }

        if ($titleInput !== '' && $details !== '') {
            $combinedForTitle = (mb_strlen($titleInput) < 15 && count(explode("\n", $titleInput)) === 1)
                ? $titleInput . "\n" . $details
                : $titleInput;

            $title = TicketTitle::fromDescription($combinedForTitle, 'Tiket Laporan Telegram');

            if (count(explode("\n", $titleInput)) > 1 || mb_strlen($titleInput) >= 60 || mb_strlen($titleInput) < 15) {
                $description = $titleInput . "\n\n" . $details;
            } else {
                $description = $details;
            }
        } elseif ($titleInput !== '') {
            $title = TicketTitle::fromDescription($titleInput, 'Tiket Laporan Telegram');
            $description = $titleInput;
        } else {
            $combined = $details !== '' ? $details : $rawDesc;
            $title = TicketTitle::fromDescription($combined, 'Tiket Laporan Telegram');
            $description = $combined;
        }

        $reporter = User::where('username', 'telegram-bot')->where('is_system', true)->first();
        if (! $reporter) {
            Log::warning('Telegram bot system user not found — cannot create ticket.');
            $this->notify($chatId, 'Terjadi kesalahan sistem. Coba lagi nanti atau hubungi tim kami.');

            return;
        }

        $client = $contact->client;
        $reporterName = trim("{$contact->telegram_first_name} {$contact->telegram_last_name}") ?: ($contact->telegram_username ?? "Chat #{$chatId}");

        $ticket = DB::transaction(function () use ($client, $reporter, $title, $description, $category, $contact, $chatId, $reporterName) {
            $lockedClient = Client::lockForUpdate()->findOrFail($client->id);
            $ticketNumber = $this->ticketNumbering->nextTicketNumber($lockedClient, null);

            $ticket = Ticket::create([
                'client_id' => $lockedClient->id,
                'reporter_id' => $reporter->id,
                'title' => $title,
                'description' => $description,
                'ticket_number' => $ticketNumber,
                'source' => 'telegram',
                'external_reporter_name' => $reporterName,
                'external_reporter_contact' => $contact->telegram_username,
                'telegram_chat_id' => $chatId,
                'type' => $category,
                'priority' => 'medium',
                'status' => 'todo',
            ]);

            $ticket->logStatusChange('todo', null, $reporter->id);

            return $ticket;
        });

        $this->attachStoredFiles($ticket, $payload['attachments'] ?? []);

        $state->reset();

        $this->notify(
            $chatId,
            "Tiket <b>{$ticket->ticket_number}</b> berhasil dibuat ✅\nTim kami akan segera menindaklanjuti.\n\nKetik /newticket untuk melapor lagi."
        );

        app(ExternalTicketNotifier::class)->notifyStaff($ticket, $client->nama, $reporterName);
    }

    /**
     * Move files downloaded to a temp "pending" path into the ticket's real
     * attachment folder, matching TicketController::storeTicketAttachments()'s
     * output shape.
     */
    private function attachStoredFiles(Ticket $ticket, array $pendingAttachments): void
    {
        if ($pendingAttachments === []) {
            return;
        }

        $attachments = [];
        $disk = Storage::disk('public');

        foreach ($pendingAttachments as $file) {
            $newPath = 'ticket-attachments/' . $ticket->id . '/' . basename($file['path']);

            try {
                $disk->move($file['path'], $newPath);
            } catch (\Throwable $e) {
                Log::warning('Failed to move Telegram attachment', ['path' => $file['path'], 'msg' => $e->getMessage()]);

                continue;
            }

            $attachments[] = [
                'name' => $file['name'],
                'path' => $newPath,
                'disk' => 'public',
                'url' => $disk->url($newPath),
                'size' => $file['size'],
                'mime_type' => $file['mime_type'],
            ];
        }

        if ($attachments !== []) {
            $ticket->update(['attachments' => $attachments]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function activeContact(int $chatId): ?TelegramContact
    {
        return TelegramContact::with('client')
            ->where('chat_id', $chatId)
            ->whereNotNull('verified_at')
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Throttles the two ways an unverified chat can be probed: guessing the
     * verification code, and sharing a mismatched contact card. Keyed per
     * chat_id, cleared on a successful verification.
     */
    private function verifyRateLimitKey(int $chatId): string
    {
        return "telegram-verify:{$chatId}";
    }

    private function tooManyVerifyAttempts(int $chatId): bool
    {
        return RateLimiter::tooManyAttempts($this->verifyRateLimitKey($chatId), self::MAX_VERIFY_ATTEMPTS);
    }

    private function registerFailedVerifyAttempt(int $chatId): void
    {
        RateLimiter::hit($this->verifyRateLimitKey($chatId), self::VERIFY_DECAY_SECONDS);
    }

    private function clearVerifyAttempts(int $chatId): void
    {
        RateLimiter::clear($this->verifyRateLimitKey($chatId));
    }

    private function verifyLockedOutMessage(int $chatId): string
    {
        $seconds = RateLimiter::availableIn($this->verifyRateLimitKey($chatId));
        $wait = $seconds >= 60 ? ceil($seconds / 60) . ' menit' : "{$seconds} detik";

        return "Terlalu banyak percobaan yang gagal. Coba lagi dalam {$wait}, atau hubungi tim kami.";
    }

    /**
     * Loose keyword match for "I want to report/create a ticket" phrased in
     * natural Indonesian (e.g. "mau buat tiket", "ada bug nih", "mau lapor
     * error") so free text is understood the same way as /newticket,
     * instead of just falling through to a generic hint or — worse, before
     * verification — being misread as a verification-code attempt.
     */
    private function looksLikeTicketIntent(string $text): bool
    {
        $normalized = strtolower($text);

        foreach (self::TICKET_INTENT_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Best-effort guess at a ticket category from free text, so a user who
     * types "ada bug di menu resep" instead of tapping a button still lands
     * in the right bucket. Returns null when nothing obvious matches —
     * callers fall back to showing the category buttons.
     */
    private function guessCategoryFromText(string $text): ?string
    {
        $normalized = strtolower($text);

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Best-effort message send — a Telegram API hiccup (or missing bot
     * token) must never abort conversation-state changes or ticket
     * creation that already happened earlier in the same request.
     */
    private function notify(int $chatId, string $text): void
    {
        try {
            $this->telegram->sendMessage($chatId, $text);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed', ['chat_id' => $chatId, 'msg' => $e->getMessage()]);
        }
    }

    private function notifyKeyboard(int $chatId, string $text, array $buttons): void
    {
        try {
            $this->telegram->sendInlineKeyboard($chatId, $text, $buttons);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendInlineKeyboard failed', ['chat_id' => $chatId, 'msg' => $e->getMessage()]);
        }
    }

    private function ack(string $callbackId): void
    {
        try {
            $this->telegram->answerCallbackQuery($callbackId);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery failed', ['callback_id' => $callbackId, 'msg' => $e->getMessage()]);
        }
    }

    private function notifyContactRequest(int $chatId, string $text): void
    {
        try {
            $this->telegram->sendContactRequest($chatId, $text);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendContactRequest failed', ['chat_id' => $chatId, 'msg' => $e->getMessage()]);
        }
    }

    private function notifyRemoveKeyboard(int $chatId, string $text): void
    {
        try {
            $this->telegram->removeKeyboard($chatId, $text);
        } catch (\Throwable $e) {
            Log::warning('Telegram removeKeyboard failed', ['chat_id' => $chatId, 'msg' => $e->getMessage()]);
        }
    }
}
