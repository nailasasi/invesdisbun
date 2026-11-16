<?php

namespace App\Providers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
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
        // Tetapkan batas Remember Me tepat 30 hari (30 * 24 * 60 = 43.200 menit)
        if (Auth::guard('web') instanceof SessionGuard) {
            Auth::guard('web')->setRememberDuration(43200);
        }
    }
}
