<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around the Telegram Bot API. Used exclusively for the
 * client ticket-intake bot (see TelegramWebhookController).
 */
class TelegramService
{
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->call('sendMessage', $payload);
    }

    /**
     * @param  array<int, array{text: string, callback_data: string}>  $buttons  one per row
     */
    public function sendInlineKeyboard(int|string $chatId, string $text, array $buttons): void
    {
        $this->sendMessage($chatId, $text, [
            'inline_keyboard' => array_map(fn ($button) => [$button], $buttons),
        ]);
    }

    /**
     * Ask the user to share their own phone number via Telegram's native
     * "share contact" button. Telegram only lets this button send the
     * account owner's own contact card, which is what makes it a real
     * second verification factor (unlike a shared text code, it can't be
     * forwarded to someone else).
     */
    public function sendContactRequest(int|string $chatId, string $text): void
    {
        $this->sendMessage($chatId, $text, [
            'keyboard' => [[['text' => '📱 Bagikan Nomor HP', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    /**
     * Dismiss a previously shown custom (non-inline) keyboard, e.g. after
     * the contact-share step completes.
     */
    public function removeKeyboard(int|string $chatId, string $text): void
    {
        $this->sendMessage($chatId, $text, ['remove_keyboard' => true]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $payload['text'] = $text;
        }

        $this->call('answerCallbackQuery', $payload);
    }

    /**
     * Resolve a Telegram file_id to its download path via getFile, then
     * download the raw bytes. Returns null if the file can't be fetched.
     *
     * @return array{contents: string, file_name: string}|null
     */
    public function downloadFile(string $fileId): ?array
    {
        $token = $this->token();

        $fileInfo = Http::timeout($this->timeout())
            ->get($this->apiUrl() . "/bot{$token}/getFile", ['file_id' => $fileId]);

        if ($fileInfo->failed() || ! $fileInfo->json('ok')) {
            Log::warning('Telegram getFile failed', ['file_id' => $fileId, 'body' => $fileInfo->json()]);

            return null;
        }

        $filePath = $fileInfo->json('result.file_path');
        if (! $filePath) {
            return null;
        }

        $download = Http::timeout($this->timeout())
            ->get($this->apiUrl() . "/file/bot{$token}/{$filePath}");

        if ($download->failed()) {
            Log::warning('Telegram file download failed', ['file_path' => $filePath]);

            return null;
        }

        return [
            'contents' => $download->body(),
            'file_name' => basename($filePath),
        ];
    }

    /**
     * Register the "/" command menu shown in the Telegram client next to
     * the message box.
     *
     * @param  array<int, array{command: string, description: string}>  $commands
     */
    public function setMyCommands(array $commands): array
    {
        return $this->call('setMyCommands', ['commands' => $commands]);
    }

    /**
     * Register our webhook URL with Telegram (called once via
     * `php artisan telegram:set-webhook`).
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $response = $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        return $response;
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', []);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', [], 'get');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function token(): string
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum di-set di file .env. Buat bot lewat @BotFather di Telegram.');
        }

        return $token;
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('services.telegram.api_base_url', 'https://api.telegram.org'), '/');
    }

    private function timeout(): int
    {
        return (int) config('services.telegram.timeout', 15);
    }

    private function call(string $method, array $payload, string $verb = 'post'): array
    {
        $token = $this->token();
        $url = $this->apiUrl() . "/bot{$token}/{$method}";

        $response = $verb === 'get'
            ? Http::timeout($this->timeout())->get($url, $payload)
            : Http::timeout($this->timeout())->asJson()->post($url, $payload);

        $body = $response->json() ?: [];

        if ($response->failed() || ! ($body['ok'] ?? false)) {
            Log::warning('Telegram API call failed', ['method' => $method, 'body' => $body]);
        }

        return $body;
    }
}
