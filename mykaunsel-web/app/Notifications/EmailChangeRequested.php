<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the CURRENT address so the owner can spot an unauthorised change. */
class EmailChangeRequested extends Notification
{
    public function __construct(private string $newEmailMasked) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email change requested on your MyKaunsel account')
            ->line("A request was made to change your account email to {$this->newEmailMasked}.")
            ->line('Your current email stays active until the new address is confirmed. If this was not you, change your password immediately.');
    }
}
