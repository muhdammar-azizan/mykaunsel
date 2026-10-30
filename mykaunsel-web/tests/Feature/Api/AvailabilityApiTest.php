<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/v1/counselors/{id}/availability: who may ask, what is offered (the booking window, booked times, clashes), and what is never in
 * the answer. The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class AvailabilityApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        RateLimiter::clear('availability|1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---- who can ask ---------------------------------------------------------------------------------------------------------------------------

    public function test_signed_out_people_get_a_json_401(): void
    {
        $counselor = $this->counselor('Aina', [$this->open]);

        $this->getJson('/api/v1/counselors/'.$counselor->public_id.'/availability')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_an_unconfirmed_email_address_gets_403_with_the_code_the_app_already_knows(): void
    {
        $counselor = $this->counselor('Aina', [$this->open]);
        $this->client(verified: false);

        $this->availability($counselor)->assertForbidden()->assertJson(['code' => 'email_not_verified']);
    }

    public function test_maintenance_answers_503_in_json_with_retry_after(): void
    {
        $counselor = $this->counselor('Aina', [$this->open]);
        $this->client();
        app(PlatformSettings::class)->setMaintenance(null, true);

        $this->availability($counselor)->assertStatus(503)->assertJsonStructure(['message'])->assertHeader('Retry-After');
    }

    public function test_an_outdated_app_is_told_to_update(): void
    {
        config(['api.min_app_version' => '2.0.0']);
        $counselor = $this->counselor('Aina', [$this->open]);
        $this->client();

        $this->withHeaders(['X-App-Version' => '1.0.0', 'X-App-Platform' => 'android'])->getJson('/api/v1/counselors/'.$counselor->public_id.'/availability')
            ->assertStatus(426)->assertJson(['code' => 'app_update_required']);
    }

    public function test_a_closed_organizations_counselor_is_for_active_members_only(): void
    {
        $counselor = $this->counselor('Rina Kampus', [$this->closed]);
        $this->slot($counselor, $this->closed, '2026-11-03 10:00');

        $member = $this->client([$this->closed]);
        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($counselor)->assertOk()));

        Sanctum::actingAs($this->client([]));
        $this->availability($counselor)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);

        // A member whose membership is no longer active is a stranger again.
        foreach ([MembershipStatus::Suspended, MembershipStatus::Offboarded, MembershipStatus::Alumni] as $status) {
            Membership::where('user_id', $member->id)->update(['status' => $status]);
            Sanctum::actingAs($member->fresh());
            $this->availability($counselor)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);
        }
    }

    public function test_a_clinic_an_independent_and_an_open_organization_are_bookable_by_any_confirmed_person(): void
    {
        $clinic = $this->counselor('Clinic Aina', [$this->open]);
        $independent = $this->counselor('Indie Aina', [$this->platform]);
        $this->slot($clinic, $this->open, '2026-11-03 10:00');
        $this->slot($independent, $this->platform, '2026-11-03 10:00');
        $this->client([]);

        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($clinic)->assertOk()));
        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($independent)->assertOk()));
    }

    public function test_every_reason_for_no_gets_exactly_the_same_answer(): void
    {
        $viewer = $this->client([]);

        $pending = $this->counselor('Pending', [$this->open], ['status' => VerificationStatus::Pending]);
        $suspended = $this->counselor('Suspended', [$this->open], ['status' => VerificationStatus::Suspended]);
        $rejected = $this->counselor('Rejected', [$this->open], ['status' => VerificationStatus::Rejected]);
        $expiredPa = $this->counselor('Expired PA', [$this->open], ['pa_until' => '2026-10-01']);
        $inactiveOrg = $this->counselor('Inactive Org', [$this->org('Klinik Tutup', \App\Enums\AccessModel::Open, status: OrgStatus::Suspended)]);
        $closedStranger = $this->counselor('Closed Org', [$this->closed]);
        $notAMember = $this->counselor('No Membership', []);
        $self = $this->counselor('Myself', [$this->open]);
        $self->user->forceFill(['email_verified_at' => now()])->save();

        foreach ([$pending, $suspended, $rejected, $expiredPa, $inactiveOrg, $closedStranger, $notAMember, $self] as $profile) {
            $this->slot($profile, $this->open, '2026-11-03 10:00');
        }

        $answers = [
            'unknown id' => $this->getJson('/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz/availability'),
            'not an id' => $this->getJson('/api/v1/counselors/not-an-id/availability'),
            'a row id' => $this->getJson('/api/v1/counselors/1/availability'),
            'pending' => $this->availability($pending),
            'suspended' => $this->availability($suspended),
            'rejected' => $this->availability($rejected),
            'expired PA' => $this->availability($expiredPa),
            'inactive organization' => $this->availability($inactiveOrg),
            'closed organization, not a member' => $this->availability($closedStranger),
            'no membership at all' => $this->availability($notAMember),
        ];

        Sanctum::actingAs($self->user);
        $answers['the counselor themselves'] = $this->availability($self);
        Sanctum::actingAs($viewer);

        $first = null;
        foreach ($answers as $reason => $response) {
            $response->assertNotFound();
            $summary = [$response->getStatusCode(), $response->json(), $response->headers->get('Cache-Control')];
            $first ??= $summary;
            $this->assertSame($first, $summary, "the answer for '{$reason}' differs from the others");
        }

        $this->assertSame(['message' => 'This counselor is no longer available for booking.', 'code' => 'counselor_unavailable'], $first[1]);
    }

    public function test_the_counselor_can_still_book_other_counselors(): void
    {
        $me = $this->counselor('Myself', [$this->open]);
        $other = $this->counselor('Colleague', [$this->open]);
        $this->slot($other, $this->open, '2026-11-03 10:00');
        Sanctum::actingAs($me->user);

        $this->availability($other)->assertOk();
        $this->availability($me)->assertNotFound();
    }

    // ---- the booking window ----------------------------------------------------------------------------------------------------------------------------

    public function test_the_lead_time_the_horizon_and_the_past_are_exact_to_the_minute(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        $this->slot($aina, $this->open, '2026-11-01 10:00');   // yesterday
        $this->slot($aina, $this->open, '2026-11-02 08:00');   // earlier today
        $this->slot($aina, $this->open, '2026-11-02 10:59');   // 1 h 59 min from now: too soon
        $this->slot($aina, $this->open, '2026-11-02 11:00');   // exactly 2 h: allowed
        $this->slot($aina, $this->open, '2026-12-02 23:00');   // the last day (today + 30)
        $this->slot($aina, $this->open, '2026-12-03 09:00');   // one day too far

        $near = $this->availability($aina)->assertOk();
        $this->assertSame(['2026-11-02 11:00'], $this->startsOf($near));
        $near->assertJsonPath('booking_window.earliest_start', '2026-11-02T11:00:00+08:00')
            ->assertJsonPath('booking_window.last_date', '2026-12-02')
            ->assertJsonPath('booking_window.min_lead_hours', 2);

        $far = $this->availability($aina, ['from' => '2026-11-20'])->assertOk();
        $this->assertSame(['2026-12-02 23:00'], $this->startsOf($far));
        $this->assertNull($far->json('next_from'), 'nothing more to load');
        $this->assertSame('2026-12-02', collect($far->json('days'))->last()['date'], 'the range stops at the horizon');
    }

    public function test_the_lead_time_and_horizon_follow_the_configuration(): void
    {
        config(['booking.min_lead_hours' => 5, 'booking.horizon_days' => 3]);
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-02 13:59');
        $this->slot($aina, $this->open, '2026-11-02 14:00');
        $this->slot($aina, $this->open, '2026-11-05 10:00');
        $this->slot($aina, $this->open, '2026-11-06 10:00');

        $this->assertSame(['2026-11-02 14:00', '2026-11-05 10:00'], $this->startsOf($this->availability($aina)));
    }

    public function test_the_range_pages_with_next_from_and_stops_at_fourteen_days(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        $first = $this->availability($aina)->assertOk();
        $this->assertCount(14, $first->json('days'), 'fourteen days by default');
        $this->assertSame('2026-11-02', $first->json('days.0.date'));
        $this->assertSame('2026-11-15', $first->json('days.13.date'));
        $this->assertSame('2026-11-16', $first->json('next_from'));

        $second = $this->availability($aina, ['from' => $first->json('next_from'), 'days' => 3])->assertOk();
        $this->assertSame(['2026-11-16', '2026-11-17', '2026-11-18'], collect($second->json('days'))->pluck('date')->all());
        $this->assertSame('2026-11-19', $second->json('next_from'));

        $this->assertCount(1, $this->availability($aina, ['days' => 1])->json('days'));
    }

    public function test_a_start_in_the_past_is_moved_to_today_and_one_beyond_the_horizon_is_empty(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        $this->assertSame('2026-11-02', $this->availability($aina, ['from' => '2026-10-01', 'days' => 2])->json('days.0.date'));

        $beyond = $this->availability($aina, ['from' => '2027-02-01'])->assertOk();
        $this->assertSame([], $beyond->json('days'));
        $this->assertNull($beyond->json('next_from'));
        $this->assertSame('2026-12-02', $beyond->json('booking_window.last_date'));
    }

    public function test_bad_input_is_a_422(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        foreach ([['days' => 0], ['days' => 15], ['days' => 'x'], ['days' => 2.5], ['from' => 'soon'], ['from' => '2026-13-45'], ['from' => '03/11/2026'], ['mode' => 'in_person'], ['mode' => 'x'], ['mode' => ['a']]] as $query) {
            $this->availability($aina, $query)->assertStatus(422);
        }

        // Unknown things are ignored, never an error and never a filter (the app does not choose the context).
        $this->availability($aina, ['context_org_id' => 999, 'page' => 'x'])->assertOk();
    }

    public function test_malaysian_midnight_decides_the_day_not_utc(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-03 00:30');    // 16:30 UTC on the 2nd
        $this->slot($aina, $this->open, '2026-11-03 23:30');    // 15:30 UTC on the 3rd

        $answer = $this->availability($aina, ['days' => 3])->assertOk();

        $this->assertSame(['2026-11-03 00:30', '2026-11-03 23:30'], collect($answer->json('days'))->firstWhere('date', '2026-11-03')['slots'] === [] ? [] : $this->startsOf($answer));
        $this->assertSame(2, collect($answer->json('days'))->firstWhere('date', '2026-11-03')['counts']['online']);
        $this->assertSame(0, collect($answer->json('days'))->firstWhere('date', '2026-11-02')['counts']['online']);
        $answer->assertJsonPath('days.1.slots.0.starts_at', '2026-11-03T00:30:00+08:00');

        // At 23:30 UTC on the 2nd it is already 07:30 on the 3rd in Malaysia: "today" is the 3rd.
        Carbon::setTestNow(Carbon::parse('2026-11-02 23:30:00', 'UTC'));
        $this->assertSame('2026-11-03', $this->availability($aina, ['days' => 1])->json('days.0.date'));
    }

    // ---- what a slot is ------------------------------------------------------------------------------------------------------------------------------------

    public function test_a_slot_with_no_mode_is_online_and_the_mode_filter_works(): void
    {
        $aina = $this->counselor('Aina', [$this->platform, $this->open]);
        $place = $this->place($this->open);
        $this->client([]);
        $this->slot($aina, $this->platform, '2026-11-03 09:00', null);
        $this->slot($aina, $this->platform, '2026-11-03 11:00', SessionMode::Online);
        $this->slot($aina, $this->open, '2026-11-03 14:00', SessionMode::Physical, $place);

        $all = $this->availability($aina)->assertOk();
        $this->assertSame(['online', 'online', 'physical'], collect($this->slotsOf($all))->pluck('mode')->all());
        $this->assertSame(['online', 'physical'], $all->json('counselor.modes_offered'));

        $this->assertSame(['2026-11-03 14:00'], $this->startsOf($this->availability($aina, ['mode' => 'physical'])));
        $this->assertSame(['2026-11-03 09:00', '2026-11-03 11:00'], $this->startsOf($this->availability($aina, ['mode' => 'online'])));
        $this->assertSame(['online', 'physical'], $this->availability($aina, ['mode' => 'online'])->json('counselor.modes_offered'), 'what is offered does not depend on the filter');
    }

    public function test_the_counts_equal_the_available_slots_returned_for_each_day_and_mode(): void
    {
        $aina = $this->counselor('Aina', [$this->platform, $this->open]);
        $place = $this->place($this->open);
        $this->client([]);

        foreach (range(3, 9) as $day) {
            $this->slot($aina, $this->platform, "2026-11-0{$day} 09:00", SessionMode::Online);
            $this->slot($aina, $this->platform, "2026-11-0{$day} 11:00", null);
            if ($day % 2 === 0) {
                $this->slot($aina, $this->open, "2026-11-0{$day} 14:00", SessionMode::Physical, $place);
            }
            if ($day % 3 === 0) {
                $this->book($this->slot($aina, $this->platform, "2026-11-0{$day} 16:00", SessionMode::Online));
            }
        }

        foreach ([[], ['mode' => 'online'], ['mode' => 'physical']] as $query) {
            $answer = $this->availability($aina, $query)->assertOk();

            foreach ($answer->json('days') as $day) {
                foreach (['online', 'physical'] as $mode) {
                    $expected = collect($day['slots'])->where('state', 'available')->where('mode', $mode)->count();
                    $this->assertSame($expected, $day['counts'][$mode], "{$day['date']} {$mode} with ".json_encode($query));
                }
            }
        }
    }

    public function test_booked_times_come_back_as_booked_with_no_detail_in_every_layer(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        $booked = $this->slot($aina, $this->open, '2026-11-03 09:00');
        $this->book($booked, \App\Models\User::factory()->create(['name' => 'Secret Client', 'email' => 'secret.client@example.test']));
        $flagOnly = $this->slot($aina, $this->open, '2026-11-03 12:00', available: false);                       // flag off, no booking row
        $rowOnly = $this->slot($aina, $this->open, '2026-11-03 15:00');
        $this->book($rowOnly, clearFlag: false);                                                                  // booking row, flag still on
        $cancelled = $this->slot($aina, $this->open, '2026-11-04 09:00');
        $this->book($cancelled, status: BookingStatus::Cancelled);                                                // cancelled: free again
        $free = $this->slot($aina, $this->open, '2026-11-04 12:00');

        $answer = $this->availability($aina, ['days' => 3])->assertOk();
        $states = collect($this->slotsOf($answer))->mapWithKeys(fn ($s) => [\Carbon\Carbon::parse($s['starts_at'])->format('d H:i') => $s['state']])->all();

        $this->assertSame(['03 09:00' => 'booked', '03 12:00' => 'booked', '03 15:00' => 'booked', '04 09:00' => 'available', '04 12:00' => 'available'], $states);

        foreach ($this->slotsOf($answer) as $slot) {
            $this->assertSame(['id', 'starts_at', 'duration_minutes', 'mode', 'state', 'location'], array_keys($slot), 'a booked time carries exactly what a free one does');
        }

        $dump = json_encode($answer->json());
        foreach (['Secret Client', 'secret.client', 'secret-room-123', 'meet.example'] as $leak) {
            $this->assertStringNotContainsString($leak, $dump);
        }
    }

    public function test_private_blocks_are_never_returned_and_a_slot_they_cover_is_left_out_not_shown_as_booked(): void
    {
        $aina = $this->counselor('Aina', [$this->open], ['buffer' => 15]);
        $this->client([]);
        $this->block($aina, '2026-11-03 14:00');                  // 14:00-15:00, with the buffer 13:45-15:15

        foreach (['12:44', '12:45', '12:46', '13:30', '14:30', '15:10', '15:15', '15:16'] as $time) {
            $this->slot($aina, $this->open, "2026-11-03 {$time}");
        }

        $answer = $this->availability($aina)->assertOk();

        // A slot is 60 minutes. 12:45 ends exactly when the block's buffer starts (13:45): allowed. 12:46 ends a minute into it: hidden.
        // 15:15 starts exactly when the buffer ends: allowed. 15:10 starts inside it: hidden.
        $this->assertSame(['2026-11-03 12:44', '2026-11-03 12:45', '2026-11-03 15:15', '2026-11-03 15:16'], $this->startsOf($answer));
        $this->assertSame([], $this->startsOf($answer, 'booked'), 'a covered slot is never shown as booked');

        $dump = json_encode($answer->json());
        $this->assertStringNotContainsString('Private dentist visit', $dump);
        $this->assertStringNotContainsString('personal', strtolower($dump));
    }

    public function test_a_block_created_after_the_slot_removes_the_slot(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($aina)));

        $this->block($aina, '2026-11-03 10:30', 30);

        $this->assertSame([], $this->startsOf($this->availability($aina)));
    }

    public function test_two_slots_in_two_contexts_at_the_same_time_cannot_both_be_taken(): void
    {
        $aina = $this->counselor('Aina', [$this->platform, $this->open]);
        $this->client([]);
        $independent = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $this->slot($aina, $this->open, '2026-11-03 10:00');                   // the same hour, another context
        $this->slot($aina, $this->open, '2026-11-03 11:10');                   // inside the independent slot's buffer (ends 11:00 + 15)
        $this->slot($aina, $this->open, '2026-11-03 11:15');                   // exactly clear of it

        $this->assertSame(['2026-11-03 10:00', '2026-11-03 10:00', '2026-11-03 11:10', '2026-11-03 11:15'], $this->startsOf($this->availability($aina)), 'nothing is taken yet');

        $this->book($independent);

        $answer = $this->availability($aina)->assertOk();
        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($answer, 'booked'));
        $this->assertSame(['2026-11-03 11:15'], $this->startsOf($answer, 'available'), 'the other context\'s overlapping slots disappear without being shown as booked');
    }

    public function test_the_buffer_is_the_counselors_own(): void
    {
        $aina = $this->counselor('Aina', [$this->open], ['buffer' => 0]);
        $this->client([]);
        $this->book($this->slot($aina, $this->open, '2026-11-03 10:00'));
        $this->slot($aina, $this->open, '2026-11-03 11:00');                   // adjacent: fine with no buffer
        $this->slot($aina, $this->open, '2026-11-03 09:01');                   // overlaps the booked one

        $this->assertSame(['2026-11-03 11:00'], $this->startsOf($this->availability($aina), 'available'));

        CounselorProfile::where('id', $aina->id)->update(['buffer_minutes' => 30]);
        $this->assertSame([], $this->startsOf($this->availability($aina), 'available'), 'with 30 minutes of buffer 11:00 is too close');
    }

    public function test_adjacent_free_slots_do_not_hide_each_other(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        foreach (['09:00', '10:00', '11:00', '12:00'] as $time) {
            $this->slot($aina, $this->open, "2026-11-03 {$time}");
        }

        $this->assertCount(4, $this->startsOf($this->availability($aina)));
    }

    public function test_a_calendar_entry_of_another_counselor_never_matters(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $other = $this->counselor('Other', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->block($other, '2026-11-03 10:00');
        $this->book($this->slot($other, $this->open, '2026-11-03 10:00'));

        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($aina), 'available'));
    }

    public function test_an_online_time_is_offered_even_before_the_counselor_has_a_meeting_link(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        CounselorProfile::where('id', $aina->id)->update(['meeting_provider' => null]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-03 10:00');

        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($aina)->assertOk(), 'available'));
    }

    public function test_an_in_person_time_shows_the_place_name_and_city_only(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-03 10:00', SessionMode::Physical, $this->place($this->open));
        $this->slot($aina, $this->open, '2026-11-03 14:00', SessionMode::Online);

        $slots = $this->slotsOf($this->availability($aina)->assertOk());

        $this->assertSame(['name' => 'Klinik Damai Kuantan', 'city' => 'Kuantan'], $slots[0]['location']);
        $this->assertNull($slots[1]['location']);
    }

    // ---- contexts and names -----------------------------------------------------------------------------------------------------------------------------------

    public function test_only_the_slots_of_contexts_the_viewer_may_see_are_returned(): void
    {
        $aina = $this->counselor('Aina', [$this->closed, $this->open]);
        $this->slot($aina, $this->closed, '2026-11-03 09:00');
        $this->slot($aina, $this->open, '2026-11-03 14:00');
        $stranger = $this->client([]);

        $this->assertSame(['2026-11-03 14:00'], $this->startsOf($this->availability($aina)->assertOk()));

        Sanctum::actingAs($this->client([$this->closed]));
        $this->assertSame(['2026-11-03 09:00', '2026-11-03 14:00'], $this->startsOf($this->availability($aina)->assertOk()));

        // A slot of a context where the counselor no longer works is gone for everyone.
        Membership::where('user_id', $aina->user_id)->where('org_id', $this->open->id)->update(['status' => MembershipStatus::Offboarded]);
        $this->assertSame(['2026-11-03 09:00'], $this->startsOf($this->availability($aina)));
        $this->assertNotNull($stranger);
    }

    public function test_the_name_is_the_one_the_list_and_the_profile_show_to_the_same_viewer(): void
    {
        $aina = $this->counselor('Aina Registered', [$this->platform, $this->closed, $this->open], ['display_independent' => 'Aina Damai', 'display_org' => 'Dr. Aina (Kampus)']);
        $this->slot($aina, $this->open, '2026-11-03 10:00');

        foreach ([$this->client([]), $this->client([$this->closed])] as $viewer) {
            Sanctum::actingAs($viewer);
            $name = $this->availability($aina)->assertOk()->json('counselor.display_name');

            $this->assertSame($this->getJson('/api/v1/counselors/'.$aina->public_id)->json('data.name'), $name);
            $this->assertSame($name, collect($this->getJson('/api/v1/counselors')->json('data'))->firstWhere('id', $aina->public_id)['name']);
        }
    }

    public function test_the_profile_and_the_list_never_offer_a_time_the_booking_screen_would_not(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $this->slot($aina, $this->open, '2026-11-02 10:00');     // too soon (lead time)
        $this->slot($aina, $this->open, '2026-12-20 10:00');     // beyond the horizon
        $this->block($aina, '2026-11-03 14:00');
        $this->slot($aina, $this->open, '2026-11-03 14:30');     // under the block
        $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->slot($aina, $this->open, '2026-11-04 10:00');

        $offered = $this->startsOf($this->availability($aina), 'available');
        $profile = $this->getJson('/api/v1/counselors/'.$aina->public_id)->assertOk();
        $shown = collect($profile->json('data.upcoming_slots'))->map(fn ($s) => Carbon::parse($s['starts_at'])->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))->all();

        $this->assertSame(['2026-11-03 10:00', '2026-11-04 10:00'], $offered);
        $this->assertSame($offered, $shown);
        $this->assertSame('2026-11-03T10:00:00+08:00', collect($this->getJson('/api/v1/counselors')->json('data'))->firstWhere('id', $aina->public_id)['next_available']['starts_at']);
    }

    // ---- the shape of the answer and what it never holds -------------------------------------------------------------------------------------------------------

    public function test_the_shape_of_the_answer_and_the_random_slot_ids(): void
    {
        $aina = $this->counselor('Aina', [$this->open], ['accepts_requests' => false]);
        $slot = $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->client([]);

        $answer = $this->availability($aina)->assertOk();

        $this->assertSame(['counselor', 'time_zone', 'booking_window', 'days', 'next_from'], array_keys($answer->json()));
        $this->assertSame(['id', 'display_name', 'avatar_url', 'verified', 'modes_offered', 'accepts_requests'], array_keys($answer->json('counselor')));
        $this->assertSame(['earliest_start', 'last_date', 'min_lead_hours'], array_keys($answer->json('booking_window')));
        $this->assertSame(['date', 'counts', 'slots'], array_keys($answer->json('days.0')));
        $answer->assertJsonPath('time_zone', 'Asia/Kuala_Lumpur')
            ->assertJsonPath('counselor.id', $aina->public_id)
            ->assertJsonPath('counselor.avatar_url', null)
            ->assertJsonPath('counselor.verified', true)
            ->assertJsonPath('counselor.accepts_requests', false);

        $id = collect($this->slotsOf($answer))->first()['id'];
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $id, 'a random id, not a counter');
        $this->assertSame($slot->public_id, $id);
        $this->assertNotSame((string) $slot->id, $id);
        $this->assertStringNotContainsString('"counselor_id"', $answer->getContent());
    }

    public function test_the_answer_never_holds_a_link_an_address_a_client_a_booking_a_price_or_an_organization(): void
    {
        $aina = $this->counselor('Aina', [$this->closed, $this->open]);
        $place = $this->place($this->open);
        $this->client([$this->closed]);
        $this->book($this->slot($aina, $this->open, '2026-11-03 09:00'), \App\Models\User::factory()->create(['name' => 'Siti Klien']));
        $this->slot($aina, $this->open, '2026-11-03 14:00', SessionMode::Physical, $place);
        $this->slot($aina, $this->closed, '2026-11-03 16:00');
        $this->block($aina, '2026-11-04 10:00');

        $answer = $this->availability($aina, ['days' => 3])->assertOk();
        [$keys, $values] = $this->flatten($answer->json());

        foreach (array_diff($keys, ['avatar_url', 'booking_window']) as $key) {   // both are in the contract on purpose
            foreach (['meeting', 'link', 'url', 'address', 'street', 'postcode', 'latitude', 'longitude', 'booking', 'client', 'user', 'price', 'fee', 'cost', 'org', 'context', 'title', 'note', 'email'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, strtolower($key), "key {$key}");
            }
        }

        foreach (['secret-room-123', 'meet.example', 'Jalan Rahsia', 'Taman Sulit', '25300', '3.8077', '103.326', 'Tingkat dua', 'Siti Klien', 'Private dentist visit', 'Universiti Contoh', 'counselor.example'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, json_encode($values), $forbidden);
        }
    }

    public function test_the_phone_may_keep_the_answer_for_thirty_seconds_but_no_shared_cache_may(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);

        $ok = $this->availability($aina)->assertOk();
        $this->assertStringContainsString('private', $ok->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=30', $ok->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', $ok->headers->get('Vary'));

        $this->assertStringContainsString('no-store', $this->getJson('/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz/availability')->headers->get('Cache-Control'));
    }

    public function test_nothing_is_kept_on_the_server_so_a_new_booking_shows_at_once(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->client([]);
        $slot = $this->slot($aina, $this->open, '2026-11-03 10:00');

        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($aina), 'available'));
        $this->book($slot);
        $this->assertSame(['2026-11-03 10:00'], $this->startsOf($this->availability($aina), 'booked'));
    }

    // ---- limits ------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_it_is_rate_limited_per_person_with_retry_after(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $viewer = $this->client([]);
        RateLimiter::clear('availability|'.$viewer->id);

        foreach (range(1, 60) as $i) {
            $this->availability($aina)->assertOk();
        }

        $this->availability($aina)->assertStatus(429)->assertHeader('Retry-After');

        // Another person is not affected.
        Sanctum::actingAs($this->client([]));
        $this->availability($aina)->assertOk();
    }

    public function test_the_number_of_queries_does_not_depend_on_the_number_of_slots(): void
    {
        $aina = $this->counselor('Aina', [$this->platform, $this->open]);
        $place = $this->place($this->open);
        $this->client([]);

        $queries = function () use ($aina) {
            $n = 0;
            \Illuminate\Support\Facades\DB::listen(function () use (&$n) {
                $n++;
            });
            $this->availability($aina, ['days' => 14])->assertOk();

            return $n;
        };

        $this->slot($aina, $this->open, '2026-11-03 09:00', SessionMode::Physical, $place);
        $this->book($this->slot($aina, $this->platform, '2026-11-03 12:00'));
        $this->block($aina, '2026-11-03 16:00');
        $queries();                                      // warm-up (first-use work)
        $few = $queries();

        foreach (range(4, 13) as $day) {
            $d = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            foreach (['09:00', '11:00', '13:00', '15:00', '17:00'] as $time) {
                $this->slot($aina, $day % 2 ? $this->platform : $this->open, "2026-11-{$d} {$time}", $day % 2 ? SessionMode::Online : SessionMode::Physical, $day % 2 ? null : $place);
            }
            $this->block($aina, "2026-11-{$d} 19:00");
            $this->book($this->slot($aina, $this->platform, "2026-11-{$d} 07:00"));
        }

        $this->assertSame($few, $queries(), 'the same queries for 3 slots or for 80');
    }
}
