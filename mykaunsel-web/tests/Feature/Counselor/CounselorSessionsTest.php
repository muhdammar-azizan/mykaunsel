<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor's own sessions on the web portal: the three lists, the meeting link, and marking a finished session completed or no-show.
 * The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class CounselorSessionsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform]);
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

    private function mine(string $when, BookingStatus $status = BookingStatus::Confirmed, ?CounselorProfile $who = null, ?User $client = null): Booking
    {
        return $this->book($this->slot($who ?? $this->aina, $this->platform, $when), $client ?? User::factory()->create(['name' => 'Siti Client']), $status);
    }

    private function meeting(Booking $booking, array $fields)
    {
        return $this->as()->put(route('counselor.appointments.meeting', $booking->public_id), $fields);
    }

    private function outcome(Booking $booking, string $outcome, ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.appointments.outcome', $booking->public_id), ['outcome' => $outcome]);
    }

    // ---- the page --------------------------------------------------------------------------------------------------

    public function test_the_page_lists_upcoming_to_mark_and_past_with_the_clients_display_name_only(): void
    {
        $this->mine('2026-11-05 10:00');
        $this->mine('2026-11-02 07:00');                                  // ended an hour ago, still confirmed
        $this->mine('2026-10-30 10:00', BookingStatus::Completed);
        $this->mine('2026-10-29 10:00', BookingStatus::NoShow);
        $this->mine('2026-11-08 10:00', BookingStatus::Cancelled);

        $page = $this->as()->get(route('counselor.appointments'))->assertOk();

        $page->assertSeeInOrder(['Needs marking', 'Mark completed', 'Upcoming', 'Thu, 5 Nov 2026', 'Past']);
        $page->assertSee('Siti Client');
        $page->assertSee('Completed')->assertSee('No-show')->assertSee('Cancelled');
        $page->assertDontSee(User::where('name', 'Siti Client')->first()->email);
    }

    public function test_it_shows_only_the_counselors_own_sessions(): void
    {
        $other = $this->counselor('Bakar', [$this->platform]);
        $this->mine('2026-11-05 10:00', who: $other, client: User::factory()->create(['name' => 'Someone Else']));

        $this->as()->get(route('counselor.appointments'))->assertOk()->assertDontSee('Someone Else')->assertSee('No upcoming sessions.');
    }

    public function test_only_counselors_may_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get(route('counselor.appointments'))->assertStatus(302);
        $this->get(route('counselor.appointments'))->assertRedirect();
    }

    // ---- the meeting link --------------------------------------------------------------------------------------------

    public function test_a_link_can_be_saved_and_is_shown_back_on_the_page(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->meeting($booking, ['meeting_provider' => 'Zoom', 'meeting_url' => 'https://zoom.example/j/123'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame('Zoom', $booking->fresh()->meeting_provider);
        $this->assertSame('https://zoom.example/j/123', $booking->fresh()->meeting_url);
        $this->as()->get(route('counselor.appointments'))->assertSee('https://zoom.example/j/123');
    }

    public function test_an_empty_link_removes_it(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->meeting($booking, ['meeting_provider' => 'Zoom', 'meeting_url' => ''])->assertSessionHasNoErrors();

        $this->assertNull($booking->fresh()->meeting_url);
        $this->assertNull($booking->fresh()->meeting_provider);
    }

    public function test_only_https_links_a_known_app_and_a_sane_length(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $before = $booking->fresh()->meeting_url;

        foreach ([
            ['meeting_provider' => 'Zoom', 'meeting_url' => 'http://zoom.example/j/1'],
            ['meeting_provider' => 'Zoom', 'meeting_url' => 'javascript:alert(1)'],
            ['meeting_provider' => 'Zoom', 'meeting_url' => 'not a link'],
            ['meeting_provider' => 'Zoom', 'meeting_url' => 'https://x.example/'.str_repeat('a', 300)],
            ['meeting_provider' => 'Skype 2', 'meeting_url' => 'https://x.example/ok'],
        ] as $fields) {
            $this->meeting($booking, $fields)->assertSessionHasErrors();
            $this->assertSame($before, $booking->fresh()->meeting_url);
        }
    }

    public function test_no_link_for_in_person_ended_or_not_confirmed_sessions(): void
    {
        $inPerson = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00', \App\Enums\SessionMode::Physical, $this->place($this->platform)));
        $ended = $this->mine('2026-11-02 07:00');
        $cancelled = $this->mine('2026-11-06 10:00', BookingStatus::Cancelled);

        foreach ([$inPerson, $ended, $cancelled] as $booking) {
            $before = $booking->fresh()->meeting_url;
            $this->meeting($booking, ['meeting_provider' => 'Zoom', 'meeting_url' => 'https://zoom.example/new'])->assertSessionHasErrors('session');
            $this->assertSame($before, $booking->fresh()->meeting_url);
        }
    }

    public function test_another_counselors_booking_is_a_404_and_unchanged(): void
    {
        $other = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->mine('2026-11-05 10:00', who: $other);
        $before = $theirs->fresh()->meeting_url;

        $this->meeting($theirs, ['meeting_provider' => 'Zoom', 'meeting_url' => 'https://zoom.example/x'])->assertNotFound();
        $this->outcome($theirs, 'completed')->assertNotFound();

        $this->assertSame($before, $theirs->fresh()->meeting_url);
        $this->assertSame(BookingStatus::Confirmed, $theirs->fresh()->status);
    }

    // ---- marking ---------------------------------------------------------------------------------------------------------

    public function test_an_ended_session_can_be_marked_completed_or_no_show(): void
    {
        $a = $this->mine('2026-11-02 07:00');
        $b = $this->mine('2026-11-01 07:00');

        $this->outcome($a, 'completed')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->outcome($b, 'no_show')->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Completed, $a->fresh()->status);
        $this->assertSame(BookingStatus::NoShow, $b->fresh()->status);
    }

    public function test_marking_does_not_touch_the_time(): void
    {
        $a = $this->mine('2026-11-02 07:00');
        $slot = CalendarEntry::sole();

        $this->outcome($a, 'completed');

        $this->assertFalse($slot->fresh()->is_available);
    }

    public function test_a_session_that_has_not_ended_cannot_be_marked(): void
    {
        $future = $this->mine('2026-11-05 10:00');
        $underway = $this->mine('2026-11-02 08:30');           // started, ends 09:30

        foreach ([$future, $underway] as $booking) {
            $this->outcome($booking, 'completed')->assertSessionHasErrors('session');
            $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        }
    }

    public function test_the_end_to_the_minute(): void
    {
        $booking = $this->mine('2026-11-02 08:00');            // ends 09:00 exactly: now

        $this->outcome($booking, 'completed')->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_saying_the_same_again_is_harmless_and_saying_something_else_is_refused(): void
    {
        $booking = $this->mine('2026-11-02 07:00');

        $this->outcome($booking, 'completed')->assertSessionHasNoErrors();
        $this->outcome($booking, 'completed')->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);

        $this->outcome($booking, 'no_show')->assertSessionHasErrors('session');
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_a_cancelled_session_cannot_be_marked_and_other_outcomes_are_not_accepted(): void
    {
        $cancelled = $this->mine('2026-11-01 07:00', BookingStatus::Cancelled);
        $ended = $this->mine('2026-11-02 07:00');

        $this->outcome($cancelled, 'completed')->assertSessionHasErrors('session');
        $this->assertSame(BookingStatus::Cancelled, $cancelled->fresh()->status);

        $this->outcome($ended, 'cancelled')->assertSessionHasErrors('outcome');
        $this->outcome($ended, 'confirmed')->assertSessionHasErrors('outcome');
        $this->assertSame(BookingStatus::Confirmed, $ended->fresh()->status);
    }

    public function test_the_clients_app_shows_a_marked_session_in_past(): void
    {
        $client = User::factory()->create();
        $booking = $this->mine('2026-11-02 07:00', client: $client);
        $this->outcome($booking, 'no_show');

        \Laravel\Sanctum\Sanctum::actingAs($client);
        $this->getJson('/api/v1/bookings?scope=past')->assertOk()->assertJsonPath('data.0.status', 'no_show');
        $this->getJson('/api/v1/bookings?scope=upcoming')->assertOk()->assertJsonCount(0, 'data');
    }
}
