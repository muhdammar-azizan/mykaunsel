<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A warning from Platform Admin to someone whose feed writing was reported. Contains the admin's message only, never who reported it. */
class FeedWarning extends Notification
{
    public function __construct(private string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A message about your writing on the MyKaunsel Feed')
            ->greeting("Hi {$notifiable->name},")
            ->line('Something you wrote on the Feed was reported, and the MyKaunsel team asks you to note the following:')
            ->line($this->message)
            ->line('This is a warning. If you want to respond, or think this is a mistake, just reply to this email.');
    }
}
