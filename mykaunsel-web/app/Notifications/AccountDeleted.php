<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the address of the account that was just deleted (the address itself is erased from our records at the same moment), so a person who
 * did not ask for it finds out. Says what went and what stays.
 */
class AccountDeleted extends Notification
{
    /** @param  int  $cancelled  later sessions that were cancelled */
    public function __construct(private int $cancelled) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your MyKaunsel account has been deleted')
            ->greeting('Hello,')
            ->line('Your MyKaunsel account was deleted. Your name, e-mail address, chat conversations and Feed posts are erased, and you are signed out everywhere.');

        if ($this->cancelled > 0) {
            $mail->line($this->cancelled === 1 ? 'Your 1 later session was cancelled and the counselor has been told.' : "Your {$this->cancelled} later sessions were cancelled and the counselors have been told.");
        }

        return $mail
            ->line('Records of past bookings are kept for 90 days without your name, and what a counselor or organization wrote about a session stays with them, as the Privacy Policy explains.')
            ->line('If you did not ask for this, write to MyKaunsel support straight away.');
    }
}
