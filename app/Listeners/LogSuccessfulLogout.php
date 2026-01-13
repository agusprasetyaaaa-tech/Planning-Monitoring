<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\LoginLog;

class LogSuccessfulLogout
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if ($event->user) {
            // Find the latest active session
            $log = LoginLog::where('user_id', $event->user->id)
                ->where('status', 'online')
                ->latest()
                ->first();

            if ($log) {
                $log->update([
                    'logout_at' => now(),
                    'status' => 'offline',
                    'last_activity_at' => now(),
                ]);
            }
        }
    }
}
