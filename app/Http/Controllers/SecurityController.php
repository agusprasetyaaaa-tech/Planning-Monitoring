<?php

namespace App\Http\Controllers;

use App\Models\SecuritySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class SecurityController extends Controller
{
    public function index()
    {
        $settings = SecuritySetting::firstOrCreate([], [
            'rate_limit' => 60,
            'blocked_ips' => [],
            'blocked_countries' => [],
            'is_active' => true,
        ]);

        return Inertia::render('Security/Index', [
            'settings' => $settings,
            'current_ip' => request()->ip(),
            'countries' => $this->getCountries(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'rate_limit' => 'required|integer|min:1',
            'blocked_ips' => 'nullable|array',
            'blocked_countries' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $settings = SecuritySetting::first();

        $settings->fill([
            'rate_limit' => $request->rate_limit,
            'blocked_ips' => $request->blocked_ips ?? [],
            'blocked_countries' => $request->blocked_countries ?? [],
            'is_active' => $request->is_active,
        ]);

        $message = 'Security settings updated successfully.';

        if ($settings->isDirty('is_active')) {
            $message = $settings->is_active ? 'Security features have been enabled.' : 'Security features have been disabled.';
        }

        $settings->save();

        Cache::forget('security_settings');

        return redirect()->back()->with('success', 'Security settings updated successfully.');
    }

    private function getCountries()
    {
        return [
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'RU', 'name' => 'Russia'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'MY', 'name' => 'Malaysia'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'KR', 'name' => 'South Korea'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'AU', 'name' => 'Australia'],
        ];
    }
}
