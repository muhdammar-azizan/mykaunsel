<?php

namespace App\Notifications;

use App\Models\OrgCounselorInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an address (on demand: the counselor may not have an account yet) that an organization invited to be one of its counselors. No link and
 * no code: the counselor signs in to the website with THIS address and answers on the Dashboard.
 */
class CounselorInvitation extends Notification
{
    public function __construct(private string $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organization} invited you to be one of its counselors on MyKaunsel")
            ->greeting('Hello,')
            ->line("{$this->organization} invited you to offer counselling to its members through MyKaunsel.")
            ->line('Sign in to the MyKaunsel website as a counselor with THIS e-mail address (you need a counselor account that MyKaunsel has approved) and answer the invitation on your Dashboard.')
            ->line('The invitation works for '.OrgCounselorInvitation::VALID_DAYS.' days. If you were not expecting it, you can ignore this e-mail.');
    }
}
