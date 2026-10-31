<?php

namespace App\Notifications;

use App\Models\OrgMemberInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an address (on demand: the person may not have an account yet) that an organization invited. It carries no link and no code: the
 * person opens the app with THIS address, and the invitation is waiting under Account > Your organization.
 */
class MemberInvitation extends Notification
{
    public function __construct(private string $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organization} invited you to MyKaunsel")
            ->greeting('Hello,')
            ->line("{$this->organization} invited you to MyKaunsel, so you can book its counselors.")
            ->line('Open the MyKaunsel app and sign in (or create an account) with THIS e-mail address. Then go to Account, then Your organization, and accept the invitation.')
            ->line('The invitation works for '.OrgMemberInvitation::VALID_DAYS.' days. If you were not expecting it, you can ignore this e-mail.');
    }
}
