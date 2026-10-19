<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the NEW address; the old email stays active until this link is opened. */
class VerifyNewEmailAddress extends Notification
{
    public function __construct(private string $url, private int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your new MyKaunsel email address')
            ->greeting('Confirm your new email')
            ->line('Someone (hopefully you) asked to use this address for a MyKaunsel Platform Admin account.')
            ->action('Confirm email address', $this->url)
            ->line("This link expires in {$this->minutes} minutes. If you did not request this, ignore this email — nothing will change.");
    }
}
