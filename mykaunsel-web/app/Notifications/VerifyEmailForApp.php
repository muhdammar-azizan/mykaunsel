<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Confirm your email" for accounts created from the app. The person is not signed in on the web, so the link
 * (signed, expires) confirms the address by itself and then just says it worked: they go back to the app and sign in.
 */
class VerifyEmailForApp extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);

        $url = URL::temporarySignedRoute('verification.app', now()->addMinutes($minutes), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject('Confirm your MyKaunsel email address')
            ->greeting("Hi {$notifiable->name},")
            ->line('Please confirm this email address to finish creating your MyKaunsel account.')
            ->action('Confirm email address', $url)
            ->line("This link works for {$minutes} minutes. Afterwards, go back to the app and sign in.")
            ->line('If you did not create an account, you can ignore this email.');
    }
}
