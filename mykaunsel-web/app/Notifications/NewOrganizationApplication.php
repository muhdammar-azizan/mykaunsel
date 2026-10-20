<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a Platform Admin that an organization registered and is waiting for approval. */
class NewOrganizationApplication extends Notification
{
    public function __construct(private Organization $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = $this->organization->loadMissing(['orgDomains', 'registrant.user']);
        $registrant = $org->registrant?->user;
        $domains = $org->orgDomains->pluck('domain')->implode(', ');

        $mail = (new MailMessage)
            ->subject("New organization application: {$org->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$org->name} ({$org->org_type->value}) has registered and is waiting for approval.");

        if ($registrant) {
            $mail->line("Registered by {$registrant->name} ({$registrant->email}).");
        }

        if ($domains !== '') {
            $mail->line("Claimed domains: {$domains}");
        }

        return $mail
            ->action('Review application', route('platform.organizations.show', $org))
            ->line('You get this email because "New organization application" is on in Account → Notifications.');
    }
}
