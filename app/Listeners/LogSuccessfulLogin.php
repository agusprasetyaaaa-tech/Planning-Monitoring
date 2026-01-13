<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\LoginLog;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct(protected Request $request)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if ($event->user) {
            // Close any previous open sessions for this user to avoid duplicates
            LoginLog::where('user_id', $event->user->id)
                ->where('status', 'online')
                ->update([
                    'status' => 'offline',
                    'logout_at' => now(),
                ]);

            LoginLog::create([
                'user_id' => $event->user->id,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'login_at' => now(),
                'status' => 'online',
                'last_activity_at' => now(),
            ]);
        }
    }
}
