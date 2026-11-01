<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Enums\BookingRequestStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\BookingRefused;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Organization;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\CounselorPlanner;
use App\Services\CounselorRequests;
use App\Services\CounselorWeeklyHours;
use App\Support\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The paid period decides what can be booked (docs/PAYMENTS_PLAN.md, "Subscription end date"): an organization with no plan, or a day after its plan
 * ends, has nothing on offer and nothing can be booked, moved or asked for there. The platform organization (independent counselors) is never held to a plan.
 * The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class SubscriptionEnforcementTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A clinic open to everyone, with the given last day of its plan (null: no plan at all). */
    private function clinic(?string $lastDay, string $name = 'Klinik Tanpa Pelan'): Organization
    {
        return Organization::create([
            'name' => $name, 'org_type' => OrgType::Clinic, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active,
            'subscription_ends_at' => $lastDay === null ? null : $lastDay.' 23:59:59',
        ]);
    }

    private function body(CalendarEntry $slot): array
    {
        $start = Carbon::parse($slot->entry_date->format('Y-m-d').' '.$slot->start_time, 'Asia/Kuala_Lumpur');

        return ['slot' => $slot->public_id, 'acknowledged_policy' => true, 'expected_starts_at' => $start->toIso8601String(), 'expected_duration_minutes' => (int) $slot->duration_minutes, 'expected_mode' => 'online'];
    }

    // ---- the one rule ---------------------------------------------------------------------------------------------

    public function test_the_rule_in_php_and_the_rule_in_sql_agree(): void
    {
        $none = $this->clinic(null, 'A');
        $until = $this->clinic('2026-11-30', 'B');
        $a = $this->counselor('Aina', [$none]);
        $b = $this->counselor('Bakar', [$until]);
        $p = $this->counselor('Cahaya', [$this->platform]);

        $slots = [
            'none' => $this->slot($a, $none, '2026-11-05 10:00'),
            'last day' => $this->slot($b, $until, '2026-11-30 22:00'),
            'after' => $this->slot($b, $until, '2026-12-01 10:00'),
            'platform' => $this->slot($p, $this->platform, '2026-12-01 11:00'),
        ];

        $php = collect($slots)->map(fn (CalendarEntry $s) => Subscription::allows($s->contextOrg, $s->entry_date->format('Y-m-d')))->all();
        $sql = Subscription::scope(CalendarEntry::query())->pluck('calendar_entries.id')->all();

        $this->assertSame(['none' => false, 'last day' => true, 'after' => false, 'platform' => true], $php);
        $this->assertEqualsCanonicalizing([$slots['last day']->id, $slots['platform']->id], array_values(array_intersect($sql, collect($slots)->pluck('id')->all())));
    }

    // ---- what a client is offered and can book --------------------------------------------------------------------

    public function test_an_organization_with_no_plan_offers_nothing_and_nothing_can_be_booked(): void
    {
        $clinic = $this->clinic(null);
        $aina = $this->counselor('Aina', [$clinic]);
        $slot = $this->slot($aina, $clinic, '2026-11-05 10:00');
        $this->client();

        $this->assertSame([], $this->slotsOf($this->availability($aina)));
        $this->assertFalse(app(AvailabilityService::class)->slotBookable($slot));
        $this->postJson('/api/v1/bookings', $this->body($slot), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'slot_taken');
    }

    public function test_the_last_day_of_the_plan_counts_whole_and_the_day_after_is_closed(): void
    {
        $clinic = $this->clinic('2026-11-30');
        $aina = $this->counselor('Aina', [$clinic]);
        $last = $this->slot($aina, $clinic, '2026-11-30 22:00');
        $after = $this->slot($aina, $clinic, '2026-12-01 10:00');
        $this->client();

        $this->assertSame(['2026-11-30 22:00'], $this->startsOf($this->availability($aina, ['from' => '2026-11-29', 'days' => 4])));
        $this->assertTrue(app(AvailabilityService::class)->slotBookable($last));
        $this->assertFalse(app(AvailabilityService::class)->slotBookable($after));
        $this->postJson('/api/v1/bookings', $this->body($after), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'slot_taken');
        $this->postJson('/api/v1/bookings', $this->body($last), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    }

    public function test_renewing_brings_the_hidden_times_back_they_were_never_deleted(): void
    {
        $clinic = $this->clinic('2026-11-10');
        $aina = $this->counselor('Aina', [$clinic]);
        $slot = $this->slot($aina, $clinic, '2026-11-20 10:00');
        $this->client();

        $this->assertSame([], $this->startsOf($this->availability($aina, ['from' => '2026-11-18', 'days' => 5])));

        $clinic->update(['subscription_ends_at' => '2027-11-09 23:59:59']);

        $this->assertSame(['2026-11-20 10:00'], $this->startsOf($this->availability($aina, ['from' => '2026-11-18', 'days' => 5])));
        $this->assertNotNull(CalendarEntry::find($slot->id));
    }

    public function test_independent_counselors_are_never_held_to_a_plan_and_one_organization_does_not_hide_another(): void
    {
        $none = $this->clinic(null);
        $alone = $this->counselor('Cahaya', [$this->platform, $none]);
        $this->slot($alone, $this->platform, '2026-11-05 10:00');
        $this->slot($alone, $none, '2026-11-05 12:00');
        $this->client();

        $this->assertSame(['2026-11-05 10:00'], $this->startsOf($this->availability($alone)));
    }

    public function test_a_move_to_a_time_after_the_plan_ends_is_refused(): void
    {
        $clinic = $this->clinic('2026-11-30');
        $aina = $this->counselor('Aina', [$clinic]);
        $me = $this->client();
        $now = $this->slot($aina, $clinic, '2026-11-10 10:00');
        $later = $this->slot($aina, $clinic, '2026-12-01 10:00');
        $booking = $this->book($now, $me);

        $start = Carbon::parse($later->entry_date->format('Y-m-d').' '.$later->start_time, 'Asia/Kuala_Lumpur');
        $response = $this->postJson('/api/v1/bookings/'.$booking->public_id.'/reschedule', [
            'slot' => $later->public_id, 'expected_starts_at' => $start->toIso8601String(), 'expected_duration_minutes' => 60, 'expected_mode' => 'online',
        ]);

        $response->assertStatus(409)->assertJsonPath('code', 'slot_taken');
        $this->assertSame($now->id, $booking->fresh()->calendar_entry_id);
    }

    public function test_the_organization_in_the_app_carries_the_last_day_of_its_plan(): void
    {
        $clinic = $this->clinic('2027-03-31');
        $this->client([$clinic]);

        $this->getJson('/api/v1/account/organization')->assertOk()->assertJsonPath('organization.access_until', '2027-03-31');
    }

    // ---- what a counselor can open ---------------------------------------------------------------------------------

    private function open(CounselorProfile $who, Organization $org, string $when): void
    {
        [$date, $time] = explode(' ', $when);
        app(CounselorPlanner::class)->open($who->user, ['context' => $org->id, 'date' => $date, 'start' => $time, 'duration' => 60, 'mode' => 'online', 'location' => null]);
    }

    private function reason(callable $do): string
    {
        try {
            $do();
        } catch (BookingRefused $refused) {
            return $refused->reason;
        }

        return 'none';
    }

    public function test_a_counselor_can_not_open_a_time_for_an_organization_with_no_plan_or_after_its_plan_ends(): void
    {
        $none = $this->clinic(null);
        $until = $this->clinic('2026-11-12', 'Klinik Ada Pelan');
        $aina = $this->counselor('Aina', [$none, $until]);

        $this->assertSame('planner_no_plan', $this->reason(fn () => $this->open($aina, $none, '2026-11-05 10:00')));
        $this->assertSame('planner_after_plan', $this->reason(fn () => $this->open($aina, $until, '2026-11-13 10:00')));
        $this->assertSame('none', $this->reason(fn () => $this->open($aina, $until, '2026-11-12 10:00')));
        $this->assertSame(1, CalendarEntry::where('counselor_user_id', $aina->user_id)->count());
    }

    public function test_the_weekly_hours_open_only_the_days_inside_the_plan_and_say_how_many_were_left_out(): void
    {
        $until = $this->clinic('2026-11-12');
        $aina = $this->counselor('Aina', [$until]);
        CounselorWeeklyHour::create(['counselor_user_id' => $aina->user_id, 'context_org_id' => $until->id, 'weekday' => 4, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'slot_minutes' => 60, 'session_mode' => 'online']);

        $done = app(CounselorWeeklyHours::class)->fill($aina->user, 4);

        $this->assertSame(['created' => 4, 'skipped' => 4], $done);                  // Thursdays 5 and 12 November; 19 and 26 are after the plan
    }

    public function test_a_times_list_marks_the_times_clients_can_not_see(): void
    {
        $until = $this->clinic('2026-11-12');
        $aina = $this->counselor('Aina', [$until]);
        $this->slot($aina, $until, '2026-11-20 10:00');

        $this->actingAs($aina->user)->withSession(['current_org_id' => $until->id, 'current_role' => 'counselor'])
            ->get(route('counselor.planner'))->assertOk()->assertSee('Hidden: plan ended');
    }

    // ---- time requests ----------------------------------------------------------------------------------------------

    private function line(CounselorProfile $who, Organization $org): void
    {
        CounselorWeeklyHour::create(['counselor_user_id' => $who->user_id, 'context_org_id' => $org->id, 'weekday' => 4, 'start_time' => '10:00:00', 'end_time' => '13:00:00', 'slot_minutes' => 60, 'session_mode' => 'online']);
    }

    public function test_a_time_request_is_refused_without_a_plan_or_after_it_and_works_inside_it(): void
    {
        $none = $this->clinic(null);
        $until = $this->clinic('2026-11-12', 'Klinik Ada Pelan');
        $a = $this->counselor('Aina', [$none]);
        $b = $this->counselor('Bakar', [$until]);
        $this->line($a, $none);
        $this->line($b, $until);
        $this->client();

        $this->postJson('/api/v1/counselors/'.$a->public_id.'/requests', ['date' => '2026-11-05', 'time' => '10:00'])->assertStatus(422)->assertJsonPath('code', 'request_time_invalid');
        $this->postJson('/api/v1/counselors/'.$b->public_id.'/requests', ['date' => '2026-11-19', 'time' => '10:00'])->assertStatus(422)->assertJsonPath('code', 'request_time_invalid');
        $this->postJson('/api/v1/counselors/'.$b->public_id.'/requests', ['date' => '2026-11-12', 'time' => '10:00'])->assertSuccessful();
    }

    public function test_approving_a_request_after_the_plan_has_ended_is_refused(): void
    {
        $clinic = $this->clinic('2026-11-30');
        $aina = $this->counselor('Aina', [$clinic]);
        $this->line($aina, $clinic);
        $me = $this->client();
        $request = BookingRequest::create([
            'user_id' => $me->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $clinic->id, 'requested_date' => '2026-11-26', 'requested_time' => '10:00:00',
            'duration_minutes' => 60, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(48),
        ]);
        $clinic->update(['subscription_ends_at' => '2026-11-10 23:59:59']);              // the plan was shortened or ended meanwhile

        $this->assertSame('request_cannot_approve', $this->reason(fn () => app(CounselorRequests::class)->approve($aina->user, $request->public_id)));
        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
    }
}
