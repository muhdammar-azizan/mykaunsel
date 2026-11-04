<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingMode;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\OrgStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TimeRequestAnsweredClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * Mode B, the counselor's side: seeing the requests made to them, approving (which books the session) and declining. The clock stands at
 * Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class CounselorRequestsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform]);
        $this->client = User::factory()->create(['name' => 'Siti Client']);

        // Her weekly hours: every day 06:00-22:00 in one-hour times, online. Approving checks the time is still inside them.
        foreach (range(1, 7) as $weekday) {
            CounselorWeeklyHour::create([
                'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'weekday' => $weekday,
                'start_time' => '06:00:00', 'end_time' => '22:00:00', 'slot_minutes' => 60, 'session_mode' => 'online',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?CounselorProfile $profile = null)
    {
        return $this->actingAs(($profile ?? $this->aina)->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function request(array $over = []): BookingRequest
    {
        return BookingRequest::create($over + [
            'user_id' => $this->client->id, 'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id,
            'requested_date' => '2026-11-05', 'requested_time' => '10:00:00', 'duration_minutes' => 60, 'session_mode' => 'online',
            'message' => 'About exams', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(48),
        ]);
    }

    private function approve(BookingRequest $request, ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.requests.approve', $request->public_id));
    }

    private function decline(BookingRequest $request, ?string $reason = null)
    {
        return $this->as()->post(route('counselor.requests.decline', $request->public_id), ['reason' => $reason]);
    }

    // ---- the page --------------------------------------------------------------------------------------------------

    public function test_the_page_lists_the_waiting_requests_with_the_clients_name_and_note_only(): void
    {
        $this->request();
        $this->request(['requested_time' => '11:00:00', 'status' => BookingRequestStatus::Rejected]);
        $this->request(['requested_time' => '12:00:00', 'expires_at' => now()->subMinute()]);
        $other = $this->counselor('Bakar', [$this->platform]);
        $this->request(['counselor_user_id' => $other->user_id, 'message' => 'Not for Aina', 'user_id' => User::factory()->create(['name' => 'Someone Else'])->id]);

        $page = $this->as()->get(route('counselor.requests'))->assertOk();

        $page->assertSee('Time requests')->assertSee('Siti Client')->assertSee('About exams')->assertSee('Thu, 5 Nov 2026')->assertSee('10:00 AM – 11:00 AM');
        $page->assertDontSee('11:00 AM – 12:00 PM')->assertDontSee('12:00 PM – 1:00 PM')->assertDontSee('Someone Else')->assertDontSee('Not for Aina');
        $page->assertDontSee($this->client->email);
    }

    public function test_the_bookings_page_only_points_to_the_requests_and_the_sidebar_has_the_page(): void
    {
        $this->as()->get(route('counselor.appointments'))->assertOk()->assertDontSee('time request is waiting')->assertSee('Time requests');

        $this->request();

        $this->as()->get(route('counselor.appointments'))->assertOk()->assertSee('1 time request is waiting for your answer.')->assertSee(route('counselor.requests'), false)->assertDontSee('About exams');
    }

    // ---- approving -------------------------------------------------------------------------------------------------

    public function test_approving_books_the_session_for_the_client(): void
    {
        Notification::fake();
        $request = $this->request();

        $this->approve($request)->assertSessionHasNoErrors()->assertSessionHas('status');

        $request->refresh();
        $this->assertSame(BookingRequestStatus::Approved, $request->status);
        $this->assertNotNull($request->responded_at);

        $booking = Booking::sole();
        $this->assertSame($booking->id, $request->booking_id);
        $this->assertSame(BookingMode::RequestBased, $booking->booking_mode);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($this->client->id, $booking->user_id);
        $this->assertSame($this->aina->user_id, $booking->counselor_user_id);
        $this->assertSame($this->platform->id, $booking->org_id);

        $entry = CalendarEntry::sole();
        $this->assertSame(CalendarEntryType::RequestedSession, $entry->entry_type);
        $this->assertFalse($entry->is_available);
        $this->assertSame($entry->id, $booking->calendar_entry_id);
        $this->assertSame('2026-11-05', $entry->entry_date->format('Y-m-d'));
        $this->assertSame('10:00:00', $entry->start_time);
        $this->assertSame(60, $entry->duration_minutes);

        Notification::assertSentTo($this->client, TimeRequestAnsweredClient::class, function (TimeRequestAnsweredClient $n) {
            $text = implode("\n", [$n->toMail($this->client)->subject, ...$n->toMail($this->client)->introLines, ...$n->toMail($this->client)->outroLines]);
            $this->assertStringContainsString('approved', $text);
            $this->assertStringContainsString('Aina', $text);
            $this->assertStringContainsString('Thursday, 5 November 2026', $text);
            $this->assertStringNotContainsString('http', $text);

            return true;
        });
    }

    public function test_the_clients_app_then_shows_it_as_an_ordinary_booking_and_the_request_points_at_it(): void
    {
        $request = $this->request();
        $this->approve($request);

        Sanctum::actingAs($this->client);
        $list = $this->getJson('/api/v1/bookings?scope=upcoming')->assertOk()->assertJsonCount(1, 'data');
        $id = $list->json('data.0.id');
        $this->getJson('/api/v1/bookings/'.$id)->assertOk()->assertJsonPath('booking.status', 'confirmed')->assertJsonPath('booking.starts_at', '2026-11-05T10:00:00+08:00')
            ->assertJsonPath('booking.mode', 'online')->assertJsonPath('booking.can_cancel', true);
        $this->getJson('/api/v1/requests/'.$request->public_id)->assertOk()->assertJsonPath('request.status', 'approved')->assertJsonPath('request.booking_id', $id);
    }

    public function test_an_approved_time_is_never_offered_as_a_slot_and_blocks_the_calendar(): void
    {
        $this->approve($this->request());
        $this->slot($this->aina, $this->platform, '2026-11-05 11:10');     // inside the buffer of the approved session
        $this->slot($this->aina, $this->platform, '2026-11-05 14:00');

        Sanctum::actingAs(User::factory()->create());
        $starts = $this->startsOf($this->availability($this->aina));

        $this->assertNotContains('2026-11-05 10:00', $starts);
        $this->assertNotContains('2026-11-05 11:10', $starts, 'the buffer applies to an approved session');
        $this->assertContains('2026-11-05 14:00', $starts);
    }

    public function test_cancelling_the_booking_does_not_turn_the_time_into_an_open_slot(): void
    {
        $this->approve($this->request());

        Sanctum::actingAs($this->client);
        $id = $this->getJson('/api/v1/bookings?scope=upcoming')->json('data.0.id');
        $this->postJson('/api/v1/bookings/'.$id.'/cancel')->assertOk()->assertJsonPath('booking.status', 'cancelled');

        $this->assertTrue(CalendarEntry::sole()->is_available, 'the contract (B9) still gives the time back');
        Sanctum::actingAs(User::factory()->create());
        $this->assertNotContains('2026-11-05 10:00', $this->startsOf($this->availability($this->aina)), 'but it was never a slot, so nobody is offered it');
    }

    public function test_approving_and_declining_are_also_news_inside_the_app_once_each(): void
    {
        Notification::fake();
        $approved = $this->request();
        $declined = $this->request(['requested_date' => '2026-11-06']);

        $this->approve($approved);
        $this->approve($approved); // a repeat adds nothing
        $this->decline($declined, 'Not that day');

        $rows = \App\Models\UserNotification::query()->where('recipient_user_id', $this->client->id)->orderBy('id')->get();

        $this->assertSame(['request.approved', 'request.declined'], $rows->pluck('type')->all());
        $this->assertSame([$approved->id, $declined->id], $rows->pluck('target_id')->all());
        $this->assertSame('Aina', $rows[0]->label, "the counselor's name only: no time, no reason");
        $this->assertStringNotContainsString('Not that day', json_encode($rows->toArray()));
        $this->assertSame(0, \App\Models\UserNotification::query()->where('recipient_user_id', $this->aina->user_id)->count(), 'the counselor is not told here');
    }

    public function test_approving_and_declining_are_audited_with_ids_only(): void
    {
        Notification::fake();
        $approved = $this->request();
        $declined = $this->request(['requested_time' => '14:00:00', 'message' => 'A private worry']);

        $this->approve($approved)->assertSessionHasNoErrors();
        $this->decline($declined, 'Sorry, I am full that week.')->assertSessionHasNoErrors();

        $yes = AuditLog::where('action', 'request.approved')->sole();
        $this->assertSame($approved->id, $yes->metadata['request_id']);
        $this->assertSame(Booking::sole()->id, $yes->metadata['booking_id']);
        $this->assertSame($this->aina->user_id, $yes->actor_user_id);

        $no = AuditLog::where('action', 'request.declined')->sole();
        $this->assertSame($declined->id, $no->metadata['request_id']);
        $this->assertTrue($no->metadata['has_reason']);

        $everything = json_encode([$yes->getAttributes(), $no->getAttributes()]);
        foreach (['Siti', 'private worry', 'I am full'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
    }

    public function test_a_repeat_or_a_refused_answer_writes_no_second_entry(): void
    {
        Notification::fake();
        $request = $this->request();
        $this->approve($request);
        $this->approve($request);
        $this->decline($request)->assertSessionHasErrors();

        $this->assertSame(1, AuditLog::where('action', 'request.approved')->count());
        $this->assertSame(0, AuditLog::where('action', 'request.declined')->count());
    }

    // ---- the rules at the moment of approving ---------------------------------------------------------------------------

    /** Approving must be refused, nothing booked, nothing written, and the request stays waiting. */
    private function assertRefused(BookingRequest $request): void
    {
        $this->approve($request)->assertSessionHasErrors('session');

        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, CalendarEntry::count());
        $this->assertSame(0, AuditLog::where('action', 'request.approved')->count());
    }

    public function test_it_is_refused_when_the_organization_is_no_longer_active(): void
    {
        Notification::fake();
        $request = $this->request();
        $this->platform->update(['subscription_status' => OrgStatus::Suspended]);

        $this->assertRefused($request);
    }

    public function test_it_is_refused_when_the_counselor_is_no_longer_approved(): void
    {
        Notification::fake();
        $request = $this->request();
        $this->aina->update(['verification_status' => VerificationStatus::Suspended]);

        $this->assertRefused($request);
    }

    public function test_it_is_refused_beyond_the_booking_horizon(): void
    {
        Notification::fake();
        $this->assertRefused($this->request(['requested_date' => '2026-12-20']));   // 48 days ahead; the horizon is 30
    }

    public function test_it_is_refused_when_the_weekly_hours_changed_or_were_removed(): void
    {
        Notification::fake();
        $sameLength = $this->request();
        $shortLines = $this->request(['requested_time' => '11:00:00']);

        CounselorWeeklyHour::query()->where('weekday', 4)->update(['slot_minutes' => 30]);   // Thursday's pattern is now 30-minute times
        $this->assertRefused($sameLength);

        CounselorWeeklyHour::query()->delete();
        $this->approve($shortLines)->assertSessionHasErrors('session');
        $this->assertSame(BookingRequestStatus::Pending, $shortLines->fresh()->status);
        $this->assertSame(0, Booking::count());
    }

    public function test_the_minimum_notice_is_not_applied_again_a_close_request_can_still_be_approved(): void
    {
        Notification::fake();
        // 10:00 today is one hour ahead, closer than the two hours a client must give; the counselor may still say yes.
        $close = $this->request(['requested_date' => '2026-11-02', 'requested_time' => '10:00:00', 'expires_at' => now()->addHour()]);

        $this->approve($close)->assertSessionHasNoErrors();

        $this->assertSame(BookingRequestStatus::Approved, $close->fresh()->status);
        $this->assertSame(1, Booking::count());
    }

    public function test_approving_twice_is_harmless(): void
    {
        Notification::fake();
        $request = $this->request();

        $this->approve($request)->assertSessionHasNoErrors();
        $this->approve($request)->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
        $this->assertSame(1, CalendarEntry::count());
        Notification::assertSentToTimes($this->client, TimeRequestAnsweredClient::class, 1);
    }

    public function test_it_is_refused_when_the_time_no_longer_fits_and_nothing_changes(): void
    {
        Notification::fake();
        $cases = [];

        // 1. The counselor filled the time meanwhile.
        $cases[] = [$this->request(), fn () => $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'))];
        // 2. A personal block went on top.
        $cases[] = [$this->request(['requested_time' => '13:00:00']), fn () => $this->block($this->aina, '2026-11-05 13:00')];
        // 3. The client booked something else at that time.
        $other = $this->counselor('Bakar', [$this->platform]);
        $cases[] = [$this->request(['requested_time' => '15:00:00']), fn () => $this->book($this->slot($other, $this->platform, '2026-11-05 15:00'), $this->client)];
        // 4. The counselor no longer works in that organization.
        $cases[] = [$this->request(['requested_time' => '17:00:00', 'context_org_id' => $this->open->id]), fn () => null];

        foreach ($cases as [$request, $change]) {
            $change();
            $this->approve($request)->assertSessionHasErrors('session');
            $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
            $this->assertNull($request->fresh()->booking_id);
        }

        $this->assertSame(0, Booking::where('booking_mode', 'request_based')->count());
        $this->assertSame(0, CalendarEntry::where('entry_type', 'requested_session')->count());
        Notification::assertNothingSent();
    }

    public function test_a_client_at_the_upcoming_cap_cannot_be_approved(): void
    {
        $other = $this->counselor('Bakar', [$this->platform]);
        foreach (['2026-11-09 10:00', '2026-11-10 10:00', '2026-11-11 10:00'] as $when) {
            $this->book($this->slot($other, $this->platform, $when), $this->client);
        }
        $request = $this->request();

        $this->approve($request)->assertSessionHasErrors('session');

        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
    }

    public function test_an_expired_withdrawn_or_started_request_cannot_be_approved(): void
    {
        $expired = $this->request(['expires_at' => now()->subMinute()]);
        $withdrawn = $this->request(['requested_time' => '11:00:00', 'status' => BookingRequestStatus::Withdrawn]);
        $declined = $this->request(['requested_time' => '12:00:00', 'status' => BookingRequestStatus::Rejected]);
        $started = $this->request(['requested_date' => '2026-11-02', 'requested_time' => '08:00:00', 'expires_at' => now()->addHours(2)]);

        foreach ([$expired, $withdrawn, $declined, $started] as $request) {
            $this->approve($request)->assertSessionHasErrors('session');
        }

        $this->assertSame(0, Booking::count());
    }

    public function test_a_failure_while_booking_rolls_the_whole_approval_back(): void
    {
        $request = $this->request();

        DB::listen(function ($query) {
            if (str_contains(self::plainSql($query->sql), 'insert into bookings')) {
                throw new RuntimeException('disk full');
            }
        });

        $this->approve($request)->assertStatus(500);

        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
        $this->assertSame(0, CalendarEntry::count(), 'the entry made for it was rolled back too');
    }

    // ---- declining -------------------------------------------------------------------------------------------------

    public function test_declining_closes_it_with_the_reason_the_client_is_shown(): void
    {
        Notification::fake();
        $request = $this->request();

        $this->decline($request, 'I am away that week.')->assertSessionHasNoErrors()->assertSessionHas('status');

        $request->refresh();
        $this->assertSame(BookingRequestStatus::Rejected, $request->status);
        $this->assertSame('I am away that week.', $request->response_reason);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, CalendarEntry::count());

        Notification::assertSentTo($this->client, TimeRequestAnsweredClient::class, function (TimeRequestAnsweredClient $n) {
            $text = implode("\n", [$n->toMail($this->client)->subject, ...$n->toMail($this->client)->introLines, ...$n->toMail($this->client)->outroLines]);
            $this->assertStringContainsString('declined', $text);
            $this->assertStringContainsString('I am away that week.', $text);

            return true;
        });

        Sanctum::actingAs($this->client);
        $this->getJson('/api/v1/requests/'.$request->public_id)->assertOk()->assertJsonPath('request.status', 'rejected')->assertJsonPath('request.response_reason', 'I am away that week.');
    }

    public function test_the_reason_is_optional_and_limited(): void
    {
        $a = $this->request();
        $b = $this->request(['requested_time' => '11:00:00']);

        $this->decline($a)->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->response_reason);

        $this->decline($b, str_repeat('a', 301))->assertSessionHasErrors('reason');
        $this->assertSame(BookingRequestStatus::Pending, $b->fresh()->status);
    }

    public function test_declining_twice_is_harmless_and_an_answered_request_cannot_be_answered_the_other_way(): void
    {
        Notification::fake();
        $declined = $this->request();
        $approved = $this->request(['requested_time' => '12:00:00']);

        $this->decline($declined, 'No.');
        $this->decline($declined, 'Still no.')->assertSessionHasNoErrors();
        $this->assertSame('No.', $declined->fresh()->response_reason);
        Notification::assertSentToTimes($this->client, TimeRequestAnsweredClient::class, 1);

        $this->approve($declined)->assertSessionHasErrors('session');
        $this->approve($approved);
        $this->decline($approved)->assertSessionHasErrors('session');
        $this->assertSame(BookingRequestStatus::Approved, $approved->fresh()->status);
    }

    // ---- who ---------------------------------------------------------------------------------------------------------------

    public function test_another_counselors_request_and_an_unknown_one_are_the_same_404(): void
    {
        $other = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->request(['counselor_user_id' => $other->user_id]);

        $this->approve($theirs)->assertNotFound();
        $this->decline($theirs)->assertNotFound();
        $this->as()->post(route('counselor.requests.approve', '01hzzzzzzzzzzzzzzzzzzzzzzz'))->assertNotFound();

        $this->assertSame(BookingRequestStatus::Pending, $theirs->fresh()->status);
        $this->assertSame(0, Booking::count());
    }

    public function test_only_counselors_may_answer(): void
    {
        $request = $this->request();

        $this->actingAs(User::factory()->create())->post(route('counselor.requests.approve', $request->public_id))->assertStatus(302);
        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status);
    }
}
