<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MorningReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $openTickets
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Selamat Pagi! Cek Tiket & Catatan Harian Hari Ini',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.morning-reminder',
        );
    }
}
