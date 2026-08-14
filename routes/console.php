<?php

use App\Console\Commands\SendDailyLogReminder;
use App\Console\Commands\SendMorningReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hari kerja Senin–Sabtu (tanpa Minggu). 0=Minggu … 6=Sabtu.
$hariKerja = [1, 2, 3, 4, 5, 6];

// Pengingat pagi: cek tiket aktif & isi catatan harian.
Schedule::command(SendMorningReminder::class)
    ->dailyAt('08:00')
    ->days($hariKerja)
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

// Pengingat sore: user yang belum isi catatan harian.
Schedule::command(SendDailyLogReminder::class)
    ->dailyAt('16:00')
    ->days($hariKerja)
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

// Backup database + lampiran ke Google Drive tiap tengah malam (jam 12 malam).
Schedule::command('backup:run')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

// Bersihkan backup lama sesuai retensi (3 hari) satu jam setelahnya.
Schedule::command('backup:clean')
    ->dailyAt('04:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Extra cleanup tiap 3 hari (di luar siklus harian) sebagai jaring pengaman
// retensi 3 hari agar backup lama di Google Drive tidak sempat menumpuk.
Schedule::command('backup:clean')
    ->cron('30 4 */3 * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Hapus dump manual lokal (storage/app/backups, manual-backups, backup-temp)
// yang lebih tua dari 3 hari. Terpisah dari backup:clean (yang membersihkan
// arsip di Google Drive) karena dump manual dibuat ad-hoc sebelum operasi
// berisiko dan tidak dikelola oleh spatie/laravel-backup.
Schedule::call(function () {
    $folders = [
        storage_path('app/backups'),
        storage_path('app/manual-backups'),
        storage_path('app/backup-temp'),
    ];

    foreach ($folders as $folder) {
        if (! File::isDirectory($folder)) {
            continue;
        }

        foreach (File::files($folder) as $file) {
            if (now()->diffInDays(now()->createFromTimestamp($file->getMTime())) > 3) {
                File::delete($file->getPathname());
            }
        }
    }
})
    ->name('cleanup-manual-backups')
    ->cron('0 5 */3 * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
