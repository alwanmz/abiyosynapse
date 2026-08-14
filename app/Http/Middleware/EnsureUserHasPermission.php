<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $permissions = array_filter(array_map('trim', preg_split('/[,|]/', $permission) ?: []));
        $allowed = collect($permissions)->contains(
            fn (string $name) => $request->user()->hasPermissionTo($name),
        );

        if (!$allowed) {
            return Inertia::render('errors/403')
                ->toResponse($request)
                ->setStatusCode(403);
        }

        return $next($request);
    }
}
