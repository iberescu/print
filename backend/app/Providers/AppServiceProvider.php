<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Behind Cloudflare's Flexible SSL the origin sees HTTP, so generated
        // asset/route URLs would be http:// and get blocked as mixed content on
        // the HTTPS site. Force https in production.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
