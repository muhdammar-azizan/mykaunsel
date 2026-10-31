<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a person that their organization accepted their request to join (made with a reference code). */
class MembershipApproved extends Notification
{
    public function __construct(private string $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organization} accepted you on MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->organization} has accepted your request to join. You can now book the counselors of {$this->organization} in the MyKaunsel app.")
            ->line('Open the app to see them.');
    }
}
