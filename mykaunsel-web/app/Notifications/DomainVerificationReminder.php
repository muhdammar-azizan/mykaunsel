<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Asks the registering admin to finish DNS verification. Lists the exact TXT record for each pending domain. */
class DomainVerificationReminder extends Notification
{
    /** @param  Collection<int, \App\Models\OrgDomain>  $domains  the domains still unverified */
    public function __construct(private Organization $organization, private Collection $domains) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Action needed: verify your domain for {$this->organization->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("We can't approve {$this->organization->name} until the domain below is verified. Add this DNS TXT record at your domain provider:");

        foreach ($this->domains as $domain) {
            $mail->line("Domain: {$domain->domain}")
                ->line("TXT value: {$domain->verification_token}");
        }

        return $mail
            ->line('DNS changes can take a few minutes to a few hours to appear. We re-check automatically, or you can check yourself:')
            ->action('Open domain verification', route('organizations.signup.verify-domain', $this->organization))
            ->line('Need help? Just reply to this email.');
    }
}
