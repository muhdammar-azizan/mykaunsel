<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Sent by a Platform Admin to a counselor whose practising certificate (PA) is running out or has expired. */
class CounselorPaRenewalReminder extends Notification
{
    public function __construct(private Carbon $validUntil, private bool $expired) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->validUntil->format('j M Y');

        return (new MailMessage)
            ->subject($this->expired ? 'Your practising certificate has expired' : 'Your practising certificate is about to expire')
            ->greeting("Hi {$notifiable->name},")
            ->line($this->expired
                ? "Your practising certificate (PA) expired on {$date}. You cannot be listed or take new bookings until a valid PA is on record."
                : "Your practising certificate (PA) is valid until {$date}. After that day you will be suspended automatically and no longer listed.")
            ->line('Please renew it with the Lembaga Kaunselor Malaysia (LKM) as soon as you can.');
    }
}
