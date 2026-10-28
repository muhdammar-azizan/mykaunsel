<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` / `migrate --seed`: the DEMO data for development and tests (accounts with a well-known password, sample
 * organizations, counselors, complaints, prices). It runs only in the local and testing environments; anywhere else it stops with a
 * clear error before it creates anything. A real server needs no seeding: run `php artisan migrate --force` and create the first
 * Platform Admin with `php artisan platform:create-admin` (docs/SETUP.md).
 *
 * BASE_SEEDERS are the ones that make data every installation needs and that has no account and no known password. There are none
 * today (the settings have defaults, and the Platform organization is created together with the first admin). They would run
 * everywhere; add them there, never to DEMO_SEEDERS.
 */
class DatabaseSeeder extends Seeder
{
    use RefusesOutsideDevelopment;
    use WithoutModelEvents;

    /** @var list<class-string<Seeder>> */
    public const BASE_SEEDERS = [];

    /** @var list<class-string<Seeder>> */
    public const DEMO_SEEDERS = [
        PlatformSeeder::class,
        OrganizationSeeder::class,
        PricingSeeder::class,
        OrganizationReviewSeeder::class,
        LkmSnapshotSeeder::class,
        CounselorSeeder::class,
        CounselorReviewSeeder::class,
        LkmReviewRequestSeeder::class,
        AdminInvitationSeeder::class,
        WaitlistSeeder::class,
        ComplaintReviewSeeder::class,
        UserSeeder::class,
        AnalyticsDemoSeeder::class,
        AuditLogSeeder::class,
    ];

    public function run(): void
    {
        if (self::BASE_SEEDERS !== []) {
            $this->call(self::BASE_SEEDERS);
        }

        $this->refuseOutsideDevelopment();

        $this->call(self::DEMO_SEEDERS);
    }
}
