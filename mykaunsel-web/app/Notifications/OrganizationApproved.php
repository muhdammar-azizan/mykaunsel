<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the admin who registered the organization once Platform Admin approves it. */
class OrganizationApproved extends Notification
{
    public function __construct(private Organization $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organization->name} is approved on MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("Good news — {$this->organization->name} has been approved. You can now sign in and start setting up your organization.")
            ->action('Sign in to MyKaunsel', route('login'))
            ->line('Thank you for joining MyKaunsel.');
    }
}
