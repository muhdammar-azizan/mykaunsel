<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * "Your subscription is about to end", to an organization admin ('admin') or to a counselor of the organization ('counselor'). Always sent. The last day
 * and the days left are queued with it, so a mail that waits in a busy queue (or is sent after a renewal) still says what was true when it was made.
 * Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class SubscriptionEndingMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'admin'|'counselor'  $audience  @param  string  $endsOn  the last day, Y-m-d */
    public function __construct(private int $organizationId, private int $daysLeft, private string $audience, private string $endsOn)
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
        $last = Carbon::parse($this->endsOn)->format('j M Y');
        $when = match ($this->daysLeft) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$this->daysLeft} days",
        };

        $mail = (new MailMessage)
            ->subject("Your subscription ends {$when} ({$org->name})")
            ->greeting("Hi {$notifiable->name},");

        if ($this->audience === 'counselor') {
            return $mail
                ->line("The MyKaunsel subscription of {$org->name} ends {$when}. Its last day is {$last}.")
                ->line("After that day clients can no longer book you for {$org->name}, and the times you opened after it are hidden from them. Sessions already booked up to and on the last day still go ahead.")
                ->line('The times come back by themselves when your organization renews. You do not need to do anything.');
        }

        return $mail
            ->line("The MyKaunsel subscription of {$org->name} ends {$when}. Its last day is {$last}.")
            ->line('After that day your members can no longer book your counselors, and your counselors can not open new times. Sessions already booked up to and on the last day still go ahead.')
            ->line('To keep going without a break, renew before then: the new year starts the day after the last day, so no paid day is lost.')
            ->action('Open the Subscription page', route('org.subscription'));
    }
}
