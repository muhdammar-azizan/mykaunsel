<?php

namespace Tests\Feature\Api;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use App\Notifications\NewTimeRequestCounselor;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Mode B, the client's side: the counselor's working hours, asking for a time, the person's own requests, withdrawing. The clock stands at
 * Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures). Aina works Thursdays 10:00 to 13:00 in one-hour times, online.
 */
class TimeRequestApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->me = $this->client();
        $this->aina = $this->counselor('Aina', [$this->platform]);
        $this->hours($this->aina);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hours(CounselorProfile $who, array $over = []): CounselorWeeklyHour
    {
        return CounselorWeeklyHour::create($over + [
            'counselor_user_id' => $who->user_id, 'context_org_id' => $this->platform->id, 'weekday' => 4,
            'start_time' => '10:00:00', 'end_time' => '13:00:00', 'slot_minutes' => 60, 'session_mode' => 'online',
        ]);
    }

    private function ask(array $over = [], ?CounselorProfile $who = null)
    {
        return $this->postJson('/api/v1/counselors/'.($who ?? $this->aina)->public_id.'/requests', $over + ['date' => '2026-11-05', 'time' => '10:00']);
    }

    // ---- who may ask -----------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401_and_unconfirmed_email_403_and_maintenance_503(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/counselors/'.$this->aina->public_id.'/requests', [])->assertUnauthorized();
        $this->getJson('/api/v1/requests')->assertUnauthorized();

        $this->client(verified: false);
        $this->ask()->assertForbidden()->assertJson(['code' => 'email_not_verified']);

        $this->client();
        app(PlatformSettings::class)->setMaintenance(null, true);
        $this->ask()->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertSame(0, BookingRequest::count());
    }

    // ---- the working hours -----------------------------------------------------------------------------------------

    public function test_the_working_hours_are_shown_with_the_window_and_nothing_private(): void
    {
        $this->hours($this->aina, ['weekday' => 2, 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'slot_minutes' => 30]);

        $response = $this->getJson('/api/v1/counselors/'.$this->aina->public_id.'/working-hours')->assertOk();

        $response->assertJsonPath('counselor.display_name', 'Aina')->assertJsonPath('accepts_requests', true)->assertJsonPath('answer_within_hours', 48)
            ->assertJsonPath('hours.0.weekday', 2)->assertJsonPath('hours.0.from', '14:00')->assertJsonPath('hours.0.duration_minutes', 30)
            ->assertJsonPath('hours.1.weekday', 4)->assertJsonPath('hours.1.mode', 'online')
            ->assertJsonPath('booking_window.min_lead_hours', 2)->assertJsonPath('booking_window.last_date', '2026-12-02');
        $this->assertStringNotContainsString('context', $response->content());
        $response->assertHeader('Cache-Control');
    }

    public function test_hours_of_an_organization_this_person_may_not_see_are_left_out_and_a_hidden_counselor_is_the_same_404(): void
    {
        $this->hours($this->aina, ['weekday' => 1, 'context_org_id' => $this->closed->id]);

        $response = $this->getJson('/api/v1/counselors/'.$this->aina->public_id.'/working-hours')->assertOk();
        $this->assertSame([4], array_column($response->json('hours'), 'weekday'));

        $hidden = $this->counselor('Hidden', [$this->closed]);
        $unknown = $this->getJson('/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz/working-hours');
        $this->getJson('/api/v1/counselors/'.$hidden->public_id.'/working-hours')->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);
        $this->assertSame($unknown->content(), $this->getJson('/api/v1/counselors/'.$hidden->public_id.'/working-hours')->content());
    }

    // ---- asking ----------------------------------------------------------------------------------------------------

    public function test_asking_makes_a_pending_request_with_the_length_mode_and_context_of_the_line(): void
    {
        Notification::fake();
        $place = $this->place($this->open, 'Kuantan', 'Pahang');
        $this->hours($this->aina, ['weekday' => 5, 'context_org_id' => $this->open->id, 'session_mode' => 'physical', 'location_id' => $place->id, 'slot_minutes' => 45]);

        $response = $this->ask(['date' => '2026-11-06', 'time' => '11:15'])->assertCreated()
            ->assertJsonPath('request.status', 'pending')->assertJsonPath('request.starts_at', '2026-11-06T11:15:00+08:00')
            ->assertJsonPath('request.ends_at', '2026-11-06T12:00:00+08:00')->assertJsonPath('request.mode', 'physical')
            ->assertJsonPath('request.location.city', 'Kuantan');

        $this->assertSame($this->open->id, BookingRequest::sole()->context_org_id);
        $this->assertSame($place->id, BookingRequest::sole()->location_id);
        $this->assertStringNotContainsString('address', $response->content());
    }

    public function test_the_stored_request_carries_what_the_line_decided(): void
    {
        $this->ask(['message' => '  Exams.  '])->assertCreated()
            ->assertJsonPath('request.counselor.display_name', 'Aina')->assertJsonPath('request.counselor.id', $this->aina->public_id)
            ->assertJsonPath('request.duration_minutes', 60)->assertJsonPath('request.mode', 'online')->assertJsonPath('request.message', 'Exams.')
            ->assertJsonPath('request.response_reason', null)->assertJsonPath('request.booking_id', null);

        $row = BookingRequest::sole();
        $this->assertSame($this->me->id, $row->user_id);
        $this->assertSame($this->platform->id, $row->context_org_id);
        $this->assertSame(BookingRequestStatus::Pending, $row->status);
        $this->assertSame(26, strlen($row->public_id));
        $this->assertSame(Carbon::now()->addHours(48)->getTimestamp(), $row->expires_at->getTimestamp());
    }

    public function test_it_never_waits_longer_than_the_time_asked_for(): void
    {
        $this->hours($this->aina, ['weekday' => 1, 'start_time' => '12:00:00', 'end_time' => '14:00:00']);

        $this->ask(['date' => '2026-11-02', 'time' => '12:00'])->assertCreated();

        $this->assertSame(Carbon::parse('2026-11-02 12:00', 'Asia/Kuala_Lumpur')->getTimestamp(), BookingRequest::sole()->expires_at->getTimestamp());
    }

    public function test_nothing_is_booked_or_held_by_asking_and_the_counselor_is_told(): void
    {
        Notification::fake();

        $this->ask()->assertCreated();

        $this->assertSame(0, \App\Models\Booking::count());
        $this->assertSame(0, \App\Models\CalendarEntry::count());
        Notification::assertSentTo($this->aina->user, NewTimeRequestCounselor::class, function (NewTimeRequestCounselor $n) {
            $text = implode("\n", [...$n->toMail($this->aina->user)->introLines, ...$n->toMail($this->aina->user)->outroLines]);
            $this->assertStringContainsString($this->me->name, $text);
            $this->assertStringContainsString('Thursday, 5 November 2026', $text);
            $this->assertStringContainsString('10:00 AM – 11:00 AM', $text);
            $this->assertStringContainsString('Appointments', $text);
            $this->assertStringNotContainsString($this->me->email, $text);

            return true;
        });
    }

    public function test_the_time_must_fall_inside_a_line_far_enough_ahead_and_inside_the_window(): void
    {
        $bad = [
            ['date' => '2026-11-06'],                      // Friday: no line
            ['time' => '09:00'],                           // before the window
            ['time' => '12:30'],                           // the hour would run past 13:00
            ['time' => '10:07'],                           // not a 5-minute mark
            ['date' => '2026-11-02', 'time' => '10:00'],   // Monday: no line
            ['date' => '2026-12-17'],                      // beyond the booking window
        ];

        foreach ($bad as $fields) {
            $this->ask($fields)->assertStatus(422)->assertJson(['code' => 'request_time_invalid']);
        }

        $this->hours($this->aina, ['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $this->ask(['date' => '2026-11-02', 'time' => '10:00'])->assertStatus(422)->assertJson(['code' => 'request_time_invalid']);    // sooner than 2 hours
        $this->ask(['date' => '2026-11-02', 'time' => '11:00'])->assertCreated();                                                       // exactly 2 hours
        $this->assertSame(1, BookingRequest::count());
    }

    public function test_the_fields_are_checked(): void
    {
        $this->postJson('/api/v1/counselors/'.$this->aina->public_id.'/requests', [])->assertStatus(422)->assertJsonValidationErrors(['date', 'time']);
        $this->ask(['message' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('message');
        $this->ask(['date' => '5 Nov'])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_a_counselor_who_does_not_take_requests_or_cannot_be_seen(): void
    {
        $this->aina->update(['accepts_requests' => false]);
        $this->ask()->assertStatus(409)->assertJson(['code' => 'requests_closed']);

        $hidden = $this->counselor('Hidden', [$this->closed]);
        $this->hours($hidden, ['context_org_id' => $this->closed->id]);
        $this->ask([], $hidden)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);

        $suspended = $this->counselor('Susp', [$this->platform], ['status' => VerificationStatus::Suspended]);
        $this->hours($suspended);
        $this->ask([], $suspended)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);
        $this->assertSame(0, BookingRequest::count());
    }

    public function test_a_time_that_is_not_free_on_the_counselors_calendar_is_slot_taken_and_says_no_more(): void
    {
        $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'));

        $this->ask()->assertStatus(409)->assertJson(['code' => 'slot_taken']);
        $this->ask(['time' => '11:10'])->assertStatus(409)->assertJson(['code' => 'slot_taken']);      // inside the buffer of the booked session

        $this->block($this->aina, '2026-11-05 12:00');
        $this->ask(['time' => '12:00'])->assertStatus(409)->assertJson(['code' => 'slot_taken']);
    }

    public function test_it_cannot_overlap_an_upcoming_session_or_another_waiting_request(): void
    {
        $other = $this->counselor('Bakar', [$this->platform]);
        $this->hours($other);
        $this->book($this->slot($other, $this->platform, '2026-11-05 10:00'), $this->me);

        $this->ask()->assertStatus(409)->assertJson(['code' => 'time_conflict']);                      // my own booked session with Bakar

        $this->ask(['time' => '12:00'])->assertCreated();
        $this->ask(['time' => '12:00'], $other)->assertStatus(409)->assertJson(['code' => 'time_conflict']);   // my waiting request to Aina
        $this->assertSame(1, BookingRequest::count());
    }

    public function test_at_most_three_requests_wait_at_once(): void
    {
        $this->hours($this->aina, ['weekday' => 5, 'start_time' => '10:00:00', 'end_time' => '13:00:00']);

        $this->ask(['time' => '10:00'])->assertCreated();
        $this->ask(['time' => '11:00'])->assertCreated();
        $this->ask(['time' => '12:00'])->assertCreated();
        $this->ask(['date' => '2026-11-06', 'time' => '10:00'])->assertStatus(409)->assertJson(['code' => 'request_limit_reached', 'max_pending_requests' => 3]);

        $this->assertSame(3, BookingRequest::count());
    }

    public function test_asking_again_for_the_same_time_answers_the_same_request(): void
    {
        Notification::fake();

        $first = $this->ask()->assertCreated();
        $again = $this->ask()->assertOk();

        $this->assertSame($first->json('request.id'), $again->json('request.id'));
        $this->assertSame(1, BookingRequest::count());
        Notification::assertSentToTimes($this->aina->user, NewTimeRequestCounselor::class, 1);
    }

    public function test_asking_is_rate_limited_with_retry_after(): void
    {
        config(['booking.request_rate_per_minute' => 2]);

        $this->ask()->assertCreated();
        $this->ask()->assertOk();
        $this->ask()->assertStatus(429)->assertHeader('Retry-After');
    }

    // ---- reading and withdrawing -----------------------------------------------------------------------------------------

    public function test_the_list_and_the_details_are_the_persons_own(): void
    {
        $mine = $this->ask(['message' => 'Hello'])->assertCreated()->json('request.id');
        $stranger = User::factory()->create();
        $theirs = BookingRequest::create([
            'user_id' => $stranger->id, 'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'requested_date' => '2026-11-05',
            'requested_time' => '12:00:00', 'duration_minutes' => 60, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(48),
        ]);

        $list = $this->getJson('/api/v1/requests')->assertOk()->assertJsonPath('page', 1)->assertJsonCount(1, 'data');
        $this->assertSame($mine, $list->json('data.0.id'));
        $this->getJson('/api/v1/requests/'.$mine)->assertOk()->assertJsonPath('request.message', 'Hello');

        $someoneElses = $this->getJson('/api/v1/requests/'.$theirs->public_id);
        $someoneElses->assertNotFound()->assertJson(['code' => 'request_not_found']);
        $this->assertSame($someoneElses->content(), $this->getJson('/api/v1/requests/01hzzzzzzzzzzzzzzzzzzzzzzz')->content());
        $this->postJson('/api/v1/requests/'.$theirs->public_id.'/withdraw')->assertNotFound();
        $this->assertSame(BookingRequestStatus::Pending, $theirs->fresh()->status);
    }

    public function test_a_request_nobody_answered_in_time_is_shown_as_expired_even_before_the_command_runs(): void
    {
        $id = $this->ask()->assertCreated()->json('request.id');

        Carbon::setTestNow(Carbon::now()->addHours(49));

        $this->getJson('/api/v1/requests/'.$id)->assertOk()->assertJsonPath('request.status', 'expired');
        $this->postJson('/api/v1/requests/'.$id.'/withdraw')->assertStatus(409)->assertJson(['code' => 'request_not_pending']);
    }

    public function test_withdrawing_takes_it_back_and_repeating_changes_nothing(): void
    {
        $id = $this->ask()->assertCreated()->json('request.id');

        $this->postJson('/api/v1/requests/'.$id.'/withdraw')->assertOk()->assertJsonPath('request.status', 'withdrawn');
        $stamp = BookingRequest::sole()->responded_at;

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->postJson('/api/v1/requests/'.$id.'/withdraw')->assertOk()->assertJsonPath('request.status', 'withdrawn');
        $this->assertEquals($stamp, BookingRequest::sole()->responded_at);

        // A withdrawn request frees the person to ask again for the same time.
        $this->ask()->assertCreated();
    }

    public function test_an_answered_request_cannot_be_withdrawn(): void
    {
        $id = $this->ask()->assertCreated()->json('request.id');
        BookingRequest::sole()->update(['status' => BookingRequestStatus::Rejected]);

        $this->postJson('/api/v1/requests/'.$id.'/withdraw')->assertStatus(409)->assertJson(['code' => 'request_not_pending']);
        $this->assertSame(BookingRequestStatus::Rejected, BookingRequest::sole()->status);
        $this->assertSame(BookingStatus::Confirmed->value, 'confirmed');
    }
}
