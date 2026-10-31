<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a person that their organization did not accept their request to join. Gives no reason: that is between them and their organization. */
class MembershipDeclined extends Notification
{
    public function __construct(private string $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organization} could not accept your request")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->organization} did not accept your request to join on MyKaunsel, so you can not book its counselors.")
            ->line('If you think this is a mistake, please ask your organization. You can still use the public counselors, the chat and the Feed in the app.');
    }
}
