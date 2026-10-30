<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Someone asked for a time", to the counselor. The client's display name, the date, time and kind ONLY (the note is read in the portal, not
 * mailed). It says until when the request waits.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{client: string, date: string, time: string, minutes: int, mode: string, until: string}  $request  mode is "online" or "physical"
 */
class NewTimeRequestCounselor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $request)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;

        return (new MailMessage)
            ->subject('New time request')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$r['client']} asked for a session with you.")
            ->line("Date: {$r['date']}")
            ->line("Time: {$r['time']} (Malaysia time), {$r['minutes']} minutes")
            ->line('Type: '.($r['mode'] === 'online' ? 'Online' : 'In-person'))
            ->line("Please answer by {$r['until']} (Malaysia time). After that the request closes by itself.")
            ->line('Open the Appointments page of your portal to approve or decline.');
    }
}
