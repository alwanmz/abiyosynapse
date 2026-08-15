<?php

namespace App\Services;

use App\Mail\EmailOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailOtpService
{
    private const CODE_LENGTH = 6;
    private const EXPIRES_IN_MINUTES = 10;

    /**
     * Generate a fresh code, store its hash, and email it to the user.
     */
    public function sendCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_otp_code' => Hash::make($code),
            'email_otp_expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ])->save();

        Mail::to($user->email)->send(new EmailOtpMail($code, self::EXPIRES_IN_MINUTES));
    }

    /**
     * Verify a submitted code. On success, marks the email verified and
     * clears the stored code (one-time use, can't be replayed).
     */
    public function verify(User $user, string $submittedCode): bool
    {
        if (! $user->email_otp_code || ! $user->email_otp_expires_at) {
            return false;
        }

        if ($user->email_otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($submittedCode, $user->email_otp_code)) {
            return false;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
        ])->save();

        return true;
    }
}
