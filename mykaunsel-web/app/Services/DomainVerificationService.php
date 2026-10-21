<?php

namespace App\Services;

use App\Enums\DomainMatchType;
use App\Enums\OrgStatus;
use App\Models\OrgDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DomainVerificationService
{
    /** How long we keep re-checking an unverified domain automatically. */
    public const CHECK_WINDOW_DAYS = 7;

    public function generateToken(OrgDomain $orgDomain): string
    {
        $token = 'mykaunsel-verify='.Str::random(32);

        $orgDomain->update(['verification_token' => $token]);

        return $token;
    }

    /** Look the record up in DNS. Network only — touches no table, so call it OUTSIDE any database transaction. */
    public function probeDns(OrgDomain $orgDomain): bool
    {
        return $orgDomain->verification_token !== null && $this->tokenPresentInDns($orgDomain);
    }

    /** Lookup + save in one go (fine when no audit entry has to share the transaction). */
    public function checkDns(OrgDomain $orgDomain): bool
    {
        return $this->applyCheckResult($orgDomain, $this->probeDns($orgDomain));
    }

    /** Save the outcome of a lookup. Database only — safe inside the transaction that also writes the audit entry. */
    public function applyCheckResult(OrgDomain $orgDomain, bool $verified): bool
    {
        $orgDomain->update([
            'dns_verified' => $verified,
            'verified_at' => $verified ? now() : $orgDomain->verified_at,
            'last_checked_at' => now(),
            'check_attempts' => $orgDomain->check_attempts + 1,
            // Once verified, an earlier "expired" mark no longer applies.
            'dns_check_expired_at' => $verified ? null : $orgDomain->dns_check_expired_at,
        ]);

        return $verified;
    }

    /** When the automatic checks stop for this domain: 7 days after the application was (re)submitted. */
    public function windowEndsAt(OrgDomain $orgDomain): Carbon
    {
        $start = $orgDomain->organization?->submitted_at ?? $orgDomain->created_at;

        return $start->copy()->addDays(self::CHECK_WINDOW_DAYS);
    }

    /** 'verified', 'pending' (still being checked automatically) or 'expired' (automatic checks stopped). */
    public function state(OrgDomain $orgDomain): string
    {
        if ($orgDomain->dns_verified) {
            return 'verified';
        }

        if ($orgDomain->dns_check_expired_at !== null || $this->windowEndsAt($orgDomain)->isPast()) {
            return 'expired';
        }

        return 'pending';
    }

    public function markExpired(OrgDomain $orgDomain): void
    {
        $orgDomain->update(['dns_check_expired_at' => now()]);
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

    /**
     * The domain that lets this email address join an organization automatically — or null.
     *
     * Security: only DNS-verified domains of ACTIVE organizations count. An unverified domain proves
     * nothing about who controls it, so it must never grant membership.
     */
    public function verifiedDomainFor(string $email): ?OrgDomain
    {
        $domains = OrgDomain::query()
            ->where('dns_verified', true)
            ->whereHas('organization', fn ($q) => $q->where('subscription_status', OrgStatus::Active->value))
            ->with('organization')
            ->get();

        // Prefer the most specific (longest) domain when several match.
        return $domains
            ->filter(fn (OrgDomain $d) => $this->matchesEmail($email, $d))
            ->sortByDesc(fn (OrgDomain $d) => strlen($d->domain))
            ->first();
    }

    protected function tokenPresentInDns(OrgDomain $orgDomain): bool
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
