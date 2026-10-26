<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the admins of an organization when one of its counselors is suspended by the platform.
 * The platform's reason is not included: it is between the platform and the counselor.
 */
class CounselorSuspendedForOrganization extends Notification
{
    public function __construct(private string $counselorName, private string $organizationName, private bool $upcomingCancelled) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->counselorName} has been suspended on MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->counselorName}, a counselor of {$this->organizationName}, has been suspended by the MyKaunsel platform. They no longer appear in search and cannot open new slots.")
            ->line($this->upcomingCancelled
                ? 'Their upcoming sessions have been cancelled and the clients have been told.'
                : 'Sessions that are already booked have not been changed.')
            ->line('If you have questions, reply to this email.');
    }
}
