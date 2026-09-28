<?php

use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureCompanyOnboarded;
use App\Http\Middleware\EnsureCompanyOperational;
use App\Http\Middleware\EnsureTrialNotExpired;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureTenantFeature;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The middleware configuration runs before Laravel registers the
        // config repository, so this bootstrap-only value must be read from
        // the environment directly.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            $middleware->trustProxies(at: $trustedProxies);
        }
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);
        $middleware->validateCsrfTokens(except: ['billing/webhook/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            EnsureCompanyContext::class,
            EnsureTrialNotExpired::class,
            EnsureCompanyOperational::class,
            EnsureCompanyOnboarded::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'feature' => EnsureTenantFeature::class,
        ]);

        // Resolve the tenant before SubstituteBindings so route-bound
        // company-scoped models cannot be looked up with an empty context.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            EnsureCompanyContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired CSRF token means the session lapsed, so send people back to
        // the right login page instead of a dead-end "419 Page Expired" screen.
        // Matched on status rather than TokenMismatchException because the
        // framework rewrites that into an HttpException(419) before rendering.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return null;
            }

            return redirect()->route('login')
                ->with('status', 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
        });
    })->create();
