<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Plan;
use App\Observers\PlanObserver;
use Illuminate\Support\Facades\URL; // 1. Tambahkan ini
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;

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
        // 2. Tambahkan kondisi ini untuk memaksa HTTPS di produksi
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
        Plan::observe(PlanObserver::class);
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
    }
}
