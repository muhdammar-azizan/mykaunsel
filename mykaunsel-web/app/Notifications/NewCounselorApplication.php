<?php

namespace App\Notifications;

use App\Models\CounselorProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a Platform Admin that an independent counselor has submitted (or resubmitted) their documents. */
class NewCounselorApplication extends Notification
{
    public function __construct(private CounselorProfile $profile, private bool $resubmitted = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $profile = $this->profile->loadMissing('user');
        $name = $profile->user?->name ?? 'A counselor';

        return (new MailMessage)
            ->subject(($this->resubmitted ? 'Counselor resubmitted: ' : 'New counselor application: ').$name)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$name} (KB {$profile->kb_number}) ".($this->resubmitted ? 'has resubmitted documents' : 'has submitted documents')." and is waiting for verification.")
            ->action('Review application', route('platform.counselors.show', $profile))
            ->line('You get this email because "New counselor application" is on in Account → Notifications.');
    }
}
