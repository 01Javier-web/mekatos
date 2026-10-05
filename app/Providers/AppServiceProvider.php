<?php

namespace App\Providers;

use App\Support\LoginAttempts;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Límite de peticiones del login web. Es independiente del bloqueo por
        // cuenta (3 fallos en 15 minutos, ver LoginAttempts): frena ataques de
        // fuerza bruta o contra muchas cuentas desde una IP. El límite por IP es
        // amplio porque todo el personal puede salir por la misma IP del local.
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            // Mismo mensaje exista o no el correo: no revela nada de la cuenta.
            $tooManyAttempts = fn () => redirect()->route('login')
                ->withErrors(['email' => LoginAttempts::THROTTLED_ERROR])
                ->withInput($request->only('email'));

            return [
                Limit::perMinute(20)->by('ip:'.$request->ip())->response($tooManyAttempts),
                Limit::perMinute(5)->by('email:'.$email)->response($tooManyAttempts),
            ];
        });
    }
}
