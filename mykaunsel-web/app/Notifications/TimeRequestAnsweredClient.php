<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The answer to a time request, to the person who asked: approved (now a booking), declined (with the counselor's reason if they gave one) or
 * expired (nobody answered in time). The counselor's public name, the time and the kind only. No meeting link and no street address.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 *
 * @param  array{outcome: string, counselor: string, date: string, time: string, minutes: int, mode: string, reason: ?string}  $request  outcome is approved | declined | expired
 */
class TimeRequestAnsweredClient extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $request)
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
        $r = $this->request;

        $mail = (new MailMessage)->greeting("Hi {$notifiable->name},");

        match ($r['outcome']) {
            'approved' => $mail->subject('Your time request was approved')->line("{$r['counselor']} approved your request. Your session is booked."),
            'declined' => $mail->subject('Your time request was declined')->line("{$r['counselor']} could not take your request."),
            default => $mail->subject('Your time request expired')->line("{$r['counselor']} did not answer in time, so your request closed."),
        };

        $mail->line("Date: {$r['date']}")
            ->line("Time: {$r['time']} (Malaysia time), {$r['minutes']} minutes")
            ->line('Type: '.($r['mode'] === 'online' ? 'Online' : 'In-person'));

        if ($r['outcome'] === 'declined' && filled($r['reason'])) {
            $mail->line("Their message: {$r['reason']}");
        }

        return $mail->line($r['outcome'] === 'approved' ? 'Open the MyKaunsel app to see your booking.' : 'You can choose another time in the MyKaunsel app.');
    }
}
