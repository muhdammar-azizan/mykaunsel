<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Database\Seeders\AvailabilityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The demo data for the availability screen really shows every case it was made to show. */
class AvailabilityDemoSeederTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();                       // Monday 2 November 2026, 09:00 Malaysian time
        $this->seed(AvailabilityDemoSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function counselorNamed(string $name): CounselorProfile
    {
        return CounselorProfile::whereHas('user', fn ($q) => $q->where('name', $name))->firstOrFail();
    }

    private function actAs(string $name): void
    {
        Sanctum::actingAs(User::where('name', $name)->firstOrFail());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $count = CalendarEntry::count();

        $this->seed(AvailabilityDemoSeeder::class);

        $this->assertSame($count, CalendarEntry::count());
    }

    public function test_everything_is_marked_as_demo_and_nobody_can_sign_in_as_them(): void
    {
        $demoUsers = User::where('name', 'like', 'Demo %')->get();

        $this->assertGreaterThanOrEqual(9, $demoUsers->count());
        foreach ($demoUsers as $user) {
            $this->assertStringEndsWith('@example.test', $user->email);
            $this->assertGreaterThan(30, strlen($user->password), 'a random hashed password nobody knows');
        }
    }

    public function test_the_closed_universitys_counselor_is_for_its_member_only(): void
    {
        $closed = $this->counselorNamed('Demo University Counselor');

        $this->actAs('Demo Member');
        $this->assertCount(3, $this->slotsOf($this->availability($closed)->assertOk()));

        $this->actAs('Demo Non-member');
        $this->availability($closed)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);

        $this->actAs('Demo Unverified');
        $this->availability($closed)->assertForbidden()->assertJson(['code' => 'email_not_verified']);
    }

    public function test_the_open_clinics_and_the_independents_are_for_everyone_confirmed(): void
    {
        $this->actAs('Demo Non-member');

        $clinic = $this->availability($this->counselorNamed('Demo Clinic Counselor'))->assertOk();
        $this->assertCount(3, $this->slotsOf($clinic));
        $this->assertSame(['name' => 'Demo Clinic Damai', 'city' => 'Kuantan'], $this->slotsOf($clinic)[0]['location']);

        $this->availability($this->counselorNamed('Demo Both Modes'))->assertOk()->assertJsonPath('counselor.modes_offered', ['online', 'physical']);
        $this->availability($this->counselorNamed('Demo Online Only'))->assertOk()->assertJsonPath('counselor.modes_offered', ['online']);
    }

    public function test_a_suspended_counselor_and_one_with_an_expired_pa_are_never_bookable(): void
    {
        $this->actAs('Demo Non-member');

        $this->availability($this->counselorNamed('Demo Suspended'))->assertNotFound();
        $this->availability($this->counselorNamed('Demo Expired PA'))->assertNotFound();
    }

    public function test_times_in_the_past_inside_the_lead_time_and_beyond_the_horizon_are_not_offered(): void
    {
        $this->actAs('Demo Non-member');
        $both = $this->counselorNamed('Demo Both Modes');

        $all = $this->startsOf($this->availability($both, ['days' => 14]));
        $far = $this->startsOf($this->availability($both, ['from' => '2026-11-30', 'days' => 14]));

        $this->assertNotContains('2026-11-01 10:00', $all, 'yesterday');
        $this->assertSame(0, count(array_filter($all, fn ($t) => str_starts_with($t, '2026-11-02'))), 'the one inside the lead time (an hour from now)');
        $this->assertSame([], $far, 'the one 35 days away');
        $this->assertCount(12, $all, '4 days x (2 online + 1 in person)');
    }

    public function test_the_booked_slot_the_empty_mode_the_block_and_the_buffer_all_show_up(): void
    {
        $this->actAs('Demo Non-member');
        $online = $this->counselorNamed('Demo Online Only');

        $answer = $this->availability($online, ['days' => 7])->assertOk();
        $states = collect($this->slotsOf($answer))->mapWithKeys(fn ($s) => [Carbon::parse($s['starts_at'])->format('d H:i') => $s['state']])->all();

        $this->assertSame('booked', $states['05 10:00'], 'the booked one (its online booking has no meeting link yet)');
        $this->assertArrayNotHasKey('05 14:30', $states, 'under the personal block: left out, not shown as booked');
        $this->assertArrayNotHasKey('05 15:00', $states, 'the regular 15:00 slot starts inside the block\'s buffer');
        $this->assertArrayNotHasKey('05 15:10', $states, 'inside the buffer after the block');
        $this->assertSame('available', $states['05 15:30'], 'clear of the buffer');
        $this->assertSame('available', $states['08 10:00'], 'the slot with no mode at all');
        $this->assertSame('online', collect($this->slotsOf($answer))->firstWhere('starts_at', '2026-11-08T10:00:00+08:00')['mode']);
        $this->assertStringNotContainsString('dentist', json_encode($answer->json()));
        $this->assertStringNotContainsString('private appointment', strtolower(json_encode($answer->json())));
    }
}
