<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the registering admin when an application is rejected. Includes the reason written by the reviewer. */
class OrganizationRejected extends Notification
{
    public function __construct(private Organization $organization, private string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on your MyKaunsel application for {$this->organization->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("We reviewed the application for {$this->organization->name} and could not approve it at this time.")
            ->line('Reason from our team:')
            ->line($this->reason)
            ->line('If you think this is a mistake, or you have questions, just reply to this email.');
    }
}
