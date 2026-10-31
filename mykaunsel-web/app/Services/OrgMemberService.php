<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipNotPending;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\OrgMemberInvitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberInvitation;
use App\Notifications\MembershipApproved;
use App\Notifications\MembershipDeclined;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * What an organization's admin (HR) does with reference codes and with people who asked to join with one. Every change runs under a lock on the
 * row, so two admins can not both answer one request. The person is told by e-mail AFTER the change is saved; a failing mail never undoes it.
 */
class OrgMemberService
{
    /** Switched-on codes per organization: enough for every team, too few to lose track of. */
    public const MAX_OPEN_CODES = 20;

    /** Invitations waiting for an answer at one time. */
    public const MAX_OPEN_INVITATIONS = 100;

    public function createCode(Organization $organization, User $admin, ?int $maxUses, ?Carbon $expiresAt): OrgInviteCode
    {
        return DB::transaction(function () use ($organization, $admin, $maxUses, $expiresAt) {
            // The organization row is the lock for "how many open codes does it have".
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            $open = OrgInviteCode::query()->where('org_id', $organization->getKey())->get()->filter->isUsable()->count();

            if ($open >= self::MAX_OPEN_CODES) {
                throw new RuntimeException('You already have '.self::MAX_OPEN_CODES.' codes switched on. Switch one off first.');
            }

            $code = OrgInviteCode::issue($organization, $admin, $maxUses, $expiresAt);

            Audit::log('member.code_created', $organization, ['code_id' => $code->getKey(), 'max_uses' => $maxUses], $admin);

            return $code;
        });
    }

    /**
     * Invites an e-mail address. The person answers in the app, with that address confirmed. Refused (in plain words) for someone who already
     * belongs here or was already invited. Whether they belong to ANOTHER organization is only checked when they accept.
     */
    public function invite(Organization $organization, User $admin, string $email): OrgMemberInvitation
    {
        $email = OrgMemberInvitation::normalize($email);

        $invitation = DB::transaction(function () use ($organization, $admin, $email) {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            $belongs = Membership::query()->where('org_id', $organization->getKey())
                ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::PendingApproval->value, MembershipStatus::NoticePeriod->value, MembershipStatus::Suspended->value])
                ->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [$email]))->exists();

            if ($belongs) {
                throw new RuntimeException('This person already belongs to your organization.');
            }

            $open = OrgMemberInvitation::query()->where('org_id', $organization->getKey())->get()->filter->isOpen();

            if ($open->contains(fn (OrgMemberInvitation $i) => $i->email === $email)) {
                throw new RuntimeException('This address has already been invited. Cancel that invitation first if you want to send a new one.');
            }

            if ($open->count() >= self::MAX_OPEN_INVITATIONS) {
                throw new RuntimeException('You have '.self::MAX_OPEN_INVITATIONS.' invitations waiting. Cancel some first.');
            }

            $invitation = OrgMemberInvitation::create([
                'org_id' => $organization->getKey(),
                'email' => $email,
                'invited_by_user_id' => $admin->getKey(),
                'expires_at' => now()->addDays(OrgMemberInvitation::VALID_DAYS),
            ]);

            Audit::log('member.invited', $organization, ['invitation_id' => $invitation->getKey()], $admin);

            return $invitation;
        });

        try {
            Notification::route('mail', $email)->notify(new MemberInvitation($organization->name));
        } catch (Throwable $e) {
            report($e);
        }

        return $invitation;
    }

    /** Cancels an invitation that is still waiting. Safe to repeat. */
    public function revokeInvitation(Organization $organization, int $invitationId, User $admin): void
    {
        DB::transaction(function () use ($organization, $invitationId, $admin) {
            $invitation = OrgMemberInvitation::query()->where('org_id', $organization->getKey())->lockForUpdate()->findOrFail($invitationId);

            if (! $invitation->isOpen()) {
                return;
            }

            $invitation->update(['revoked_at' => now()]);
            Audit::log('member.invitation_revoked', $organization, ['invitation_id' => $invitation->getKey()], $admin);
        });
    }

    /** Switches a code off. People already waiting stay waiting; nobody new can use it. Safe to repeat. */
    public function revokeCode(Organization $organization, int $codeId, User $admin): void
    {
        DB::transaction(function () use ($organization, $codeId, $admin) {
            $code = OrgInviteCode::query()->where('org_id', $organization->getKey())->lockForUpdate()->findOrFail($codeId);

            if ($code->revoked_at !== null) {
                return;
            }

            $code->update(['revoked_at' => now()]);

            Audit::log('member.code_revoked', $organization, ['code_id' => $code->getKey()], $admin);
        });
    }

    /** The person gets their place (as an employee) and can book the organization's counselors from now on. */
    public function approve(Organization $organization, int $membershipId, User $admin): void
    {
        $person = $this->answer($organization, $membershipId, $admin, MembershipStatus::Active, 'member.join_approved', ['joined_at' => now()]);

        $this->tell($person, new MembershipApproved($organization->name));
    }

    /** The request is closed and the person's place is free again. They may ask again later. */
    public function decline(Organization $organization, int $membershipId, User $admin): void
    {
        $person = $this->answer($organization, $membershipId, $admin, MembershipStatus::Offboarded, 'member.join_declined', [
            'offboarded_at' => now(),
            'offboard_reason' => 'Request declined by the organization',
        ]);

        $this->tell($person, new MembershipDeclined($organization->name));
    }

    /** @param  array<string, mixed>  $changes */
    private function answer(Organization $organization, int $membershipId, User $admin, MembershipStatus $to, string $action, array $changes): User
    {
        return DB::transaction(function () use ($organization, $membershipId, $admin, $to, $action, $changes) {
            $membership = Membership::query()->where('org_id', $organization->getKey())->lockForUpdate()->findOrFail($membershipId);

            if ($membership->status !== MembershipStatus::PendingApproval) {
                throw new MembershipNotPending;
            }

            $membership->update(['status' => $to] + $changes);

            $person = $membership->user;
            Audit::log($action, $person, ['membership_id' => $membership->getKey(), 'org_id' => $organization->getKey()], $admin);

            return $person;
        });
    }

    private function tell(User $person, object $notification): void
    {
        try {
            $person->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
