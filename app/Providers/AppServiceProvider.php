<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Politica de senha padrao (usada por Password::defaults() nos requests).
        // Em producao tambem barra senhas vazadas (checagem HIBP).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // O listener Registered -> SendEmailVerificationNotification ja e
        // registrado automaticamente pelo framework (Laravel 12). Nao registrar
        // aqui de novo, senao o e-mail de verificacao sai em duplicado.

        // Limites SEPARADOS por proposito. O `throttle:N,M` sem nome e keyed so
        // pelo id do usuario, entao verify e resend compartilhariam o mesmo balde.
        RateLimiter::for('verify-code', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('resend-code', fn (Request $request) => Limit::perMinute(3)
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
