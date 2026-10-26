<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the counselor when a suspension is lifted. */
class CounselorReactivated extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MyKaunsel counselor account is active again')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your suspension has been lifted. You are listed in search again and can open slots. Please check your planner: slots you opened earlier are as you left them.')
            ->action('Sign in to MyKaunsel', route('login'));
    }
}
