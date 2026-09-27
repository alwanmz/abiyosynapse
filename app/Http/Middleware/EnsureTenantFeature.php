<?php

namespace App\Http\Middleware;

use App\Services\TenantLifecycleService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (app(TenantLifecycleService::class)->allowsFeature($feature)) {
            return $next($request);
        }

        return Inertia::render('errors/403')
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
