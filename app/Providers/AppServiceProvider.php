<?php

namespace App\Providers;

use App\Models\MatchParticipant;
use App\Models\Partida;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            // Tests exercise the build, isolated from a developer's live Vite server.
            Vite::useHotFile(storage_path('framework/testing/vite.hot'));
        }
        foreach ([Partida::class, MatchParticipant::class] as $model) {
            $invalidate = fn () => Cache::put('analytics:revision', (string) Str::uuid(), 120);
            $model::saved($invalidate);
            $model::deleted($invalidate);
        }
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(5)->by('register:'.$request->ip()),
            Limit::perHour(30)->by('register:global'),
        ]);
        RateLimiter::for('password-recovery', fn (Request $request) => Limit::perMinute(5)->by('recovery:'.$request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
    }
}
