<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/v1/bookings and GET /api/v1/bookings/{id}: a person's OWN bookings, read only. The clock stands at Monday 2 November 2026,
 * 09:00 Malaysian time (see AvailabilityFixtures).
 */
class MyBookingsApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

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

    private ?\App\Models\CounselorProfile $aina = null;

    private function aina()
    {
        return $this->aina ??= $this->counselor('Aina', [$this->platform]);
    }

    /** A booking of mine for a slot at $when, optionally with no meeting link yet. */
    private function mine(string $when, array $slot = [], BookingStatus $status = BookingStatus::Confirmed, bool $link = true, ?User $who = null): Booking
    {
        $aina = $this->aina();
        $entry = $this->slot($aina, $this->platform, $when, $slot['mode'] ?? SessionMode::Online, $slot['place'] ?? null, minutes: $slot['minutes'] ?? 60);
        $booking = $this->book($entry, $who ?? $this->me, $status);

        if (! $link) {
            $booking->forceFill(['meeting_url' => null, 'meeting_provider' => null])->save();
        }

        return $booking;
    }

    private function list(string $scope, array $query = [])
    {
        return $this->getJson('/api/v1/bookings?'.http_build_query(['scope' => $scope] + $query));
    }

    private function show(Booking $booking)
    {
        return $this->getJson('/api/v1/bookings/'.$booking->public_id);
    }

    private function me(): void
    {
        $this->me = $this->client();
    }

    // ---- who may ask ---------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401(): void
    {
        $this->getJson('/api/v1/bookings?scope=upcoming')->assertUnauthorized();
        $this->getJson('/api/v1/bookings/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertUnauthorized();
    }

    public function test_an_unconfirmed_email_gets_403_and_maintenance_503(): void
    {
        $this->client(verified: false);
        $this->list('upcoming')->assertForbidden()->assertJson(['code' => 'email_not_verified']);

        $this->me();
        app(PlatformSettings::class)->setMaintenance(null, true);
        $this->list('upcoming')->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_the_scope_is_required_and_checked(): void
    {
        $this->me();

        $this->getJson('/api/v1/bookings')->assertStatus(422);
        $this->getJson('/api/v1/bookings?scope=everything')->assertStatus(422);
        $this->getJson('/api/v1/bookings?scope=past&page=0')->assertStatus(422);
    }

    // ---- upcoming and past ------------------------------------------------------------------------------------------

    public function test_upcoming_is_confirmed_sessions_that_have_not_ended_soonest_first(): void
    {
        $this->me();
        $later = $this->mine('2026-11-10 10:00');
        $sooner = $this->mine('2026-11-03 10:00');
        $underWay = $this->mine('2026-11-02 08:30');                  // started 30 minutes ago, ends 09:30: still upcoming
        $ended = $this->mine('2026-11-02 07:00');                     // ended at 08:00
        $cancelled = $this->mine('2026-11-04 10:00', status: BookingStatus::Cancelled);
        $completed = $this->mine('2026-11-01 10:00', status: BookingStatus::Completed);

        $ids = collect($this->list('upcoming')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$underWay->public_id, $sooner->public_id, $later->public_id], $ids);
        $this->assertNotContains($ended->public_id, $ids);
        $this->assertNotContains($cancelled->public_id, $ids);
        $this->assertNotContains($completed->public_id, $ids);
    }

    public function test_past_is_everything_else_newest_first_and_paged(): void
    {
        $this->me();
        $upcoming = $this->mine('2026-11-03 10:00');
        $cancelled = $this->mine('2026-11-04 10:00', status: BookingStatus::Cancelled);
        $ended = $this->mine('2026-11-02 07:00');
        $noShow = $this->mine('2026-10-20 10:00', status: BookingStatus::NoShow);
        $done = $this->mine('2026-10-25 10:00', status: BookingStatus::Completed);

        $response = $this->list('past')->assertOk();

        $this->assertSame([$cancelled->public_id, $ended->public_id, $done->public_id, $noShow->public_id], collect($response->json('data'))->pluck('id')->all());
        $this->assertSame([1, 1, 4], [$response->json('page'), $response->json('last_page'), $response->json('total')]);
        $this->assertNotContains($upcoming->public_id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_past_comes_in_pages_of_twenty_and_nothing_repeats_or_is_missed(): void
    {
        $this->me();
        $aina = $this->aina();

        foreach (range(1, 25) as $i) {
            $entry = $this->slot($aina, $this->platform, Carbon::parse('2026-09-01 08:00', 'Asia/Kuala_Lumpur')->addDays($i)->format('Y-m-d H:i'));
            $this->book($entry, $this->me, BookingStatus::Completed);
        }

        $first = $this->list('past')->assertOk();
        $second = $this->list('past', ['page' => 2])->assertOk();

        $this->assertCount(20, $first->json('data'));
        $this->assertCount(5, $second->json('data'));
        $this->assertSame(2, $first->json('last_page'));
        $this->assertSame(25, $first->json('total'));

        $ids = array_merge(collect($first->json('data'))->pluck('id')->all(), collect($second->json('data'))->pluck('id')->all());
        $this->assertCount(25, array_unique($ids));
        $dates = collect(array_merge($first->json('data'), $second->json('data')))->pluck('starts_at')->all();
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates, 'newest first across pages');
    }

    public function test_only_my_own_bookings_are_listed(): void
    {
        $this->me();
        $other = User::factory()->create();
        $mine = $this->mine('2026-11-03 10:00');
        $this->mine('2026-11-04 10:00', who: $other);

        Sanctum::actingAs($this->me);
        $this->assertSame([$mine->public_id], collect($this->list('upcoming')->json('data'))->pluck('id')->all());
        $this->assertSame([], $this->list('past')->json('data'));
    }

    public function test_the_starting_soon_flag(): void
    {
        $this->me();
        $soon = $this->mine('2026-11-02 09:45');               // in 45 minutes
        $notSoon = $this->mine('2026-11-02 14:00');
        $underWay = $this->mine('2026-11-02 08:30');

        $flags = collect($this->list('upcoming')->json('data'))->mapWithKeys(fn ($b) => [$b['id'] => $b['starting_soon']])->all();

        $this->assertTrue($flags[$soon->public_id]);
        $this->assertFalse($flags[$notSoon->public_id]);
        $this->assertTrue($flags[$underWay->public_id]);
    }

    public function test_a_summary_has_the_session_the_counselor_and_where(): void
    {
        $this->me();
        $place = $this->place($this->platform);
        $booking = $this->mine('2026-11-03 10:00', ['mode' => SessionMode::Physical, 'place' => $place, 'minutes' => 50]);

        $item = $this->list('upcoming')->json('data.0');

        $this->assertSame($booking->public_id, $item['id']);
        $this->assertSame('confirmed', $item['status']);
        $this->assertSame('2026-11-03T10:00:00+08:00', $item['starts_at']);
        $this->assertSame('2026-11-03T10:50:00+08:00', $item['ends_at']);
        $this->assertSame(50, $item['duration_minutes']);
        $this->assertSame('physical', $item['mode']);
        $this->assertSame('Aina', $item['counselor']['display_name']);
        $this->assertSame(CalendarEntry::first()->counselor->counselorProfile->public_id, $item['counselor']['id']);
        $this->assertSame(['name' => 'Klinik Damai Kuantan', 'city' => 'Kuantan'], $item['location']);
    }

    public function test_the_list_costs_the_same_number_of_queries_however_many_bookings_there_are(): void
    {
        $this->me();
        $this->mine('2026-11-03 10:00');
        $this->list('upcoming')->assertOk();                      // the first request of a test also loads settings: not counted
        DB::enableQueryLog();
        $this->list('upcoming')->assertOk();
        $one = count(DB::getQueryLog());

        foreach (range(4, 8) as $day) {
            $this->mine("2026-11-0{$day} 10:00");
        }
        DB::flushQueryLog();
        $this->list('upcoming')->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($one, $many, 'no query per booking: '.json_encode(array_column(DB::getQueryLog(), 'query')));
    }

    // ---- one booking -------------------------------------------------------------------------------------------------

    public function test_another_persons_booking_and_an_unknown_one_are_the_same_404(): void
    {
        $this->me();
        $other = User::factory()->create();
        $theirs = $this->mine('2026-11-03 10:00', who: $other);

        $stranger = $this->show($theirs);
        $unknown = $this->getJson('/api/v1/bookings/01hzzzzzzzzzzzzzzzzzzzzzzz');

        $stranger->assertNotFound()->assertJson(['code' => 'booking_not_found']);
        $this->assertSame($unknown->content(), $stranger->content());
        $this->getJson('/api/v1/bookings/12')->assertNotFound(); // not a public id: no route, no hint
    }

    public function test_the_meeting_link_appears_only_inside_its_window(): void
    {
        $this->me();
        $booking = $this->mine('2026-11-03 10:00');               // a link is added

        $show = fn () => $this->show($booking)->assertOk()->json('booking.meeting');

        // A day before: opens later, no link in the answer at all.
        $before = $show();
        $this->assertSame('opens_later', $before['state']);
        $this->assertNull($before['url']);
        $this->assertSame('2026-11-03T09:45:00+08:00', $before['opens_at']);
        $this->assertStringNotContainsString('secret-room', json_encode($before));

        // 16 minutes before: still not.
        Carbon::setTestNow(Carbon::parse('2026-11-03 09:44:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame('opens_later', $show()['state']);
        $this->assertNull($show()['url']);

        // 15 minutes before: the link.
        Carbon::setTestNow(Carbon::parse('2026-11-03 09:45:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(['available', 'https://meet.example/secret-room-123'], [$show()['state'], $show()['url']]);

        // During, and at the very end minus a minute.
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:59:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame('available', $show()['state']);

        // At the end: gone again.
        Carbon::setTestNow(Carbon::parse('2026-11-03 11:00:00', 'Asia/Kuala_Lumpur'));
        $ended = $show();
        $this->assertSame('ended', $ended['state']);
        $this->assertNull($ended['url']);
    }

    public function test_no_link_added_yet_is_its_own_state_inside_the_window(): void
    {
        $this->me();
        $booking = $this->mine('2026-11-03 10:00', link: false);

        Carbon::setTestNow(Carbon::parse('2026-11-03 09:50:00', 'Asia/Kuala_Lumpur'));
        $meeting = $this->show($booking)->json('booking.meeting');

        $this->assertSame('not_added', $meeting['state']);
        $this->assertNull($meeting['url']);
    }

    public function test_in_person_has_the_full_address_and_no_meeting_and_online_has_no_address(): void
    {
        $this->me();
        $place = $this->place($this->platform);
        $inPerson = $this->mine('2026-11-03 10:00', ['mode' => SessionMode::Physical, 'place' => $place]);
        $online = $this->mine('2026-11-04 10:00');

        $physical = $this->show($inPerson)->assertOk()->json('booking');
        $this->assertNull($physical['meeting']);
        $this->assertSame('No 12, Jalan Rahsia Besar, Taman Sulit', $physical['place']['address']);
        $this->assertSame('25300', $physical['place']['postcode']);
        $this->assertEqualsWithDelta(3.8077, $physical['place']['latitude'], 0.0001);

        $virtual = $this->show($online)->assertOk()->json('booking');
        $this->assertNull($virtual['place']);
        $this->assertNotNull($virtual['meeting']);
    }

    public function test_a_cancelled_booking_shows_neither_link_nor_address(): void
    {
        $this->me();
        $place = $this->place($this->platform);
        $cancelledOnline = $this->mine('2026-11-03 10:00', status: BookingStatus::Cancelled);
        $cancelledInPerson = $this->mine('2026-11-04 10:00', ['mode' => SessionMode::Physical, 'place' => $place], BookingStatus::Cancelled);
        Carbon::setTestNow(Carbon::parse('2026-11-03 09:50:00', 'Asia/Kuala_Lumpur'));

        $a = $this->show($cancelledOnline)->assertOk()->json('booking');
        $b = $this->show($cancelledInPerson)->assertOk()->json('booking');

        $this->assertNull($a['meeting']);
        $this->assertNull($b['place']);
        $this->assertStringNotContainsString('secret-room', json_encode($a));
        $this->assertStringNotContainsString('Jalan Rahsia', json_encode($b));
        $this->assertFalse($a['can_cancel']);
    }

    public function test_the_cancellation_rule_and_the_reschedules_left_come_from_the_server(): void
    {
        $this->me();
        $far = $this->mine('2026-11-05 10:00');                         // more than 24 hours away
        $near = $this->mine('2026-11-02 19:00');                        // 10 hours away: inside the window
        $far->forceFill(['reschedule_count' => 1])->save();

        $a = $this->show($far)->assertOk()->json('booking');
        $this->assertTrue($a['can_cancel']);
        $this->assertSame('2026-11-04T10:00:00+08:00', $a['cancellation_deadline_at']);
        $this->assertSame(24, $a['cancellation_hours']);
        $this->assertSame(1, $a['reschedules_left']);

        $b = $this->show($near)->assertOk()->json('booking');
        $this->assertFalse($b['can_cancel']);
        $this->assertNull($b['cancellation_deadline_at']);
        $this->assertSame(2, $b['reschedules_left']);
    }

    public function test_nothing_private_is_in_the_answers(): void
    {
        $this->me();
        $uni = $this->closed;
        $this->me = $this->client([$uni]);
        $aina = $this->counselor('Aina', [$uni]);
        $place = $this->place($uni);
        $entry = $this->slot($aina, $uni, '2026-11-03 10:00', SessionMode::Physical, $place);
        $booking = $this->book($entry, $this->me);

        foreach ([$this->list('upcoming'), $this->show($booking)] as $response) {
            [$keys, $values] = $this->flatten($response->assertOk()->json());
            $text = json_encode($response->json());

            foreach (['email', 'phone', 'org_id', 'user_id', 'counselor_user_id', 'organization', 'context', 'password', 'kb_number', 'pa_number'] as $forbidden) {
                $this->assertNotContains($forbidden, $keys, "key {$forbidden}");
            }
            $this->assertStringNotContainsString($this->me->email, $text);
            $this->assertStringNotContainsString('counselor.example', $text);
        }

        // Row ids are numbers; every id in the answers is a 26 character public id.
        $id = $this->list('upcoming')->json('data.0.id');
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $id);
    }

    public function test_a_hidden_counselors_booking_still_shows_to_its_owner(): void
    {
        $this->me();
        $booking = $this->mine('2026-11-03 10:00');
        CalendarEntry::first()->counselor->counselorProfile->update(['verification_status' => 'suspended']);

        $this->show($booking)->assertOk()->assertJsonPath('booking.counselor.display_name', 'Aina');
    }
}
