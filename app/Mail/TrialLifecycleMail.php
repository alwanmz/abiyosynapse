<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Trial reminders ("reminder": trial ends soon) and the expiry notice
 * ("expired": pay before the purge date or the data is deleted).
 */
class TrialLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $kind,
        public readonly string $recipientName,
        public readonly string $companyName,
        public readonly CarbonInterface $trialEndsAt,
        public readonly CarbonInterface $purgeAt,
    ) {}

    public function build(): self
    {
        $subject = $this->kind === 'expired'
            ? "Trial {$this->companyName} telah berakhir"
            : "Trial {$this->companyName} berakhir " . $this->trialEndsAt->locale('id')->diffForHumans();

        return $this->subject($subject)
            ->view('emails.trial-lifecycle', ['billingUrl' => route('billing.index')]);
    }
}
