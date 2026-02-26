<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Plan;
use App\Observers\PlanObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Biarkan kosong
    }

    public function boot(): void
    {
        // Force HTTP scheme di local, HTTPS di production
        if ($this->app->environment('local')) {
            URL::forceScheme('http');
        } else {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
        Plan::observe(PlanObserver::class);
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);

        // Map Email Settings from DB to Config natively
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('email_settings')) {
                $mailSetting = \App\Models\EmailSetting::first();
                if ($mailSetting) {
                    $config = [
                        'mail.default' => $mailSetting->mail_mailer,
                        'mail.mailers.smtp.host' => $mailSetting->mail_host,
                        'mail.mailers.smtp.port' => $mailSetting->mail_port,
                        'mail.mailers.smtp.username' => $mailSetting->mail_username,
                        'mail.mailers.smtp.password' => $mailSetting->mail_password,
                        'mail.mailers.smtp.encryption' => $mailSetting->mail_encryption,
                        'mail.from.address' => $mailSetting->mail_from_address,
                        'mail.from.name' => $mailSetting->mail_from_name,
                    ];
                    config($config);
                }
            }
        } catch (\Exception $e) {
            // Do not break the app if DB is not ready during artisan commands
        }
    }
}