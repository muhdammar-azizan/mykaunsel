<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their Platform Admin access has ended. Deliberately short: the reason an admin wrote stays in the
 * audit log and is not repeated here, and neither is who removed them.
 * Queued and sent after the database commit (a queue worker must be running).
 */
class PlatformAdminRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MyKaunsel Platform Admin access has ended')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your Platform Admin access on MyKaunsel has ended, and you have been signed out on all your devices.')
            ->line('Your account is not deleted. If you did not expect this, please contact another Platform Admin.');
    }
}
