<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('kiosk-baca', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('kiosk-tulis', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Backend pure API JSON (DESAIN): tamu yang belum masuk harus dapat
        // 401 JSON via PenangananGalat, bukan redirect ke route web yang tak ada.
        Authenticate::redirectUsing(function (Request $request): ?string {
            if ($request->is('api/*') || $request->is('sanctum/*') || $request->expectsJson()) {
                return null;
            }

            return '/login';
        });
    }
}
