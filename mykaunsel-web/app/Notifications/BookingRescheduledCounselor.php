<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Booking moved", to the counselor. The old and the new date and time, the mode and the client's display name ONLY: no e-mail, no phone and
 * no link to client data. It says the old time is open again.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{client: string, old_date: string, old_time: string, date: string, time: string, minutes: int, mode: string}  $session  mode is "online" or "physical"
 */
class BookingRescheduledCounselor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $session)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->session;

        return (new MailMessage)
            ->subject('Booking moved to a new time')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$s['client']} moved their session with you to a new time.")
            ->line("Was: {$s['old_date']}, {$s['old_time']} (Malaysia time)")
            ->line("Now: {$s['date']}, {$s['time']} (Malaysia time), {$s['minutes']} minutes")
            ->line('Type: '.($s['mode'] === 'online' ? 'Online' : 'In-person'))
            ->line('The old time is open for booking again.');
    }
}
