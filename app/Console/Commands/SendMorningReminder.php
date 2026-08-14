<?php

namespace App\Console\Commands;

use App\Mail\MorningReminderMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class SendMorningReminder extends Command
{
    protected $signature = 'reminder:send-morning';

    protected $description = 'Kirim email pengingat pagi ke tim: cek tiket aktif & isi catatan harian';

    public function handle(): int
    {
        // Target audiens sama seperti reminder sore: semua anggota tim kecuali admin.
        $users = User::query()
            ->whereHas('role', fn (Builder $q) => $q->whereNotIn('name', ['super_admin', 'admin']))
            ->whereNotNull('email')
            ->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada user yang perlu dikirimi reminder pagi.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($users as $user) {
            $priorityRank = ['highest' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'lowest' => 4];

            $openTickets = Ticket::query()
                ->where(function (Builder $q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('assignees', fn (Builder $a) => $a->where('users.id', $user->id));
                })
                ->whereNotIn('status', ['done'])
                ->whereNull('archived_at')
                ->orderByDesc('created_at')
                ->get(['id', 'ticket_number', 'title', 'status', 'priority'])
                // Sort by priority in PHP so it stays portable across DB drivers.
                ->sortBy(fn (Ticket $t) => $priorityRank[$t->priority] ?? 99)
                ->values();

            Mail::to($user->email)->send(new MorningReminderMail($user, $openTickets));
            $sent++;
        }

        $this->info("Reminder pagi terkirim ke {$sent} user.");

        return self::SUCCESS;
    }
}
