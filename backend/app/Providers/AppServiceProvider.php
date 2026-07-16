<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
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
        // This app has no web "login" route — without this, an unauthenticated
        // API request whose client didn't send "Accept: application/json"
        // (e.g. a bare curl/HttpURLConnection call) crashes with a 500 instead
        // of a clean 401, because the default middleware tries to redirect to
        // a route that doesn't exist.
        Authenticate::redirectUsing(fn () => null);
    }
}
