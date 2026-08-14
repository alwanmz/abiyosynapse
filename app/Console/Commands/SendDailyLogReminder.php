<?php

namespace App\Console\Commands;

use App\Mail\DailyLogReminderMail;
use App\Models\DailyLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyLogReminder extends Command
{
    protected $signature = 'daily-log:send-reminder';

    protected $description = 'Kirim email reminder ke user yang belum mengisi catatan harian hari ini';

    public function handle(): int
    {
        $today = today();

        // Ambil semua user kecuali super_admin/admin, yang belum isi log hari ini
        $users = User::query()
            ->whereHas('role', fn ($q) => $q->whereNotIn('name', ['super_admin', 'admin']))
            ->whereNotIn('id', function ($sub) use ($today) {
                $sub->select('user_id')
                    ->from((new DailyLog)->getTable())
                    ->whereDate('log_date', $today);
            })
            ->get();

        if ($users->isEmpty()) {
            $this->info('Semua user sudah mengisi catatan harian. Tidak ada email yang dikirim.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            Mail::to($user->email)->send(new DailyLogReminderMail($user));
        }

        $this->info("Reminder terkirim ke {$users->count()} user.");

        return self::SUCCESS;
    }
}
