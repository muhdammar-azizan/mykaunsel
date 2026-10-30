<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The seeders switch model events off, so the public ids must not depend on them. */
class PublicIdsAfterFullSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_seed_leaves_no_entry_without_an_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, CalendarEntry::count());
        $this->assertSame(0, DB::table('calendar_entries')->whereNull('public_id')->count());
    }

    public function test_counselor_profiles_also_have_an_id_after_the_full_seed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, CounselorProfile::count());
        $this->assertSame(0, DB::table('counselor_profiles')->whereNull('public_id')->count());
    }
}
