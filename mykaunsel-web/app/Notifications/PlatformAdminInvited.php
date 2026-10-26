<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone is made a Platform Admin. A brand-new account gets a link to choose its password (the normal
 * password-reset link); an existing account is simply told to sign in. No password is ever emailed.
 */
class PlatformAdminInvited extends Notification
{
    /** @param  ?string  $setPasswordUrl  null for an existing account that already has a password */
    public function __construct(private string $invitedBy, private ?string $setPasswordUrl = null, private int $linkMinutes = 60) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('You have been added as a MyKaunsel Platform Admin')
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->invitedBy} added you as a Platform Admin on MyKaunsel. You can review organizations, counselors and complaints.");

        if ($this->setPasswordUrl) {
            return $mail
                ->line('Choose a password to start:')
                ->action('Choose your password', $this->setPasswordUrl)
                ->line("This link works for {$this->linkMinutes} minutes. If it has expired, ask the admin who added you to send it again.");
        }

        return $mail
            ->line('Sign in with your existing MyKaunsel account and choose the Platform Admin role.')
            ->action('Sign in to MyKaunsel', route('login'))
            ->line('If you did not expect this, tell the admin who added you.');
    }
}
