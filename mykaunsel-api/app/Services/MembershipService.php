<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class MembershipService
{
    public function joinViaDomain(User $user, Organization $organization): void
    {
        //
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
