<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an organization's admins that someone used a reference code and waits for approval. It says how many are waiting, not who: the names
 * are on the Members page, behind the admin's sign-in. One e-mail covers everyone who asks within a few minutes.
 */
class MemberWaitingApproval extends Notification
{
    public function __construct(private string $organization, private int $waiting) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->waiting === 1 ? 'One person is' : "{$this->waiting} people are";

        return (new MailMessage)
            ->subject("{$who} waiting for approval at {$this->organization}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$who} waiting for your approval after using a reference code of {$this->organization} on MyKaunsel.")
            ->action('Open the Members page', route('org.members'))
            ->line('Approve only people who work for you. Until you answer, they can not book your counselors. More people who ask in the next few minutes are covered by this e-mail.');
    }
}
