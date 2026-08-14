<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--delete : Remove the webhook instead of setting it}';

    protected $description = 'Daftarkan (atau hapus) webhook bot Telegram untuk intake tiket';

    public function handle(TelegramService $telegram): int
    {
        if (! config('services.telegram.bot_token')) {
            $this->error('TELEGRAM_BOT_TOKEN belum di-set di .env.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $result = $telegram->deleteWebhook();
            $this->info($result['ok'] ?? false ? 'Webhook dihapus.' : 'Gagal menghapus webhook: ' . json_encode($result));

            return self::SUCCESS;
        }

        $secret = config('services.telegram.webhook_secret');
        if (! $secret) {
            $this->error('TELEGRAM_WEBHOOK_SECRET belum di-set di .env.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');
        $result = $telegram->setWebhook($url, $secret);

        if (! ($result['ok'] ?? false)) {
            $this->error('Gagal set webhook: ' . json_encode($result));

            return self::FAILURE;
        }

        $this->info("Webhook terdaftar: {$url}");

        $info = $telegram->getWebhookInfo();
        $this->line(json_encode($info['result'] ?? $info, JSON_PRETTY_PRINT));

        $commandsResult = $telegram->setMyCommands([
            ['command' => 'start', 'description' => 'Mulai / verifikasi akun'],
            ['command' => 'newticket', 'description' => 'Buat tiket baru'],
            ['command' => 'status', 'description' => 'Cek status tiket Anda'],
            ['command' => 'cancel', 'description' => 'Batalkan proses yang sedang berjalan'],
            ['command' => 'help', 'description' => 'Bantuan'],
        ]);

        $this->info(($commandsResult['ok'] ?? false) ? 'Menu perintah (/) terdaftar.' : 'Gagal set menu perintah: ' . json_encode($commandsResult));

        return self::SUCCESS;
    }
}
