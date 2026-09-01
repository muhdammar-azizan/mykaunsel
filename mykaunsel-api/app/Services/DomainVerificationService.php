<?php

namespace App\Services;

use App\Models\OrgDomain;

class DomainVerificationService
{
    public function generateToken(OrgDomain $orgDomain): string
    {
        //
    }

    public function checkDns(OrgDomain $orgDomain): bool
    {
        //
    }

    public function matchesEmail(string $email, OrgDomain $orgDomain): bool
    {
        //
    }
}
