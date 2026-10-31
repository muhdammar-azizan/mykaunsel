<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells an organization's admins that a member left by themselves. The name only: never their sessions, counselors or anything else. */
class MemberLeft extends Notification
{
    public function __construct(private string $organization, private string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->name} left {$this->organization} on MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->name} has left {$this->organization} on MyKaunsel by their own choice.")
            ->line('They can no longer book your counselors. You can see who belongs to your organization on the Members page.');
    }
}
