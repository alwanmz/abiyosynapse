<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
        ]);
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

            $route = $request->is('portal', 'portal/*') ? 'portal.login' : 'login';

            return redirect()->route($route)
                ->with('status', 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
        });

        // Unauthenticated requests on the client guard belong on the portal
        // login page, not the staff login page.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if (in_array('client', $e->guards(), true) && ! $request->expectsJson()) {
                return redirect()->guest(route('portal.login'));
            }

            return null;
        });
    })->create();
