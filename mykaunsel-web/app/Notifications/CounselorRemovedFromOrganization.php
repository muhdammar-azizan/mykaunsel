<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a counselor that an organization ended their assignment, and what that did to their calendar. Date counts only: no client is named. */
class CounselorRemovedFromOrganization extends Notification
{
    public function __construct(private string $organization, private int $cancelled, private string $reason = '')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->organization} ended your assignment")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->organization} ended your assignment as one of its counselors on MyKaunsel.")
            ->when($this->reason !== '', fn (MailMessage $m) => $m->line('The reason they gave: '.$this->reason))
            ->line($this->cancelled > 0
                ? "{$this->cancelled} later ".($this->cancelled === 1 ? 'session' : 'sessions').' with that organization '.($this->cancelled === 1 ? 'was' : 'were').' cancelled and the clients were told.'
                : 'You had no later sessions with that organization.')
            ->line('Times you had opened for that organization were removed. Your other work on MyKaunsel is not affected.');

        return $mail;
    }
}
