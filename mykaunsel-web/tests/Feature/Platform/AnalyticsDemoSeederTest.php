<?php

namespace Tests\Feature\Platform;

use App\Models\Booking;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AnalyticsRange;
use Database\Seeders\AnalyticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The demo data for the Analytics page: it fills every section, shows the edge cases, is clearly marked as made up, and is safe to run twice.
 */
class AnalyticsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const KL = 'Asia/Kuala_Lumpur';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', self::KL));
        config(['analytics.small_number_threshold' => 5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(): array
    {
        return app(AnalyticsService::class)->report(AnalyticsRange::preset(180, self::KL));
    }

    public function test_it_fills_every_section_of_the_report(): void
    {
        $this->seed(AnalyticsDemoSeeder::class);

        $r = $this->report();

        $this->assertSame('week', $r['range']['bucket']);
        $this->assertFalse($r['growth']['empty']);
        $this->assertSame(11, $r['cards']['organizations']['value']);
        $this->assertSame(1, $r['cards']['organizations']['suspended']);
        $this->assertSame(1, $r['cards']['organizations']['undated'], 'one approved organization with no approval date');
        $this->assertSame([14, 10, 4, 1, 4], [$r['cards']['counselors']['value'], $r['cards']['counselors']['independent'], $r['cards']['counselors']['organization'], $r['cards']['counselors']['suspended'], $r['cards']['counselors']['undated']]);
        $this->assertGreaterThan(50, $r['cards']['sessions_completed']['value']);
        $this->assertNotNull($r['cards']['no_show_rate']['value']);
        $this->assertGreaterThan(0, $r['cards']['no_show_rate']['cancelled_excluded']);
        $this->assertTrue($r['notes']['any_session_outcomes']);

        $types = collect($r['sessions_by_type']['items'])->keyBy('key');
        foreach (['university', 'corporate', 'clinic', 'independent'] as $type) {
            $this->assertGreaterThan(0, $types[$type]['count'] ?? 0, "{$type} has sessions");
        }

        $this->assertSame([15, 10, 3, 2], [$r['outcomes']['counselors']['submitted'], $r['outcomes']['counselors']['approved'], $r['outcomes']['counselors']['rejected'], $r['outcomes']['counselors']['pending']]);
        $this->assertSame(1, $r['outcomes']['counselors']['resubmissions']);
        $this->assertSame([15, 11, 2, 2], [$r['outcomes']['organizations']['submitted'], $r['outcomes']['organizations']['approved'], $r['outcomes']['organizations']['rejected'], $r['outcomes']['organizations']['pending']]);
        $this->assertNotNull($r['outcomes']['organizations']['median_decision_seconds']);
        $this->assertNotNull($r['outcomes']['counselors']['median_decision_seconds']);
    }

    public function test_the_rare_specializations_are_grouped_into_other(): void
    {
        $this->seed(AnalyticsDemoSeeder::class);

        $labels = array_column($this->report()['specializations']['sessions']['items'], 'label');

        $this->assertContains('Stres & Kebimbangan', $labels);
        $this->assertContains('Other (fewer than 5)', $labels);
        $this->assertNotContains('Trauma', $labels, 'three sessions: under the line');
        $this->assertNotContains('Penagihan', $labels, 'two sessions: under the line');
        $this->assertSame(1, count(array_keys($labels, 'Stres & Kebimbangan', true)), 'the lower-case spelling is merged into it');
    }

    public function test_everything_it_makes_is_marked_as_demo_and_nobody_can_sign_in_as_a_demo_person(): void
    {
        $before = ['orgs' => Organization::count(), 'users' => User::count()];
        $this->seed(AnalyticsDemoSeeder::class);

        $newOrgs = Organization::where('name', 'like', '[Demo] Analytics %')->count();
        $this->assertSame(15, $newOrgs);
        $this->assertSame($before['orgs'] + 15 + 1, Organization::count(), '15 demo organizations and the platform organization');

        $demoUsers = User::where('email', 'like', '%@example.test')->get();
        $this->assertSame(16 + 4 + 6, $demoUsers->count());
        $this->assertSame($before['users'] + 26, User::count());
        $this->assertTrue($demoUsers->every(fn (User $u) => str_starts_with($u->name, 'Demo ')));
        $this->assertTrue($demoUsers->every(fn (User $u) => strlen($u->getRawOriginal('password')) === 60 && ! password_verify('password', $u->getRawOriginal('password'))));
        $this->assertTrue(CounselorProfile::where('kb_number', 'not like', 'DEMO-KB-%')->doesntExist());
    }

    public function test_it_is_safe_to_run_twice_and_gives_the_same_data_each_time(): void
    {
        $this->seed(AnalyticsDemoSeeder::class);
        $counts = [Organization::count(), User::count(), Booking::count()];
        $first = json_encode($this->report());

        $this->seed(AnalyticsDemoSeeder::class);

        $this->assertSame($counts, [Organization::count(), User::count(), Booking::count()]);
        $this->assertSame($first, json_encode($this->report()));
    }

    public function test_it_includes_sessions_still_to_come_and_they_are_not_counted(): void
    {
        $this->seed(AnalyticsDemoSeeder::class);

        $this->assertSame(3, Booking::where('status', 'confirmed')->count());
        $this->assertSame(0, Booking::where('status', 'confirmed')->whereHas('calendarEntry', fn ($q) => $q->where('entry_date', '<=', '2026-10-28'))->count());
    }
}
