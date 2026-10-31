<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your session is booked", to the person who booked. Only what they chose: the counselor's public name, the time, the length, the mode,
 * the place's name and city, and the cancellation deadline. No meeting link, no street address, no organization name.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{counselor: string, date: string, time: string, minutes: int, mode: string, place: ?string, deadline: ?string}  $session
 */
class BookingConfirmedClient extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $session)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        // The person's own choice (Account > Notifications).
        return $notifiable->wantsNotification('booking_emails') ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->session;

        $mail = (new MailMessage)
            ->subject('Your counselling session is booked')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your session with {$s['counselor']} is confirmed.")
            ->line("Date: {$s['date']}")
            ->line("Time: {$s['time']} (Malaysia time), {$s['minutes']} minutes")
            ->line('Type: '.($s['place'] !== null ? "In-person, {$s['place']}" : 'Online'));

        $mail->line($s['deadline'] !== null
            ? "You can cancel until {$s['deadline']} (Malaysia time)."
            : 'This session starts soon, so it can no longer be cancelled.');

        return $mail->line('Open the MyKaunsel app to see your booking.');
    }
}
