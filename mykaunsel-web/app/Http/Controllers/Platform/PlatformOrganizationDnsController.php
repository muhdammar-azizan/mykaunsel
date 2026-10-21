<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Notifications\DomainVerificationReminder;
use App\Services\DomainVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Platform Admin actions on an organization's domains: check now, and remind the applicant. */
class PlatformOrganizationDnsController extends Controller
{
    /** At most one reminder email per organization in this many hours. */
    private const REMINDER_COOLDOWN_HOURS = 6;

    /**
     * Check DNS now. Without `domain` it checks every unverified domain; with `domain` it re-verifies that one
     * (even if it was verified before — a record that has since been removed un-verifies the domain).
     */
    public function check(Request $request, Organization $organization, DomainVerificationService $service): RedirectResponse
    {
        if ($blocked = $this->refuse($organization)) {
            return $blocked;
        }

        $only = $request->input('domain');

        if ($only !== null) {
            $targets = $organization->orgDomains()->whereKey($only)->get();
            abort_if($targets->isEmpty(), 404);
        } else {
            $targets = $organization->orgDomains()->where('dns_verified', false)->get();

            if ($targets->isEmpty()) {
                return $this->back($organization)->with('error', 'Every domain is already verified.');
            }
        }

        // 1. Network lookups first, outside any transaction (a slow DNS server must not hold a database lock).
        $found = [];
        foreach ($targets as $domain) {
            if (! $domain->verification_token) {
                $service->generateToken($domain);
            }

            $found[$domain->id] = $service->probeDns($domain);
        }

        // 2. Save the results and the audit entry together.
        $nowVerified = 0;
        $lapsed = [];
        $perDomain = [];

        DB::transaction(function () use ($targets, $found, $service, $organization, &$nowVerified, &$lapsed, &$perDomain) {
            foreach ($targets as $domain) {
                $wasVerified = $domain->dns_verified;
                $service->applyCheckResult($domain, $found[$domain->id]);

                if ($found[$domain->id]) {
                    $nowVerified++;
                    $perDomain[$domain->domain] = 'verified';
                } else {
                    $perDomain[$domain->domain] = $wasVerified ? 'no longer verified' : 'not found';
                    if ($wasVerified) {
                        $lapsed[] = $domain->domain;
                    }
                }
            }

            $summary = "{$nowVerified} of {$targets->count()} verified";
            if ($lapsed) {
                $summary .= '; no longer verified: '.implode(', ', $lapsed);
            }

            Audit::log('organization.dns_checked', $organization, ['summary' => $summary, 'domains' => $perDomain]);
        });

        $summary = "{$nowVerified} of {$targets->count()} verified";

        if ($lapsed) {
            return $this->back($organization)->with('error', 'DNS record missing: '.implode(', ', $lapsed).' is no longer verified, so it stops matching members.');
        }

        return $this->back($organization)->with(
            $nowVerified > 0 ? 'status' : 'error',
            $nowVerified > 0 ? "DNS checked: {$summary}." : 'DNS checked: no matching TXT record found yet.',
        );
    }

    public function remind(Request $request, Organization $organization, DomainVerificationService $service): RedirectResponse
    {
        if ($blocked = $this->refuse($organization)) {
            return $blocked;
        }

        $unverified = $organization->orgDomains()->where('dns_verified', false)->get();

        if ($unverified->isEmpty()) {
            return $this->back($organization)->with('error', 'Every domain is already verified — no reminder needed.');
        }

        $registrant = $organization->registrant()->with('user')->first()?->user;

        if (! $registrant) {
            return $this->back($organization)->with('error', 'This organization has no admin to email.');
        }

        $lastSent = AuditLog::where('action', 'organization.dns_reminder_sent')
            ->where('target_type', 'organization')
            ->where('target_id', $organization->id)
            ->latest('created_at')
            ->value('created_at');

        if ($lastSent && now()->diffInHours($lastSent, true) < self::REMINDER_COOLDOWN_HOURS) {
            return $this->back($organization)->with('error', 'A reminder was already sent recently. Please wait a few hours before sending another.');
        }

        foreach ($unverified as $domain) {
            if (! $domain->verification_token) {
                $service->generateToken($domain);
            }
        }

        try {
            $registrant->notify(new DomainVerificationReminder($organization, $unverified->fresh()));
        } catch (Throwable $e) {
            report($e);

            return $this->back($organization)->with('error', 'The reminder could not be sent. Please try again.');
        }

        Audit::log('organization.dns_reminder_sent', $organization, ['summary' => "Emailed {$registrant->email}"]);

        return $this->back($organization)->with('status', "Reminder emailed to {$registrant->email}.");
    }

    /** Only pending or active customer organizations with domains to verify. */
    private function refuse(Organization $organization): ?RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        if ($organization->org_type === OrgType::Clinic) {
            return $this->back($organization)->with('error', 'Clinics do not use domain verification.');
        }

        if (! in_array($organization->subscription_status, [OrgStatus::Pending, OrgStatus::Active], true)) {
            return $this->back($organization)->with('error', 'Domains can only be checked for pending or active organizations.');
        }

        return null;
    }

    private function back(Organization $organization): RedirectResponse
    {
        return redirect()->to(route('platform.organizations.show', $organization).'#domains');
    }
}
