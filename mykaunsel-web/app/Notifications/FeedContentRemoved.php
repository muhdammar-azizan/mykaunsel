<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells someone that a post or comment of theirs was removed from the Feed. Contains the admin's note only, never who reported it. */
class FeedContentRemoved extends Notification
{
    public function __construct(private string $kind, private ?string $note) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your {$this->kind} was removed from the MyKaunsel Feed")
            ->greeting("Hi {$notifiable->name},")
            ->line("After a report, the MyKaunsel team removed a {$this->kind} you wrote on the Feed because it did not follow our community rules.");

        if (filled($this->note)) {
            $mail->line($this->note);
        }

        return $mail->line('If you think this is a mistake, just reply to this email.');
    }
}
