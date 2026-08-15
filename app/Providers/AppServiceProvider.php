<?php

namespace App\Providers;

use App\Services\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class);
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
