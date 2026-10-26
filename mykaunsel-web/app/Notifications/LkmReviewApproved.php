<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a counselor that the re-check of their LKM record was approved, with the practising-certificate date now on record.
 * Queued and sent after the database commit (a queue worker must be running).
 */
class LkmReviewApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Carbon $validUntil, private bool $stillSuspended)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your LKM record has been updated')
            ->greeting("Hi {$notifiable->name},")
            ->line('We checked the evidence you sent and updated your record in our LKM directory.')
            ->line('Your practising certificate (PA) is now valid until '.$this->validUntil->format('j M Y').'.');

        if ($this->stillSuspended) {
            $mail->line('Your counselor account is still suspended. Lifting a suspension is a separate decision by our team, and you will be emailed when it happens.');
        }

        return $mail->action('Sign in to MyKaunsel', route('login'));
    }
}
