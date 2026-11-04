<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the admins of an organization when one of its counselors leaves it by themselves. The counselor's name, how many later sessions
 * were cancelled: nothing about any client and not the reason the counselor wrote.
 */
class CounselorLeftOrganization extends Notification
{
    public function __construct(private string $counselorName, private string $organizationName, private int $cancelled) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->counselorName} left {$this->organizationName} on MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->counselorName} chose to stop working as a counselor for {$this->organizationName} on MyKaunsel.")
            ->line($this->cancelled > 0
                ? "{$this->cancelled} later ".($this->cancelled === 1 ? 'session' : 'sessions').' with your organization '.($this->cancelled === 1 ? 'was' : 'were').' cancelled and the clients were told.'
                : 'They had no later sessions with your organization.')
            ->line('The times they had opened for your organization were removed.');
    }
}
