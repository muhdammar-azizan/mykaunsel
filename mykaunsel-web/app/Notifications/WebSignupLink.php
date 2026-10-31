<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Here is the link to sign up on a computer", for someone who chose Organization or Counselor in the app (those accounts are made on the web).
 * Sent to whatever address the person typed, to nobody's account: it holds only a public sign-up address, no token and nothing about anyone.
 *
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class WebSignupLink extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'organization'|'counselor'  $type */
    public function __construct(private string $type)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->type === 'organization';

        return (new MailMessage)
            ->subject($organization ? 'Sign up your organization on MyKaunsel' : 'Sign up as a counselor on MyKaunsel')
            ->greeting('Hello,')
            ->line($organization
                ? 'You asked for a link to register an organization on MyKaunsel. Organizations sign up on a computer.'
                : 'You asked for a link to sign up as a counselor on MyKaunsel. Counselors sign up on a computer.')
            ->action($organization ? 'Register your organization' : 'Sign up as a counselor', route($organization ? 'organizations.signup.create' : 'counselors.signup.create'))
            ->line('If you did not ask for this, you can ignore this email: nothing happens unless you open the link.');
    }
}
