<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a counselor that their request to re-check the LKM record was not accepted, with the reason.
 * Queued and sent after the database commit (a queue worker must be running).
 */
class LkmReviewRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $reason)
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
            ->subject('Update on your LKM re-check request')
            ->greeting("Hi {$notifiable->name},")
            ->line('We looked at your request to update your record in our LKM directory and could not accept it.')
            ->line('Reason from our team:')
            ->line($this->reason)
            ->line('You can send a new request with clearer evidence. Questions? Just reply to this email.')
            ->action('Sign in to MyKaunsel', route('login'));
    }
}
