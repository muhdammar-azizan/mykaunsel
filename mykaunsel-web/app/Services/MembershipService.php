<?php

namespace App\Services;

use App\Enums\JoinSource;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class MembershipService
{
    public function __construct(private DomainVerificationService $domains) {}

    /**
     * Join an organization because your email belongs to one of its domains.
     *
     * Only works when the email is verified AND matches a DNS-verified domain of this (active)
     * organization. Anything else is refused — never fall back to an unverified domain.
     *
     * @throws AuthorizationException
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

    public function joinViaInviteCode(User $user, string $code): void
    {
        //
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
