<?php

namespace Tests\Feature\Platform;

use App\Models\AdminInvitation;
use App\Models\User;
use App\Notifications\PlatformAdminInvitation;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by the Platform Admin tests: send an invitation as the signed-in admin, find the link that was emailed, and open it
 * as the (signed-out) invited person. Needs CounselorTestCase (`$this->as()`).
 */
trait AdminInvitationHelpers
{
    protected const NEW_PASSWORD = 'Str0ng!Passw0rd';

    /** Invites and returns the invitation and the emailed link. Starts a fresh Notification fake. */
    protected function invite(string $name = 'Nur Aisyah', string $email = 'aisyah@mykaunsel.com'): array
    {
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => $name, 'email' => $email])->assertSessionHasNoErrors();

        return [AdminInvitation::where('email', strtolower($email))->orderByDesc('id')->firstOrFail(), $this->emailedLink()];
    }

    /** The link in the most recent invitation email (the fake must be active). */
    protected function emailedLink(): string
    {
        $urls = [];

        Notification::assertSentOnDemand(PlatformAdminInvitation::class, function ($notification, $channels, $notifiable) use (&$urls) {
            $urls[] = $notification->toMail($notifiable)->actionUrl;

            return true;
        });

        return end($urls);
    }

    /** Sign out the admin the test is acting as, so the next request is the invited person's own. */
    protected function asGuest(): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this;
    }

    /** Opens the emailed link as a visitor and accepts with a new password. */
    protected function acceptWithNewPassword(string $url, string $password = self::NEW_PASSWORD)
    {
        return $this->asGuest()->post($url, ['password' => $password, 'password_confirmation' => $password]);
    }

    /** The whole way to a new admin: invited, link opened, password chosen. Leaves the test signed out. */
    protected function addAdmin(string $name = 'Nur Aisyah', string $email = 'aisyah@mykaunsel.com'): User
    {
        [, $url] = $this->invite($name, $email);

        $this->acceptWithNewPassword($url)->assertRedirect(route('login'));

        return User::where('email', strtolower($email))->firstOrFail();
    }
}
