<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * A valid bcrypt hash of a random value, used for constant-time comparison
     * when no matching client exists so login timing can't be used to enumerate
     * accounts. It never matches any real password.
     */
    private const DUMMY_HASH = '$2y$12$7xdcZFBItbU6Z70.hoyfI.9.0BAVv/Cft/vgWIgYqbmDPaaFDahEe';

    /**
     * Show the client portal login page (mirrors the staff login visually).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/client-login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Attempt to authenticate a client against the "client" guard.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // The single login field accepts a username (primary) or an email.
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited($request);

        // Resolve the client by username first, falling back to email. Values are
        // bound by Eloquent (PDO parameters), so this is not SQL-injectable.
        $login = $validated['username'];
        $client = \App\Models\Client::where('username', $login)
            ->orWhere('email', $login)
            ->first();

        // Always run a hash check — against a dummy hash when the client/password
        // is missing — so response timing does not reveal whether an account
        // exists (prevents username/email enumeration).
        $hashToCheck = $client && $client->password
            ? $client->password
            : self::DUMMY_HASH;
        $passwordOk = \Illuminate\Support\Facades\Hash::check($validated['password'], $hashToCheck);

        if (! $client || ! $client->password || ! $passwordOk) {
            $this->hitRateLimiter($request);

            throw ValidationException::withMessages([
                'username' => 'Username/email atau kata sandi salah.',
            ]);
        }

        Auth::guard('client')->login($client, $request->boolean('remember'));

        $this->clearRateLimiter($request);
        $request->session()->regenerate();

        // Honour a portal URL the client was redirected away from, else land on
        // their ticket dashboard. Default guards against a stale root intended URL.
        $intended = $request->session()->pull('url.intended');
        if ($intended && str_contains($intended, '/portal')) {
            return redirect()->to($intended);
        }

        return redirect()->route('portal.dashboard');
    }

    /**
     * Log the client out of the "client" guard only, leaving any concurrent
     * staff "web" session in the same browser untouched.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();

        return redirect()->route('portal.login');
    }

    /**
     * Two-layer brute-force protection:
     *  - per username+IP (5/min): stops hammering one account.
     *  - per IP        (20/min): stops rotating many usernames from one IP.
     * Mirrors the intent of the staff login limiter (FortifyServiceProvider).
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        foreach ([$this->throttleKey($request) => 5, $this->ipKey($request) => 20] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages([
                    'username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
                ]);
            }
        }
    }

    private function hitRateLimiter(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request));
        RateLimiter::hit($this->ipKey($request));
    }

    private function clearRateLimiter(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
        RateLimiter::clear($this->ipKey($request));
    }

    private function throttleKey(Request $request): string
    {
        return 'portal-login|'.Str::transliterate(Str::lower((string) $request->input('username'))).'|'.$request->ip();
    }

    private function ipKey(Request $request): string
    {
        return 'portal-login-ip|'.$request->ip();
    }
}
