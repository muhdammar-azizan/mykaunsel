<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Exceptions\AdminChangeBlocked;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformAdminInvited;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Who is a Platform Admin: adding, re-sending the invite and removing.
 *
 * An admin is a membership with the platform_admin role in the Platform organization. Removing keeps the row
 * (status offboarded, with the date), so the history stays and the person can be added again later.
 * Every change and its audit entry share one transaction; emails go out only after the commit.
 */
class PlatformAdminService
{
    public function platformOrganization(): Organization
    {
        return Organization::query()->where('org_type', OrgType::Platform->value)->firstOrFail();
    }

    /**
     * Add a Platform Admin by email. A new email gets a new account (with a link to choose its password); an existing
     * account (for example a counselor) is given the role as well and keeps its own password.
     *
     * @return array{user: User, outcome: 'created'|'existing'|'restored'}
     *
     * @throws AdminChangeBlocked
     */
    public function invite(User $actor, string $name, string $email): array
    {
        $email = Str::lower(trim($email));
        $platform = $this->platformOrganization();
        $setPasswordUrl = null;

        try {
            $result = $this->addAdmin($actor, $name, $email, $platform, $setPasswordUrl);
        } catch (UniqueConstraintViolationException) {
            // Another admin added the same new email at the same moment.
            throw new AdminChangeBlocked(['Someone just added this email. Reload the page to see it.']);
        }

        $this->sendInvite($result['user'], $actor, $setPasswordUrl);

        return $result;
    }

    /** @return array{user: User, outcome: 'created'|'existing'|'restored'} */
    private function addAdmin(User $actor, string $name, string $email, Organization $platform, ?string &$setPasswordUrl): array
    {
        return DB::transaction(function () use ($actor, $name, $email, $platform, &$setPasswordUrl) {
            $user = User::query()->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();
            $outcome = 'existing';

            if ($user === null) {
                $user = new User(['name' => trim($name), 'email' => $email, 'password' => Str::random(48)]);
                // The person who adds them vouches for the address; the emailed link proves the mailbox is theirs.
                $user->forceFill(['email_verified_at' => now()])->save();
                $outcome = 'created';
            }

            $membership = Membership::query()->where('user_id', $user->id)->where('org_id', $platform->id)->lockForUpdate()->first();

            if ($membership?->status === MembershipStatus::Active && $membership->role === MembershipRole::PlatformAdmin) {
                throw new AdminChangeBlocked(["{$user->name} is already a Platform Admin."]);
            }

            if ($membership) {
                $membership->update([
                    'role' => MembershipRole::PlatformAdmin->value,
                    'status' => MembershipStatus::Active->value,
                    'offboarded_at' => null,
                    'offboard_reason' => null,
                ]);
                $outcome = $outcome === 'created' ? 'created' : 'restored';
            } else {
                Membership::create([
                    'user_id' => $user->id,
                    'org_id' => $platform->id,
                    'role' => MembershipRole::PlatformAdmin->value,
                    'status' => MembershipStatus::Active->value,
                    'joined_at' => now(),
                ]);
            }

            Audit::log('admin.invited', $user, [
                'summary' => match ($outcome) {
                    'created' => 'New account created and invited',
                    'restored' => 'Platform Admin access given again',
                    default => 'Existing account given Platform Admin access',
                },
            ], $actor);

            if ($outcome === 'created') {
                $setPasswordUrl = $this->setPasswordUrl($user);
            }

            return ['user' => $user, 'outcome' => $outcome];
        });
    }

    /**
     * A new link to choose a password, for an admin who has never signed in.
     *
     * @throws AdminChangeBlocked
     */
    public function resendInvite(User $actor, User $admin): void
    {
        $url = DB::transaction(function () use ($actor, $admin) {
            $membership = $this->membershipOf($admin, lock: true);

            if ($membership?->status !== MembershipStatus::Active) {
                throw new AdminChangeBlocked(["{$admin->name} is no longer a Platform Admin."]);
            }

            if ($admin->fresh()->last_login_at !== null) {
                throw new AdminChangeBlocked(["{$admin->name} has already signed in. They can use \"Forgot password\" on the sign-in page if they need a new one."]);
            }

            Audit::log('admin.invited', $admin, ['summary' => 'Invite sent again'], $actor);

            return $this->setPasswordUrl($admin);
        });

        $this->sendInvite($admin, $actor, $url);
    }

    /**
     * Take Platform Admin access away. Never yourself, and never the last active admin (two admins removing each other
     * at the same time cannot both succeed: the admin rows are locked while the count is checked).
     *
     * @throws AdminChangeBlocked
     */
    public function remove(User $actor, User $admin, string $reason): void
    {
        DB::transaction(function () use ($actor, $admin, $reason) {
            $platform = $this->platformOrganization();

            $admins = Membership::query()
                ->where('org_id', $platform->id)
                ->where('role', MembershipRole::PlatformAdmin->value)
                ->lockForUpdate()
                ->get();

            $membership = $admins->firstWhere('user_id', $admin->id);

            if ($membership === null || $membership->status !== MembershipStatus::Active) {
                throw new AdminChangeBlocked(["{$admin->name} is no longer a Platform Admin."]);
            }

            if ($admin->is($actor)) {
                throw new AdminChangeBlocked(['You cannot remove yourself. Ask another Platform Admin to do it.']);
            }

            if ($admins->where('status', MembershipStatus::Active)->count() <= 1) {
                throw new AdminChangeBlocked(['This is the last active Platform Admin, so they cannot be removed.']);
            }

            // Logged first: while they are still active the entry keeps their name (non-staff people are stored by id only).
            Audit::log('admin.removed', $admin, ['reason' => $reason], $actor);

            $membership->update([
                'status' => MembershipStatus::Offboarded->value,
                'offboarded_at' => now(),
                'offboard_reason' => $reason,
            ]);
        });
    }

    private function membershipOf(User $admin, bool $lock = false): ?Membership
    {
        $query = Membership::query()
            ->where('user_id', $admin->id)
            ->where('org_id', $this->platformOrganization()->id)
            ->where('role', MembershipRole::PlatformAdmin->value);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function setPasswordUrl(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('password.reset', ['token' => $token, 'email' => $user->email]);
    }

    /** After the commit; a mail problem never undoes the change. */
    private function sendInvite(User $user, User $actor, ?string $setPasswordUrl): void
    {
        DB::afterCommit(function () use ($user, $actor, $setPasswordUrl) {
            try {
                $user->notify(new PlatformAdminInvited($actor->name, $setPasswordUrl, (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)));
            } catch (Throwable $e) {
                Log::warning('Could not email a new Platform Admin.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
