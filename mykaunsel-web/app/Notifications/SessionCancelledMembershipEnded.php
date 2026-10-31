<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a counselor that a later session was cancelled because the client is no longer a member of the organization. Date and time only. */
class SessionCancelledMembershipEnded extends Notification
{
    public function __construct(private string $organization, private string $client, private string $when) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking cancelled')
            ->greeting("Hi {$notifiable->name},")
            ->line("The session with {$this->client} on {$this->when} was cancelled because they are no longer a member of {$this->organization}.")
            ->line('The time is open for booking again.');
    }
}
