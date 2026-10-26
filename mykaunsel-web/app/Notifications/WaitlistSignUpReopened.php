<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * The one email a waitlist address gets: sign-up is open again. A link to the normal sign-up page for that kind of sign-up
 * (nobody is registered automatically), an opt-out link, and the one-click List-Unsubscribe headers mail programs show as
 * an "Unsubscribe" button. Sent to an address that is not a user (see App\Jobs\SendWaitlistEmail).
 */
class WaitlistSignUpReopened extends Notification
{
    public function __construct(private string $type, private string $unsubscribeUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $word = config("platform-settings.registration_roles.{$this->type}.word", 'account');
        $signUp = $this->type === 'counselor' ? route('counselors.signup.create') : route('organizations.signup.create');
        $url = $this->unsubscribeUrl;

        return (new MailMessage)
            ->subject('MyKaunsel '.$word.' sign-up is open again')
            ->greeting('Hello,')
            ->line("You asked us to let you know when {$word} sign-up on MyKaunsel opens again. It is open now.")
            ->action('Go to sign-up', $signUp)
            ->line('This is the only email we will send you about it. We do not share your email address.')
            ->line("If you no longer want to be on this list, you can opt out here: {$url}")
            ->withSymfonyMessage(function (Email $message) use ($url) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<'.$url.'>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
