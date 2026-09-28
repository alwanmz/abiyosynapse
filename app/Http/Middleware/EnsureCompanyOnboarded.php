<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A company must finish the chart-of-accounts onboarding before it can use
 * the rest of the app. Runs after the trial and operational checks, so a
 * locked company still lands on billing first.
 */
class EnsureCompanyOnboarded
{
    private const EXEMPT_ROUTES = [
        'onboarding.*',
        'billing.*',
        'trial-expired',
        'company.subscription*',
        'company.switch',
        'companies.create',
        'companies.store',
        'verification.*',
        'profile.*',
        'user-password.*',
        'appearance.*',
        'two-factor.*',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company || $company->isOnboarded() || ! $request->user() || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(409, 'Selesaikan pengaturan awal perusahaan terlebih dahulu.');
        }

        return redirect()->route('onboarding.show');
    }
}
