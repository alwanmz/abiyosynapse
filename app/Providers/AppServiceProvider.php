<?php

namespace App\Providers;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Ticket::observe(\App\Observers\TicketObserver::class);
        \App\Models\TicketComment::observe(\App\Observers\TicketCommentObserver::class);

        $this->registerGoogleDriveDisk();

        // Share dynamic favicon URL with the main Blade layout.
        \Illuminate\Support\Facades\View::composer('app', function ($view) {
            $company = \App\Models\CompanySetting::find(1);
            $faviconUrl = $company?->logo_path
                ? '/storage/' . $company->logo_path
                : '/favicon.ico';
            $view->with('faviconUrl', $faviconUrl);
        });
    }

    /**
     * Register the "google" filesystem disk (Google Drive via OAuth refresh
     * token). Used as the backup destination. The masbug adapter isn't a
     * native Laravel driver, so we wire it up here.
     */
    private function registerGoogleDriveDisk(): void
    {
        Storage::extend('google', function ($app, array $config) {
            $client = new GoogleClient();
            $client->setClientId($config['clientId'] ?? '');
            $client->setClientSecret($config['clientSecret'] ?? '');
            $client->refreshToken($config['refreshToken'] ?? '');

            $service = new GoogleServiceDrive($client);
            $adapter = new GoogleDriveAdapter($service, $config['folder'] ?: null);
            $flysystem = new Flysystem($adapter);

            return new FilesystemAdapter($flysystem, $adapter, $config);
        });
    }
}
