<?php

namespace App\Services;

use App\Enums\AdminInvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Exceptions\AdminChangeBlocked;
use App\Exceptions\InvitationPasswordRejected;
use App\Exceptions\InvitationUnavailable;
use App\Facades\Audit;
use App\Models\AdminInvitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformAdminInvitation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Invitations to become a Platform Admin.
 *
 * - Inviting creates an invitation and emails a signed link. NOTHING about the person changes until they accept: no
 *   account, no role. The token is random (256 bits), stored only as a SHA-256 hash, tied to the invited email and
 *   works once, for `platform-admins.invitation.expires_hours` hours.
 * - One live invitation per email. "Send again" gives the SAME invitation a new token (the old link stops working at once)
 *   and a new time limit; "Revoke" cancels it at once.
 * - Accepting (see accept()) depends on the email:
 *     no account                → the person chooses a password and the account is created;
 *     account, email verified   → the person confirms the account's own password (and, if the account has two-factor, is
 *                                 signed in first);
 *     account, email NOT verified → the only way is to set a NEW password: the old one stops working, every session of
 *                                 the account is ended and the email counts as verified through this link.
 * - Every change and its audit entry share one transaction; emails go out only after the commit.
 *
 * Audit entries (target = the invitation, by number: no email, no name): admin.invited, admin.invite_resent,
 * admin.invite_revoked, admin.invite_accepted (target = the new admin).
 */
class AdminInvitationService
{
    public function __construct(private AccountSessionEnder $sessions, private NotificationCenter $inbox) {}

    // ---- sending ---------------------------------------------------------------------------------------------

    /**
     * @throws AdminChangeBlocked
     */
    public function invite(User $actor, string $name, string $email): AdminInvitation
    {
        $email = Str::lower(trim($email));
        $limitKey = 'admin-invite:'.$actor->getKey();

        if (RateLimiter::tooManyAttempts($limitKey, (int) config('platform-admins.invitation.new_per_admin_per_hour'))) {
            throw new AdminChangeBlocked(['You have sent a lot of invitations in the last hour. Please try again later.']);
        }

        $plain = $this->newToken();

        try {
            $invitation = DB::transaction(function () use ($actor, $name, $email, $plain) {
                $platform = $this->platformOrganization();
                $user = User::query()->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();

                if ($user && $this->isActiveAdmin($user, $platform)) {
                    throw new AdminChangeBlocked(["{$user->name} is already a Platform Admin."]);
                }

                $existing = AdminInvitation::query()->where('active_email', $email)->lockForUpdate()->first();

                if ($existing?->isLive()) {
                    throw new AdminChangeBlocked(["There is already an invitation waiting for {$email}. Use \"Send again\" on the Invitations tab."]);
                }

                // A pending invitation past its time makes way for the new one.
                $existing?->update(['status' => AdminInvitationStatus::Expired->value, 'active_email' => null, 'superseded_at' => now()]);

                $invitation = AdminInvitation::create([
                    'name' => trim($name),
                    'email' => $email,
                    'token_hash' => $this->hash($plain),
                    'active_email' => $email,
                    'status' => AdminInvitationStatus::Pending->value,
                    'invited_by_user_id' => $actor->getKey(),
                    'expires_at' => now()->addHours($this->hours()),
                    'last_sent_at' => now(),
                    'sent_count' => 1,
                ]);

                Audit::log('admin.invited', $invitation, ['summary' => 'Invitation sent', 'expires_hours' => $this->hours()], $actor);

                return $invitation;
            });
        } catch (UniqueConstraintViolationException) {
            // Another admin invited the same address at the same moment.
            throw new AdminChangeBlocked(['Someone just invited this email. Reload the page to see it.']);
        }

        RateLimiter::hit($limitKey, 3600);
        $this->sendEmail($invitation, $plain, $actor);

        return $invitation;
    }

    /**
     * A new link for a waiting invitation (also one that ran out). The old link stops working at once.
     *
     * @throws AdminChangeBlocked
     */
    public function resend(User $actor, AdminInvitation $invitation): AdminInvitation
    {
        $plain = $this->newToken();

        $fresh = DB::transaction(function () use ($actor, $invitation, $plain) {
            $locked = AdminInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->isErased()) {
                throw new AdminChangeBlocked(['This old invitation no longer holds the name and email it was sent to, so it cannot be sent again. Invite the person again.']);
            }

            if ($locked->status !== AdminInvitationStatus::Pending) {
                throw new AdminChangeBlocked(['This invitation is '.$locked->status->value.', so it cannot be sent again. Invite the person again if needed.']);
            }

            $max = (int) config('platform-admins.invitation.max_sends');

            if ($locked->sent_count >= $max) {
                throw new AdminChangeBlocked(["This invitation has already been sent {$max} times. Revoke it and invite the person again if you still need to."]);
            }

            $wait = $locked->last_sent_at->copy()->addSeconds((int) config('platform-admins.invitation.resend_cooldown_seconds'));

            if ($wait->isFuture()) {
                throw new AdminChangeBlocked(['Please wait about '.max(1, (int) ceil(now()->diffInSeconds($wait, true))).' seconds before sending it again.']);
            }

            $locked->update([
                'token_hash' => $this->hash($plain),
                'expires_at' => now()->addHours($this->hours()),
                'last_sent_at' => now(),
                'sent_count' => $locked->sent_count + 1,
                'invited_by_user_id' => $actor->getKey(),
            ]);

            Audit::log('admin.invite_resent', $locked, ['summary' => 'Invitation sent again', 'send_number' => $locked->sent_count], $actor);

            return $locked;
        });

        $this->sendEmail($fresh, $plain, $actor);

        return $fresh;
    }

    /** @throws AdminChangeBlocked */
    public function revoke(User $actor, AdminInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation) {
            $locked = AdminInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->isErased()) {
                throw new AdminChangeBlocked(['This old invitation has run out and its details were erased. There is nothing to revoke.']);
            }

            if ($locked->status !== AdminInvitationStatus::Pending) {
                throw new AdminChangeBlocked(['Only an invitation that is waiting can be revoked. This one is '.$locked->status->value.'.']);
            }

            $this->revokeRow($locked, ['summary' => 'Invitation revoked'], $actor);
        });
    }

    /**
     * Cancel every waiting invitation a Platform Admin sent (used when they are removed: a link they sent must not outlive
     * their access). Call inside the transaction that removes them.
     */
    public function revokeSentBy(User $sender): int
    {
        $waiting = AdminInvitation::query()
            ->where('status', AdminInvitationStatus::Pending->value)
            ->where('invited_by_user_id', $sender->getKey())
            ->lockForUpdate()
            ->get();

        foreach ($waiting as $invitation) {
            $this->revokeRow($invitation, ['summary' => 'Revoked because the admin who sent it was removed', 'cause' => 'sender_removed']);
        }

        return $waiting->count();
    }

    // ---- the link --------------------------------------------------------------------------------------------

    /** The invitation behind a link's token, or null when the link cannot be used (whatever the reason). */
    public function findUsable(string $token): ?AdminInvitation
    {
        $invitation = AdminInvitation::query()->where('token_hash', $this->hash($token))->first();

        return $invitation && $invitation->isLive() && $this->senderIsActiveAdmin($invitation) ? $invitation : null;
    }

    /** @return 'new'|'verified'|'unverified' */
    public function modeOf(AdminInvitation $invitation): string
    {
        $user = $this->accountFor($invitation);

        return match (true) {
            $user === null => 'new',
            $user->hasVerifiedEmail() => 'verified',
            default => 'unverified',
        };
    }

    public function accountFor(AdminInvitation $invitation): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])->first();
    }

    /** An account with two-factor must be signed in (so the second factor was asked) before it can accept. */
    public function mustSignInFirst(AdminInvitation $invitation, ?User $signedInAs): bool
    {
        $user = $this->accountFor($invitation);

        return $user !== null && $user->hasVerifiedEmail() && $user->hasTwoFactorEnabled() && $signedInAs?->getKey() !== $user->getKey();
    }

    /**
     * Accept the invitation behind $token. $password is the new password (no account, or email not verified) or the
     * account's own password (email verified).
     *
     * @throws InvitationUnavailable the link cannot be used any more
     * @throws InvitationPasswordRejected the account's password was wrong
     * @throws AdminChangeBlocked sign in first (two-factor)
     */
    public function accept(string $token, string $password, ?User $signedInAs = null): User
    {
        return DB::transaction(function () use ($token, $password, $signedInAs) {
            $invitation = AdminInvitation::query()->where('token_hash', $this->hash($token))->lockForUpdate()->first();

            if ($invitation === null || ! $invitation->isLive() || ! $this->senderIsActiveAdmin($invitation)) {
                throw new InvitationUnavailable;
            }

            $platform = $this->platformOrganization();
            $user = User::query()->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])->lockForUpdate()->first();

            if ($user === null) {
                $how = 'new_account';
                $user = new User(['name' => $invitation->name, 'email' => Str::lower($invitation->email), 'password' => $password]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } elseif ($user->hasVerifiedEmail()) {
                $how = 'existing_account';

                if ($this->mustSignInFirst($invitation, $signedInAs)) {
                    throw new AdminChangeBlocked(['This account uses two-factor authentication. Sign in first, then open the link from the email again.']);
                }

                if (! is_string($user->password) || ! Hash::check($password, $user->password)) {
                    throw new InvitationPasswordRejected;
                }
            } else {
                // Nobody has proved this mailbox is theirs yet, so whoever knew the old password must not keep the account.
                $how = 'unverified_account_new_password';
                $user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $this->sessions->endAll($user);
            }

            $membership = Membership::query()->where('user_id', $user->getKey())->where('org_id', $platform->getKey())->lockForUpdate()->first();

            if ($membership?->status === MembershipStatus::Active && $membership->role === MembershipRole::PlatformAdmin) {
                throw new InvitationUnavailable;
            }

            if ($membership) {
                $membership->update([
                    'role' => MembershipRole::PlatformAdmin->value,
                    'status' => MembershipStatus::Active->value,
                    'offboarded_at' => null,
                    'offboard_reason' => null,
                ]);
            } else {
                Membership::create([
                    'user_id' => $user->getKey(),
                    'org_id' => $platform->getKey(),
                    'role' => MembershipRole::PlatformAdmin->value,
                    'status' => MembershipStatus::Active->value,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update([
                'status' => AdminInvitationStatus::Accepted->value,
                'active_email' => null,
                'accepted_at' => now(),
                'accepted_user_id' => $user->getKey(),
            ]);

            // After the membership exists, so the entry keeps the new admin's name like other staff entries do.
            Audit::log('admin.invite_accepted', $user, ['invitation_id' => $invitation->getKey(), 'how' => $how, 'summary' => 'Invitation accepted'], $user);

            // The other active admins are told; the person who just joined is not (they did it themselves).
            DB::afterCommit(fn () => $this->inbox->publish('admin.invitation_accepted', $user, $user->name, (string) $invitation->getKey(), $user));

            return $user;
        });
    }

    // ---- internals -------------------------------------------------------------------------------------------

    private function revokeRow(AdminInvitation $invitation, array $metadata, ?User $actor = null): void
    {
        $invitation->update(['status' => AdminInvitationStatus::Revoked->value, 'active_email' => null, 'revoked_at' => now()]);

        Audit::log('admin.invite_revoked', $invitation, $metadata, $actor);
    }

    /** After the commit and queued; a mail problem never undoes the invitation. */
    private function sendEmail(AdminInvitation $invitation, string $plainToken, User $sender): void
    {
        DB::afterCommit(function () use ($invitation, $plainToken, $sender) {
            try {
                $url = URL::temporarySignedRoute('admin-invitation.show', $invitation->expires_at, ['token' => $plainToken]);

                Notification::route('mail', $invitation->email)->notify(new PlatformAdminInvitation($sender->name, $url, $invitation->expires_at));
            } catch (Throwable $e) {
                Log::warning('Could not email a Platform Admin invitation.', ['invitation_id' => $invitation->getKey(), 'error' => $e->getMessage()]);
            }
        });
    }

    private function platformOrganization(): Organization
    {
        return Organization::query()->where('org_type', OrgType::Platform->value)->firstOrFail();
    }

    private function isActiveAdmin(User $user, Organization $platform): bool
    {
        return Membership::query()
            ->where('user_id', $user->getKey())
            ->where('org_id', $platform->getKey())
            ->where('role', MembershipRole::PlatformAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }

    /** A link only works while the admin who sent it still has access. */
    private function senderIsActiveAdmin(AdminInvitation $invitation): bool
    {
        return $invitation->invited_by_user_id !== null
            && Membership::query()
                ->where('user_id', $invitation->invited_by_user_id)
                ->where('role', MembershipRole::PlatformAdmin->value)
                ->where('status', MembershipStatus::Active->value)
                ->exists();
    }

    private function hours(): int
    {
        return max(1, (int) config('platform-admins.invitation.expires_hours'));
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
