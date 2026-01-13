<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnlineUserController extends Controller
{
    public function index()
    {
        // Show a list of current sessions, but only 1 per user (the latest one)
        // We can group by user_id and take max id, or filter in PHP.
        // Let's filter in PHP for simplicity as distinct on JSON/relationship can be tricky in SQL strict mode

        $onlineUsers = LoginLog::with(['user:id,name,email,team_id', 'user.team'])
            ->where('status', 'online')
            ->orderByDesc('login_at')
            ->get()
            ->unique('user_id') // Deduplicate by User ID
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user ? $log->user->name : 'Unknown',
                    'user_email' => $log->user ? $log->user->email : '',
                    'team' => $log->user && $log->user->team ? $log->user->team->name : '-',
                    'ip_address' => $log->ip_address,
                    'login_at' => $log->login_at->format('Y-m-d H:i:s'),
                    'last_activity' => $log->last_activity_at->diffForHumans(),
                    'platform' => $this->parseUserAgent($log->user_agent),
                ];
            })->values(); // Reset keys after unique

        // Get History (Last 50 Logins)
        $history = LoginLog::with(['user:id,name'])
            ->whereNotNull('logout_at')
            ->orderByDesc('logout_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user ? $log->user->name : 'Unknown',
                    'login_at' => $log->login_at->format('Y-m-d H:i:s'),
                    'logout_at' => $log->logout_at->format('Y-m-d H:i:s'),
                    'duration' => $log->login_at->diffForHumans($log->logout_at, true),
                    'ip_address' => $log->ip_address,
                ];
            });

        return Inertia::render('Security/OnlineUsers', [
            'onlineUsers' => $onlineUsers,
            'history' => $history
        ]);
    }

    public function clearHistory()
    {
        // Delete all logs where logout_at is present (completed sessions) or status is offline
        LoginLog::whereNotNull('logout_at')->delete();

        return back()->with('success', 'Login history cleared successfully.');
    }

    private function parseUserAgent($ua)
    {
        if (!$ua)
            return '-';
        if (str_contains($ua, 'Mobile'))
            return 'Mobile';
        if (str_contains($ua, 'Windows'))
            return 'Windows';
        if (str_contains($ua, 'Mac'))
            return 'Mac';
        if (str_contains($ua, 'Linux'))
            return 'Linux';
        return 'Desktop';
    }
}
