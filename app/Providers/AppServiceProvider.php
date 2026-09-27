<?php

namespace App\Providers;

use App\Contracts\Ai\VisionProvider;
use App\Services\CurrentCompany;
use App\Services\TenantLifecycleService;
use App\Services\Ai\GeminiVisionProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
        $this->app->scoped(TenantLifecycleService::class);
        $this->app->bind(VisionProvider::class, GeminiVisionProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share dynamic favicon URL with the main Blade layout.
        \Illuminate\Support\Facades\View::composer('app', function ($view) {
            $view->with('faviconUrl', '/favicon.svg');
        });
    }
}
