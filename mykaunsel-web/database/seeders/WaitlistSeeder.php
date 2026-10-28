<?php

namespace Database\Seeders;

use App\Models\WaitlistEntry;
use App\Models\WaitlistSuppression;
use App\Services\WaitlistHasher;
use App\Services\WaitlistService;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Seeder;

/**
 * Sample waitlist addresses for Platform Admin → Registration Control → Waitlist. All made up (example.test), for
 * Organization and Counselor only: individuals have no waitlist. Some are already notified, and a few opted out (only their
 * hashes exist, as in real life). Needs WAITLIST_HMAC_KEY in .env; without it the waitlist is off and nothing is made. Safe to run twice.
 */
class WaitlistSeeder extends Seeder
{
    use RefusesOutsideDevelopment;

    public function run(WaitlistHasher $hasher, WaitlistService $waitlist): void
    {
        $this->refuseOutsideDevelopment();

        if (! $hasher->available() || WaitlistEntry::query()->exists()) {
            return;
        }

        $samples = [
            'organization' => ['sekolah.alpha', 'kolej.beta', 'klinik.gamma', 'syarikat.delta', 'universiti.epsilon', 'pusat.zeta'],
            'counselor' => ['kaunselor.aina', 'kaunselor.bala', 'kaunselor.chong', 'kaunselor.dewi', 'kaunselor.edi'],
        ];

        foreach ($samples as $type => $names) {
            foreach ($names as $i => $name) {
                $email = WaitlistService::normalise("{$name}@example.test");
                $signedUp = now()->subDays(40 - $i * 5);
                $notified = $i < 2; // the first two were emailed after an earlier reopening

                WaitlistEntry::query()->insert([
                    'type' => $type, 'email' => $email, 'email_hash' => $hasher->emailHash($type, $email),
                    'status' => $notified ? WaitlistEntry::NOTIFIED : WaitlistEntry::WAITING,
                    'notified_at' => $notified ? now()->subDays(3 + $i) : null,
                    'emailed_at' => $notified ? now()->subDays(3 + $i) : null,
                    'created_at' => $signedUp, 'updated_at' => $signedUp,
                ]);
            }

            // Two people who opted out: just the hash.
            foreach (["opted.out.one.{$type}@example.test", "opted.out.two.{$type}@example.test"] as $gone) {
                WaitlistSuppression::query()->insertOrIgnore(['type' => $type, 'email_hash' => $hasher->emailHash($type, $gone), 'created_at' => now()->subDays(10)]);
            }
        }
    }
}
