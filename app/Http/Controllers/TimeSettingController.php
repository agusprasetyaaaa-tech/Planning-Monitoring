<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TimeSettingController extends Controller
{
    public function index()
    {
        $settings = \App\Models\TimeSetting::firstOrCreate([], [
            'allowed_plan_creation_days' => ['Friday'], // default
        ]);

        return \Inertia\Inertia::render('TimeSettings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'time_offset_days' => 'nullable|integer|min:0',
            'planning_time_unit' => 'required|string',
            'planning_warning_threshold' => 'required|integer',
            'plan_expiry_value' => 'required|integer',
            'plan_expiry_unit' => 'required|string',
            'allowed_plan_creation_days' => 'nullable|array',
            'testing_mode' => 'boolean',
        ]);

        $settings = \App\Models\TimeSetting::first();
        if ($settings) {
            $settings->update($validated);
        }

        return redirect()->back()->with('success', 'Time configuration updated successfully.');
    }
}
