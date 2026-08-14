<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Console\Command;

class GoogleDriveToken extends Command
{
    protected $signature = 'google:drive-token';

    protected $description = 'Ambil refresh token Google Drive (OAuth) untuk destinasi backup';

    public function handle(): int
    {
        $clientId = config('filesystems.disks.google.clientId') ?: $this->ask('Google OAuth Client ID');
        $clientSecret = config('filesystems.disks.google.clientSecret') ?: $this->secret('Google OAuth Client Secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('Client ID / Secret kosong. Isi GOOGLE_DRIVE_CLIENT_ID & GOOGLE_DRIVE_CLIENT_SECRET di .env dulu, atau masukkan manual.');

            return self::FAILURE;
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri('http://localhost');
        $client->setScopes([GoogleServiceDrive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $this->newLine();
        $this->info('1) Buka URL ini di browser & login pakai akun Google tujuan backup:');
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->info('2) Setelah klik "Allow", browser diarahkan ke http://localhost/?code=...');
        $this->comment('   (halaman gagal dibuka itu WAJAR). Salin nilai parameter "code" dari address bar,');
        $this->comment('   atau tempel seluruh URL redirect-nya di bawah ini.');
        $this->newLine();

        $input = trim((string) $this->ask('Tempel kode (atau URL redirect penuh)'));

        if (str_contains($input, 'code=')) {
            parse_str((string) parse_url($input, PHP_URL_QUERY), $query);
            $input = $query['code'] ?? $input;
        }

        $token = $client->fetchAccessTokenWithAuthCode($input);

        if (isset($token['error'])) {
            $this->error('Gagal menukar kode: ' . ($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        $refreshToken = $token['refresh_token'] ?? null;

        if (! $refreshToken) {
            $this->error('Respons tidak berisi refresh_token.');
            $this->comment('Cabut akses lama di https://myaccount.google.com/permissions lalu ulangi (agar consent "offline" keluar lagi).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('BERHASIL. Salin baris ini ke .env:');
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN=' . $refreshToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
