<?php

namespace App\Http\Middleware;

use App\Services\AiService;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = auth('web')->user();
        $membership = $user?->currentCompanyMembership();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'ai' => [
                'enabled' => app(AiService::class)->isConfigured(),
            ],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'role' => $membership?->role,
            ],
            'currentCompany' => app(CurrentCompany::class)->get(),
            'companies' => $user
                ? $user->companies()
                    ->select('companies.id', 'companies.name', 'companies.code', 'companies.logo_path')
                    ->withPivot(['role_id', 'is_default'])
                    ->get()
                : [],
            'unreadNotifications' => $user?->unreadNotifications()->count() ?? 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
