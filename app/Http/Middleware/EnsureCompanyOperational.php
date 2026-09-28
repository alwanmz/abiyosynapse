<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOperational
{
    private const EXEMPT_ROUTES = [
        'trial-expired',
        'billing.*',
        'company.subscription',
        'company.subscription.suspend',
        'company.subscription.activate',
        'company.switch',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company || $company->isOperational() || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('company.subscription');
    }
}
