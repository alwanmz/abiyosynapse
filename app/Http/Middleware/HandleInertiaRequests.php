<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use App\Services\AiService;
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

        $company = CompanySetting::find(1);

        $client = auth('client')->user();
        $portalAi = null;
        if ($client) {
            $limit = max(1, (int) config('services.deepseek.client_daily_limit', 5));
            $portalAi = [
                'enabled' => app(AiService::class)->isConfigured(),
                'limit' => $limit,
                'remaining' => max(0, $limit - \App\Models\ClientAiUsage::todayCountFor((int) $client->id)),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'company' => [
                'nama_perusahaan' => $company?->nama_perusahaan,
                'logo_path' => $company?->logo_path,
            ],
            'ai' => [
                'enabled' => app(AiService::class)->isConfigured(),
            ],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => auth('web')->user()?->load('role.permissions:id,name'),
                'client' => $client?->only('id', 'kode', 'nama', 'username', 'email'),
            ],
            'portalAi' => $portalAi,
            'unreadNotifications' => auth('web')->user()?->unreadNotifications()->count() ?? 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
