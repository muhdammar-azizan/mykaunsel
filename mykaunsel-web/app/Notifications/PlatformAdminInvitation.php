<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation email: a signed link that works once and for a limited time. It does not say whether the address already
 * has a MyKaunsel account. Sent to an address (on-demand), because the person may not have an account yet.
 * Queued and sent after the database commit (a queue worker must be running).
 *
 * The link is part of the queued job until it is sent; it stops working at its time limit, when it is replaced by
 * "Send again" and when the invitation is revoked.
 */
class PlatformAdminInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $invitedBy, private string $acceptUrl, private CarbonInterface $expiresAt)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = max(1, (int) ceil(now()->diffInMinutes($this->expiresAt, true) / 60));

        return (new MailMessage)
            ->subject('You are invited to be a MyKaunsel Platform Admin')
            ->greeting('Hello,')
            ->line("{$this->invitedBy} invited you to be a Platform Admin on MyKaunsel. Platform Admins review organizations, counselors and complaints.")
            ->line('Nothing changes for you until you accept.')
            ->action('Accept the invitation', $this->acceptUrl)
            ->line("This link works once and for about {$hours} hours. If it has stopped working, ask the admin who invited you to send it again.")
            ->line('If you were not expecting this, you can ignore this email.');
    }
}
