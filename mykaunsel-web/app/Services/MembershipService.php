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
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($user, $normalized) {
            $invite = OrgInviteCode::query()->where('code', $normalized)->lockForUpdate()->first();
            $organization = $invite?->organization;

            if ($invite === null || ! $invite->isUsable() || $organization?->subscription_status !== OrgStatus::Active || $organization->isPlatform()) {
                throw MembershipRefused::invalidCode();
            }

            // One person at a time: the user's row is the lock for "which organization am I joining".
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $held = $this->heldBy($user);

            if ($held !== null) {
                throw match (true) {
                    $held->org_id !== $organization->id => MembershipRefused::inOtherOrganization($held->organization->name),
                    $held->status === MembershipStatus::PendingApproval => MembershipRefused::waitingApproval(),
                    default => MembershipRefused::alreadyMember(),
                };
            }

            $existing = Membership::query()->where('user_id', $user->getKey())->where('org_id', $organization->id)->lockForUpdate()->first();

            // An old row of a former member is reused. A staff row (counselor, admin) is never turned into an employee one.
            if ($existing !== null && ! in_array($existing->role, self::CLIENT_ROLES, true)) {
                throw MembershipRefused::alreadyMember();
            }

            $fresh = [
                'role' => MembershipRole::Employee,
                'status' => MembershipStatus::PendingApproval,
                'join_source' => JoinSource::InviteCode,
                'joined_at' => now(),
                'notice_started_at' => null,
                'offboarded_at' => null,
                'offboard_reason' => null,
            ];

            $membership = $existing !== null
                ? tap($existing)->update($fresh)
                : Membership::create(['user_id' => $user->getKey(), 'org_id' => $organization->id] + $fresh);

            $invite->increment('used_count');

            Audit::log('member.join_requested', $organization, ['membership_id' => $membership->getKey(), 'code_id' => $invite->getKey()], $user);

            return $membership;
        });
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
