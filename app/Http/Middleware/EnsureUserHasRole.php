<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $currentRole = $request->user()->currentCompanyMembership()?->role;

        if (!$currentRole || $currentRole->name !== $role) {
            return Inertia::render('errors/403')
                ->toResponse($request)
                ->setStatusCode(403);
        }

        return $next($request);
    }
}
