<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * "Your subscription has ended", once, to an organization admin. Always sent. The last day is queued with it (see SubscriptionEndingMail). Queued and sent
 * after the database commit (a queue worker must be running).
 */
class SubscriptionEndedMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  string  $endsOn  the last day, Y-m-d */
    public function __construct(private int $organizationId, private string $endsOn)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = Organization::query()->findOrFail($this->organizationId);

        return (new MailMessage)
            ->subject("Your subscription has ended ({$org->name})")
            ->greeting("Hi {$notifiable->name},")
            ->line("The MyKaunsel subscription of {$org->name} ended on ".Carbon::parse($this->endsOn)->format('j M Y').'.')
            ->line('Your members can not book your counselors any more, and your counselors can not open new times, until the subscription is renewed. Sessions that were already booked up to and on the last day still went ahead.')
            ->line('When you renew, the times your counselors opened come back by themselves.')
            ->action('Open the Subscription page', route('org.subscription'));
    }
}
