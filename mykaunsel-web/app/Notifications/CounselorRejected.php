<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the counselor when their application is rejected: the reason, and which documents to upload again. */
class CounselorRejected extends Notification
{
    /** @param  list<string>  $documentsToReplace  labels, e.g. "ID / passport" */
    public function __construct(private string $reason, private array $documentsToReplace = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Update on your MyKaunsel counselor application')
            ->greeting("Hi {$notifiable->name},")
            ->line('We reviewed your application and could not approve it yet.')
            ->line('Reason from our team:')
            ->line($this->reason);

        if ($this->documentsToReplace !== []) {
            $mail->line('Please upload these documents again: '.implode(', ', $this->documentsToReplace).'.');
        }

        return $mail
            ->action('Upload documents', route('counselors.signup.documents'))
            ->line('You only need to replace the documents listed above. Questions? Just reply to this email.');
    }
}
