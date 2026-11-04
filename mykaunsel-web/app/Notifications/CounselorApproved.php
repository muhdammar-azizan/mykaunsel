<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the counselor when Platform Admin approves their application.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class CounselorApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MyKaunsel counselor account is approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('Good news — your credentials have been verified and your counselor account is approved. You are now listed and can open slots for clients.')
            ->action('Sign in to MyKaunsel', route('login'))
            ->line('Thank you for joining MyKaunsel.');
    }
}
