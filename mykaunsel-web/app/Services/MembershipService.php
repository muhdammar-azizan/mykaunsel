<?php

namespace App\Services;

use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Exceptions\MembershipRefused;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\OrgMemberInvitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberWaitingApproval;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MembershipService
{
    /** The roles of a person who USES the service. Counselors and admins are staff of an organization and may be in several. */
    public const CLIENT_ROLES = [MembershipRole::Student, MembershipRole::Staff, MembershipRole::Employee];

    /** A membership in one of these still holds the person's single place. Alumni and offboarded ones have let it go. */
    private const HOLDING = [MembershipStatus::Active, MembershipStatus::PendingApproval, MembershipStatus::NoticePeriod, MembershipStatus::Suspended];

    public function __construct(private DomainVerificationService $domains) {}

    /**
     * The one organization a client belongs to (or has asked to join), if any. A client can belong to only ONE at a time; the server
     * enforces this, not the app.
     */
    public function heldBy(User $user): ?Membership
    {
        return Membership::query()
            ->where('user_id', $user->getKey())
            ->whereIn('role', array_map(fn ($r) => $r->value, self::CLIENT_ROLES))
            ->whereIn('status', array_map(fn ($s) => $s->value, self::HOLDING))
            ->with('organization:id,name')
            ->first();
    }

    /**
     * Join an organization because your email belongs to one of its domains.
     *
     * Only works when the email is verified AND matches a DNS-verified domain of this (active)
     * organization. Anything else is refused — never fall back to an unverified domain.
     *
     * @throws AuthorizationException
     * @throws MembershipRefused when the person already belongs to another organization
     */
    public function joinViaDomain(User $user, Organization $organization): Membership
    {
        if ($user->email_verified_at === null) {
            throw new AuthorizationException('Verify your email address before joining with it.');
        }

        $domain = $this->domains->verifiedDomainFor($user->email);

        if ($domain === null || $domain->org_id !== $organization->id) {
            throw new AuthorizationException('Your email does not match a verified domain of this organization.');
        }

        if (in_array($domain->default_role, self::CLIENT_ROLES, true)) {
            $held = $this->heldBy($user);

            if ($held !== null && $held->org_id !== $organization->id) {
                throw MembershipRefused::inOtherOrganization($held->organization->name);
            }
        }

        return Membership::firstOrCreate(
            ['user_id' => $user->id, 'org_id' => $organization->id],
            [
                'role' => $domain->default_role,
                'status' => MembershipStatus::Active,
                'join_source' => JoinSource::Domain,
                'joined_at' => now(),
            ],
        );
    }

    /**
     * Ask to join an organization with its reference code. The person becomes an EMPLOYEE, waiting for the organization's approval
     * (no access until then). Everything happens under a lock, so two people can not use the last place of a code, and one person can
     * not join two organizations at once.
     *
     * @throws MembershipRefused
     */
    public function joinViaInviteCode(User $user, string $code): Membership
    {
        if ($user->email_verified_at === null) {
            throw MembershipRefused::emailNotVerified();
        }

        $normalized = OrgInviteCode::normalize($code);

        $membership = DB::transaction(function () use ($user, $normalized) {
            $invite = OrgInviteCode::query()->where('code', $normalized)->lockForUpdate()->first();
            $organization = $invite?->organization;

            if ($invite === null || ! $invite->isUsable() || $organization?->subscription_status !== OrgStatus::Active || $organization->isPlatform()) {
                throw MembershipRefused::invalidCode();
            }

            $membership = $this->placeAsEmployee($user, $organization, MembershipStatus::PendingApproval, JoinSource::InviteCode);

            $invite->increment('used_count');

            Audit::log('member.join_requested', $organization, ['membership_id' => $membership->getKey(), 'code_id' => $invite->getKey()], $user);

            return $membership;
        });

        $this->tellAdminsSomeoneIsWaiting($membership->organization);

        return $membership;
    }

    /** Minutes during which one e-mail to the admins covers everyone who asks (a code handed to a whole team would otherwise send dozens). */
    public const WAITING_MAIL_MINUTES = 10;

    /** The organization's admins, active ones only. */
    public function admins(Organization $organization): \Illuminate\Support\Collection
    {
        return User::query()->whereIn('id', Membership::query()
            ->where('org_id', $organization->getKey())->where('role', MembershipRole::OrgAdmin->value)->where('status', MembershipStatus::Active->value)
            ->pluck('user_id'))->get();
    }

    /** After the request is saved; never able to undo or fail it. */
    private function tellAdminsSomeoneIsWaiting(Organization $organization): void
    {
        try {
            if (! Cache::add('org-waiting-mail:'.$organization->getKey(), true, now()->addMinutes(self::WAITING_MAIL_MINUTES))) {
                return;
            }

            $waiting = Membership::query()->where('org_id', $organization->getKey())->where('status', MembershipStatus::PendingApproval->value)->count();

            foreach ($this->admins($organization) as $admin) {
                $admin->notify(new MemberWaitingApproval($organization->name, $waiting));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The invitations waiting for this person: those sent to their CONFIRMED e-mail address, still open, from an active organization.
     *
     * @return \Illuminate\Support\Collection<int, OrgMemberInvitation>
     */
    public function invitationsFor(User $user): \Illuminate\Support\Collection
    {
        if ($user->email_verified_at === null) {
            return collect();
        }

        return OrgMemberInvitation::query()
            ->where('email', OrgMemberInvitation::normalize($user->email))
            ->whereNull('accepted_at')->whereNull('declined_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->whereHas('organization', fn ($q) => $q->where('subscription_status', OrgStatus::Active->value))
            ->with('organization:id,name')->orderBy('id')->get();
    }

    /**
     * Accepts an invitation: the person becomes an employee AT ONCE (the organization already said yes). The one-organization rule applies.
     *
     * @throws MembershipRefused
     */
    public function acceptInvitation(User $user, string $publicId): Membership
    {
        return DB::transaction(function () use ($user, $publicId) {
            $invitation = $this->ownInvitation($user, $publicId);
            $membership = $this->placeAsEmployee($user, $invitation->organization, MembershipStatus::Active, JoinSource::Invitation);

            $invitation->update(['accepted_at' => now()]);
            Audit::log('member.invitation_accepted', $invitation->organization, ['membership_id' => $membership->getKey(), 'invitation_id' => $invitation->getKey()], $user);

            return $membership;
        });
    }

    /** @throws MembershipRefused */
    public function declineInvitation(User $user, string $publicId): void
    {
        DB::transaction(function () use ($user, $publicId) {
            $invitation = $this->ownInvitation($user, $publicId);

            $invitation->update(['declined_at' => now()]);
            Audit::log('member.invitation_declined', $invitation->organization, ['invitation_id' => $invitation->getKey()], $user);
        });
    }

    /** Someone else's invitation, a closed one, and an unknown one all look the same. */
    private function ownInvitation(User $user, string $publicId): OrgMemberInvitation
    {
        $invitation = OrgMemberInvitation::query()->where('public_id', strtolower($publicId))->lockForUpdate()->first();
        $organization = $invitation?->organization;

        if (
            $user->email_verified_at === null || $invitation === null || ! $invitation->isOpen()
            || $invitation->email !== OrgMemberInvitation::normalize($user->email)
            || $organization?->subscription_status !== OrgStatus::Active
        ) {
            throw MembershipRefused::invitationNotFound();
        }

        return $invitation;
    }

    /**
     * Gives the person their one place in the organization as an employee. Under a lock on the person, so they can not join two at once. An
     * old row of a former member is reused; a staff row (counselor, admin) is never turned into an employee one.
     *
     * @throws MembershipRefused
     */
    private function placeAsEmployee(User $user, Organization $organization, MembershipStatus $status, JoinSource $source): Membership
    {
        // One person at a time: the user's row is the lock for "which organization am I joining".
        User::query()->whereKey($user->getKey())->lockForUpdate()->first();

        $held = $this->heldBy($user);

        // Someone waiting for approval who is then INVITED by the same organization needs no approval any more: the organization has said yes.
        if ($held !== null && $held->org_id === $organization->id && $held->status === MembershipStatus::PendingApproval && $status === MembershipStatus::Active) {
            return tap($held)->update(['status' => MembershipStatus::Active, 'join_source' => $source, 'joined_at' => now()]);
        }

        if ($held !== null) {
            throw match (true) {
                $held->org_id !== $organization->id => MembershipRefused::inOtherOrganization($held->organization->name),
                $held->status === MembershipStatus::PendingApproval => MembershipRefused::waitingApproval(),
                default => MembershipRefused::alreadyMember(),
            };
        }

        $existing = Membership::query()->where('user_id', $user->getKey())->where('org_id', $organization->id)->lockForUpdate()->first();

        if ($existing !== null && ! in_array($existing->role, self::CLIENT_ROLES, true)) {
            throw MembershipRefused::alreadyMember();
        }

        $fresh = [
            'role' => MembershipRole::Employee,
            'status' => $status,
            'join_source' => $source,
            'joined_at' => now(),
            'notice_started_at' => null,
            'offboarded_at' => null,
            'offboard_reason' => null,
        ];

        return $existing !== null
            ? tap($existing)->update($fresh)
            : Membership::create(['user_id' => $user->getKey(), 'org_id' => $organization->id] + $fresh);
    }

    public function joinViaMemberList(User $user, Organization $organization): void
    {
        //
    }

    public function offboard(User $user, Organization $organization, string $reason): void
    {
        //
    }
}
