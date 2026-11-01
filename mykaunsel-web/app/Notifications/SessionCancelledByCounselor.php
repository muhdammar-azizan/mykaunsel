<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client that their counselor cancelled a session, with the short reason the counselor wrote for them. Only the counselor's name, the
 * time and that reason: nothing else about either person.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class SessionCancelledByCounselor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $counselorName, private string $when, private string $reason)
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
            ->subject('Your counselling session has been cancelled')
            ->greeting("Hi {$notifiable->name},")
            ->line("We're sorry: {$this->counselorName} had to cancel your session on {$this->when}.")
            ->line("Their message to you: {$this->reason}")
            ->line('You can book another time in the app. If you need to talk to someone right now, HEAL 15555 (24 hours) and Talian Kasih 15999 are there for you.');
    }
}
