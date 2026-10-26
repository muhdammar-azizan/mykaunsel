<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A warning from Platform Admin to a counselor after a client complaint. Contains the admin's message only —
 * never who complained or what the complaint said.
 */
class CounselorComplaintWarning extends Notification
{
    public function __construct(private string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A warning about your conduct on MyKaunsel')
            ->greeting("Hi {$notifiable->name},")
            ->line('Following a complaint from a client, the MyKaunsel team asks you to note the following:')
            ->line($this->message)
            ->line('This is a warning, not a suspension. If you want to respond, or think this is a mistake, just reply to this email.');
    }
}
