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
        // Production always runs behind TLS (Let's Encrypt via CloudPanel);
        // forcing the scheme keeps generated URLs correct behind the proxy.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
