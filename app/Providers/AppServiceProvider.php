<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.meteo');
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('ingest', fn (Request $r) => Limit::perMinute(60)->by(sha1((string) $r->bearerToken()).'|'.$r->ip()));
        RateLimiter::for('cron', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('geocode', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('rest', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
