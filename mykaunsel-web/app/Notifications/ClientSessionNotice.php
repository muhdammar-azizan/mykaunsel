<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client that a session (or a session request) with a counselor will not go ahead because the counselor
 * was suspended. Deliberately contains ONLY the counselor's name and the time — no reason, no other details.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class ClientSessionNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'session'|'request'  $kind */
    public function __construct(private string $counselorName, private string $when, private string $kind = 'session')
    {
        $this->afterCommit(); // only queued once the database change that caused it is safe
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isSession = $this->kind === 'session';

        return (new MailMessage)
            ->subject($isSession ? 'Your counselling session has been cancelled' : 'Your session request could not go ahead')
            ->greeting("Hi {$notifiable->name},")
            ->line($isSession
                ? "We're sorry — your session with {$this->counselorName} on {$this->when} has been cancelled because the counselor is not available."
                : "We're sorry — your session request to {$this->counselorName} for {$this->when} could not go ahead because the counselor is not available.")
            ->line('You can book another counselor in the app. If you need to talk to someone right now, HEAL 15555 (24 hours) and Talian Kasih 15999 are there for you.');
    }
}
