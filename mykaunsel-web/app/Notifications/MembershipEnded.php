<?php

namespace App\Notifications;

use App\Services\Chat\CrisisDetector;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their membership of an organization ended (they left, or the organization ended it) and what that means for their
 * sessions. Sent to the person only: their own sessions may be listed here, the organization never gets this.
 */
class MembershipEnded extends Notification
{
    /**
     * @param  'person'|'organization'  $by
     * @param  list<string>  $cancelled  "date, time with the counselor"
     * @param  list<string>  $kept
     */
    public function __construct(private string $organization, private string $by, private array $cancelled, private array $kept) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your membership of {$this->organization} has ended")
            ->greeting("Hi {$notifiable->name},")
            ->line($this->by === 'person'
                ? "You left {$this->organization} on MyKaunsel. You can no longer book its counselors."
                : "{$this->organization} has ended your membership on MyKaunsel, so you can no longer book its counselors.");

        if ($this->cancelled !== []) {
            $mail->line('These sessions with its counselors have been cancelled, and the counselors have been told:');

            foreach ($this->cancelled as $session) {
                $mail->line('- '.$session);
            }
        }

        if ($this->kept !== []) {
            $mail->line('These sessions are soon, so they still go ahead:');

            foreach ($this->kept as $session) {
                $mail->line('- '.$session);
            }
        }

        $lines = implode(' · ', array_map(fn (array $h) => "{$h['name']} {$h['number']}", CrisisDetector::helplines()));

        return $mail
            ->line('You can still talk to the public counselors in the MyKaunsel app, and use the chat and the Feed.')
            ->line("If you need to talk to someone right now: {$lines}.");
    }
}
