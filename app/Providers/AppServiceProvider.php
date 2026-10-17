<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            // Tests exercise the build, isolated from a developer's live Vite server.
            Vite::useHotFile(storage_path('framework/testing/vite.hot'));
        }
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
    }
}
