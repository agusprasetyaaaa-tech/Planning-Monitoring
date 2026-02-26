<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailSettingController extends Controller
{
    /**
     * Display the email settings page.
     */
    public function index()
    {
        // Get the first record or create a new empty instance
        $setting = EmailSetting::first();
        if (!$setting) {
            $setting = new EmailSetting();
            $setting->mail_mailer = 'smtp';
            $setting->mail_port = 587;
        }

        return Inertia::render('Settings/Email/Index', [
            'setting' => $setting
        ]);
    }

    /**
     * Update the email settings in storage.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => ['required', 'string', 'max:255'],
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        $setting = EmailSetting::first();

        if ($setting) {
            $setting->update($validated);
        } else {
            EmailSetting::create($validated);
        }

        return redirect()->back()->with('success', 'Email configuration updated successfully.');
    }
}
