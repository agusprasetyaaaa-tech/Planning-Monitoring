<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SecuritySetting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip security check for authentication routes to prevent login blocking
        if ($request->is('login', 'register', 'logout', 'forgot-password', 'reset-password/*', 'sanctum/*')) {
            return $next($request);
        }

        // Cache settings for 60 seconds to reduce DB load
        $settings = Cache::remember('security_settings', 60, function () {
            try {
                return SecuritySetting::first();
            } catch (\Exception $e) {
                Log::warning('SecurityCheck: Could not load security settings: ' . $e->getMessage());
                return null;
            }
        });

        if (!$settings || !$settings->is_active) {
            return $next($request);
        }

        $ip = $request->ip();

        // 1. IP Block
        $blockedIps = $settings->blocked_ips ?? [];
        // Log for debugging
        Log::info("Security Check: Detection IP: {$ip}. Blocked IPs: " . json_encode($blockedIps));

        if (in_array($ip, $blockedIps)) {
            Log::warning("Security Check: BLOCKED IP {$ip} attempted access.");
            abort(403, 'Access denied: IP Blocked');
        }

        // 2. Country Block (Placeholder / Cloudflare Support)
        // If 'CF-IPCountry' header is present (Cloudflare), use it.
        $countryCode = $request->header('CF-IPCountry');
        if ($countryCode && in_array($countryCode, $settings->blocked_countries ?? [])) {
            abort(403, 'Access denied: Country Blocked');
        }

        // 3. Rate Limiting
        $key = 'security_limit:' . $ip;
        // Get limit from DB or default 1000
        $maxAttempts = $settings->rate_limit ?? 1000;

        // Force minimum 1000 in local environment to prevent lockouts during dev
        if (app()->environment('local', 'testing')) {
            $maxAttempts = max($maxAttempts, 1000);
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, 'Too many requests. Please try again in ' . $seconds . ' seconds.');
        }

        RateLimiter::hit($key, 60); // 60 seconds decay

        return $next($request);
    }
}
