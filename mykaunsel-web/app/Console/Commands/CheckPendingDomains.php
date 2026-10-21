<?php

namespace App\Console\Commands;

use App\Enums\OrgStatus;
use App\Facades\Audit;
use App\Models\OrgDomain;
use App\Services\DomainVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-checks unverified domains every 30 minutes (see routes/console.php).
 * After 7 days from submission it stops checking and marks the domain expired.
 * Anything it records in the audit log is attributed to the "system" actor.
 */
class CheckPendingDomains extends Command
{
    protected $signature = 'organizations:check-dns';

    protected $description = 'Check DNS for unverified organization domains and expire them after 7 days';

    public function handle(DomainVerificationService $service): int
    {
        $checked = $verified = $expired = $failed = 0;

        Audit::asSystem(function () use ($service, &$checked, &$verified, &$expired, &$failed) {
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

                        // Network lookup first, outside any transaction; then the result and its audit entry are saved together.
                        $found = $service->probeDns($domain);
                        $checked++;

                        DB::transaction(function () use ($service, $domain, $found, &$verified) {
                            $service->applyCheckResult($domain, $found);

                            if ($found) {
                                $verified++;
                                Audit::log('organization.dns_verified', $domain->organization, ['domain' => $domain->domain]);
                            }
                        });
                    } catch (Throwable $e) {
                        // One bad lookup must not stop the rest.
                        $failed++;
                        report($e);
                    }
                });
        });

        $this->info("Checked {$checked}, verified {$verified}, expired {$expired}, failed {$failed}.");

        return self::SUCCESS;
    }
}
