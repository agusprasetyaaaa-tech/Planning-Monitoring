<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\Mime\Email;

class CustomResetPasswordNotification extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = $this->resetUrl($notifiable);
        $logoPath = public_path('logo/logo.png');

        $mailMessage = (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->greeting(Lang::get('Hello!'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :count minutes.', [
                'count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire'),
            ]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'))
            ->salutation("Regards,\nIT Interprima");

        // Embed logo as CID attachment so it displays in Gmail
        if (file_exists($logoPath)) {
            $mailMessage->withSymfonyMessage(function (Email $message) use ($logoPath) {
                $message->embed(
                    fopen($logoPath, 'r'),
                    'logo.png',
                    'image/png'
                );
            });
        }

        return $mailMessage;
    }
}
