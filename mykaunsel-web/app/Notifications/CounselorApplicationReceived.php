<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the applicant once their application is complete (all three documents are in) and again when they send documents after a rejection:
 * "we have it, here is what happens next". It carries no KB, PA or ID number and no document: only that it arrived and where to follow it.
 */
class CounselorApplicationReceived extends Notification
{
    public function __construct(private bool $resubmitted = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->resubmitted ? 'We received your documents again' : 'We received your MyKaunsel counselor application')
            ->greeting("Hi {$notifiable->name},")
            ->line($this->resubmitted
                ? 'Thank you. We received your documents and your application is back in the review queue.'
                : 'Thank you. We received your application and your documents.')
            ->line('Our team reviews every application by hand. This usually takes 1-2 business days. We will e-mail you as soon as there is a decision.')
            ->action('See your application status', route('counselors.signup.waiting-approval'))
            ->line('If you did not apply to MyKaunsel, you can ignore this e-mail.');
    }
}
