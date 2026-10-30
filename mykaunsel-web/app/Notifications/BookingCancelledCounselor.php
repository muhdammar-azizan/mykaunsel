<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Booking cancelled", to the counselor. The date, time, mode and the client's display name ONLY: no e-mail, no phone and no link to client
 * data. It says the time is open again.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{client: string, date: string, time: string, minutes: int, mode: string}  $session  mode is "online" or "physical"
 */
class BookingCancelledCounselor extends Notification implements ShouldQueue
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
            ->subject('Booking cancelled')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$s['client']} cancelled their session with you.")
            ->line("Date: {$s['date']}")
            ->line("Time: {$s['time']} (Malaysia time), {$s['minutes']} minutes")
            ->line('Type: '.($s['mode'] === 'online' ? 'Online' : 'In-person'))
            ->line('The time is open for booking again.');
    }
}
