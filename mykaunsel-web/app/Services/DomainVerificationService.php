<?php

namespace App\Services;

use App\Enums\DomainMatchType;
use App\Models\OrgDomain;
use Illuminate\Support\Str;

class DomainVerificationService
{
    public function generateToken(OrgDomain $orgDomain): string
    {
        $token = 'mykaunsel-verify='.Str::random(32);

        $orgDomain->update(['verification_token' => $token]);

        return $token;
    }

    public function checkDns(OrgDomain $orgDomain): bool
    {
        $verified = $orgDomain->verification_token !== null
            && $this->tokenPresentInDns($orgDomain);

        $orgDomain->update([
            'dns_verified' => $verified,
            'verified_at' => $verified ? now() : $orgDomain->verified_at,
            'last_checked_at' => now(),
            'check_attempts' => $orgDomain->check_attempts + 1,
        ]);

        return $verified;
    }

    public function matchesEmail(string $email, OrgDomain $orgDomain): bool
    {
        $emailDomain = Str::lower(Str::after($email, '@'));
        $orgDomainName = Str::lower($orgDomain->domain);

        if ($orgDomain->match_type === DomainMatchType::Wildcard) {
            return $emailDomain === $orgDomainName
                || Str::endsWith($emailDomain, '.'.$orgDomainName);
        }

        return $emailDomain === $orgDomainName;
    }

    private function tokenPresentInDns(OrgDomain $orgDomain): bool
    {
        $records = @dns_get_record($orgDomain->domain, DNS_TXT) ?: [];

        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $orgDomain->verification_token) {
                return true;
            }
        }

        return false;
    }
}
