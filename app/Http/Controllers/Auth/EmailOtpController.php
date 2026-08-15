<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class EmailOtpController extends Controller
{
    public function __construct(private readonly EmailOtpService $otp) {}

    /**
     * Show the "enter the code we emailed you" screen. Sends a code
     * automatically on first visit if none is currently outstanding.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        if (! $user->email_otp_code || $user->email_otp_expires_at?->isPast()) {
            $this->otp->sendCode($user);
        }

        return Inertia::render('auth/verify-email-otp', [
            'email' => $user->email,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $this->otp->verify($user, $request->string('code')->toString())) {
            return back()->withErrors([
                'code' => 'Kode salah atau sudah kedaluwarsa. Coba kirim ulang.',
            ]);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Email berhasil diverifikasi.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        $key = 'email-otp-resend:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'code' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        RateLimiter::hit($key, 60);

        $this->otp->sendCode($user);

        return back()->with('success', 'Kode verifikasi baru telah dikirim.');
    }
}
