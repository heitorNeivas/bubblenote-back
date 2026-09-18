<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8));

        RateLimiter::for('verify-code', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('resend-code', fn (Request $request) => Limit::perMinute(3)
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
