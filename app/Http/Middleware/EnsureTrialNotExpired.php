<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after EnsureCompanyContext so CurrentCompany is already resolved.
 * A company with trial_ends_at in the past is locked out of everything
 * except the trial-expired page itself, switching companies (in case the
 * user has another, non-expired company), and logout.
 */
class EnsureTrialNotExpired
{
    private const EXEMPT_ROUTES = [
        'trial-expired',
        'company.switch',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company || ! $company->isTrialExpired()) {
            return $next($request);
        }

        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('trial-expired');
    }
}
