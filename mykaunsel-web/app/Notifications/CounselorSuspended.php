<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the counselor when Platform Admin suspends them: why, and what happens to existing sessions. */
class CounselorSuspended extends Notification
{
    public function __construct(private string $reason, private bool $upcomingCancelled) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MyKaunsel counselor account has been suspended')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your counselor account has been suspended. You no longer appear in search and cannot open new slots.')
            ->line('Reason from our team:')
            ->line($this->reason)
            ->line($this->upcomingCancelled
                ? 'Your upcoming sessions have been cancelled and the clients have been told.'
                : 'Sessions that are already booked have not been changed.')
            ->line('If you think this is a mistake, or want to know what happens next, just reply to this email.');
    }
}
