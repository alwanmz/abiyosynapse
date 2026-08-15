<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    /**
     * Route names reachable even when the user has no company membership
     * at all, to avoid a redirect loop into the "create your first
     * company" onboarding page.
     */
    private const EXEMPT_ROUTES = [
        'companies.create',
        'companies.store',
        'company.switch',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $company = $this->resolve($request, $user);

        if ($company === null) {
            if ($request->routeIs(...self::EXEMPT_ROUTES)) {
                return $next($request);
            }

            return redirect()->route('companies.create')
                ->with('error', 'Anda belum tergabung dalam perusahaan manapun. Silakan buat perusahaan baru.');
        }

        app(CurrentCompany::class)->set($company);

        return $next($request);
    }

    private function resolve(Request $request, User $user): ?Company
    {
        // 1. Session override (set by POST /company/switch)
        $sessionCompanyId = $request->session()->get('current_company_id');
        if ($sessionCompanyId) {
            $membership = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $sessionCompanyId)
                ->with('company')
                ->first();
            if ($membership) {
                return $membership->company;
            }
            $request->session()->forget('current_company_id');
        }

        // 2. users.current_company_id (persisted default across sessions)
        if ($user->current_company_id) {
            $membership = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $user->current_company_id)
                ->with('company')
                ->first();
            if ($membership) {
                return $membership->company;
            }
        }

        // 3. Membership flagged is_default
        $defaultMembership = CompanyUser::where('user_id', $user->id)
            ->where('is_default', true)
            ->with('company')
            ->first();
        if ($defaultMembership) {
            return $defaultMembership->company;
        }

        // 4. First membership (deterministic order)
        $firstMembership = CompanyUser::where('user_id', $user->id)
            ->orderBy('id')
            ->with('company')
            ->first();

        return $firstMembership?->company;
    }
}
