<?php

namespace App\Console\Commands;

use App\Enums\OrgStatus;
use App\Models\OrgDomain;
use App\Services\DomainVerificationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-checks unverified domains every 30 minutes (see routes/console.php).
 * After 7 days from submission it stops checking and marks the domain expired.
 */
class CheckPendingDomains extends Command
{
    protected $signature = 'organizations:check-dns';

    protected $description = 'Check DNS for unverified organization domains and expire them after 7 days';

    public function handle(DomainVerificationService $service): int
    {
        $checked = $verified = $expired = $failed = 0;

        OrgDomain::query()
            ->where('dns_verified', false)
            ->whereNull('dns_check_expired_at')
            ->whereHas('organization', fn ($q) => $q->whereIn('subscription_status', [OrgStatus::Pending->value, OrgStatus::Active->value]))
            ->with('organization')
            ->orderBy('id')
            ->each(function (OrgDomain $domain) use ($service, &$checked, &$verified, &$expired, &$failed) {
                if ($service->windowEndsAt($domain)->isPast()) {
                    $service->markExpired($domain);
                    $expired++;

                    return;
                }

                try {
                    if (! $domain->verification_token) {
                        $service->generateToken($domain);
                    }

                    $checked++;
                    if ($service->checkDns($domain)) {
                        $verified++;
                    }
                } catch (Throwable $e) {
                    // One bad lookup must not stop the rest.
                    $failed++;
                    report($e);
                }
            });

        $this->info("Checked {$checked}, verified {$verified}, expired {$expired}, failed {$failed}.");

        return self::SUCCESS;
    }
}
