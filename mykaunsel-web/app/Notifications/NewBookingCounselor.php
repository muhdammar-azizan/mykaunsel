<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "New booking", to the counselor. The date, time, mode and the client's display name ONLY: no e-mail, no phone and no link to client data.
 * For an online session it reminds the counselor to add a meeting link (a new booking never has one).
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{client: string, date: string, time: string, minutes: int, mode: string}  $session  mode is "online" or "physical"
 */
class NewBookingCounselor extends Notification implements ShouldQueue
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
        $online = $s['mode'] === 'online';

        $mail = (new MailMessage)
            ->subject('New booking')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$s['client']} booked a session with you.")
            ->line("Date: {$s['date']}")
            ->line("Time: {$s['time']} (Malaysia time), {$s['minutes']} minutes")
            ->line('Type: '.($online ? 'Online' : 'In-person'));

        if ($online) {
            $mail->line('This online session has no meeting link yet. Please add one in your MyKaunsel dashboard before the session.');
        }

        return $mail;
    }
}
