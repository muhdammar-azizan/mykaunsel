<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor's planner: opening and removing their own bookable times. The clock stands at Monday 2 November 2026, 09:00 Malaysian time
 * (see AvailabilityFixtures).
 */
class CounselorPlannerTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform, $this->open]);
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

    private function open(array $over = [], ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.planner.store'), $over + [
            'context' => $this->platform->id, 'date' => '2026-11-05', 'start' => '10:00', 'duration' => 60, 'mode' => 'online',
        ]);
    }

    // ---- the page --------------------------------------------------------------------------------------------------

    public function test_the_page_lists_the_counselors_own_times_with_booked_and_open(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $this->book($this->slot($this->aina, $this->platform, '2026-11-06 10:00'));
        $other = $this->counselor('Bakar', [$this->platform]);
        $this->slot($other, $this->platform, '2026-11-07 15:00');

        $this->as()->get(route('counselor.planner'))->assertOk()
            ->assertSee('Thursday, 5 November 2026')->assertSee('10:00 AM – 11:00 AM')->assertSee('Open')->assertSee('Booked')
            ->assertDontSee('3:00 PM – 4:00 PM');
    }

    public function test_only_counselors_may_use_it(): void
    {
        $this->actingAs(User::factory()->create())->get(route('counselor.planner'))->assertStatus(302);
        $this->post(route('counselor.planner.store'), [])->assertRedirect();
    }

    // ---- opening ---------------------------------------------------------------------------------------------------

    public function test_a_time_can_be_opened_and_is_then_bookable_in_the_clients_app(): void
    {
        $this->open()->assertSessionHasNoErrors()->assertSessionHas('status');

        $entry = CalendarEntry::sole();
        $this->assertSame($this->aina->user_id, $entry->counselor_user_id);
        $this->assertSame($this->platform->id, $entry->context_org_id);
        $this->assertSame(SessionMode::Online, $entry->session_mode);
        $this->assertTrue($entry->is_available);
        $this->assertSame(60, $entry->duration_minutes);
        $this->assertSame(26, strlen($entry->public_id));

        $this->client();
        $this->getJson('/api/v1/counselors/'.$this->aina->public_id.'/availability')->assertOk();
        $this->assertContains('2026-11-05 10:00', $this->startsOf($this->availability($this->aina), 'available'));
    }

    public function test_an_in_person_time_needs_a_place_of_that_organization(): void
    {
        $place = $this->place($this->open, 'Kuantan', 'Pahang');

        $this->open(['context' => $this->open->id, 'mode' => 'physical', 'location' => $place->id])->assertSessionHasNoErrors();
        $this->assertSame($place->id, CalendarEntry::sole()->location_id);
        $this->assertSame($this->open->id, CalendarEntry::sole()->context_org_id);

        // No place, or a place that belongs to another organization.
        $other = $this->place($this->closed, 'Pekan', 'Pahang');
        $this->open(['date' => '2026-11-06', 'context' => $this->open->id, 'mode' => 'physical'])->assertSessionHasErrors('planner');
        $this->open(['date' => '2026-11-06', 'context' => $this->open->id, 'mode' => 'physical', 'location' => $other->id])->assertSessionHasErrors('planner');
        $this->open(['date' => '2026-11-06', 'context' => $this->platform->id, 'mode' => 'physical', 'location' => $place->id])->assertSessionHasErrors('planner');
        $this->assertSame(1, CalendarEntry::count());
    }

    public function test_an_online_time_never_keeps_a_place(): void
    {
        $place = $this->place($this->open);

        $this->open(['context' => $this->open->id, 'mode' => 'online', 'location' => $place->id])->assertSessionHasNoErrors();

        $this->assertNull(CalendarEntry::sole()->location_id);
    }

    public function test_only_a_context_the_counselor_works_in(): void
    {
        $this->open(['context' => $this->closed->id])->assertSessionHasErrors('planner');
        $this->open(['context' => 999999])->assertSessionHasErrors('planner');

        $this->assertSame(0, CalendarEntry::count());
    }

    public function test_not_in_the_past_and_not_too_far_ahead(): void
    {
        $this->open(['date' => '2026-11-02', 'start' => '08:00'])->assertSessionHasErrors('planner');
        $this->open(['date' => '2026-11-02', 'start' => '09:00'])->assertSessionHasErrors('planner');     // now is not later than now
        $this->open(['date' => '2026-10-20'])->assertSessionHasErrors('planner');
        $this->open(['date' => '2027-06-01'])->assertSessionHasErrors('planner');                         // beyond 180 days
        $this->assertSame(0, CalendarEntry::count());

        $this->open(['date' => '2026-11-02', 'start' => '09:05'])->assertSessionHasNoErrors();            // later today is fine
        $this->open(['date' => '2027-04-30'])->assertSessionHasNoErrors();                                 // day 179
    }

    public function test_fields_are_checked(): void
    {
        $this->open(['duration' => 61])->assertSessionHasErrors('duration');
        $this->open(['mode' => 'phone'])->assertSessionHasErrors('mode');
        $this->open(['date' => 'tomorrow'])->assertSessionHasErrors('date');
        $this->open(['start' => '25:00'])->assertSessionHasErrors('start');
        $this->open(['start' => '10:07'])->assertSessionHasErrors('planner');
        $this->assertSame(0, CalendarEntry::count());
    }

    // ---- the calendar rules ----------------------------------------------------------------------------------------

    public function test_it_cannot_sit_on_another_open_time_in_any_context(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');

        $this->open(['start' => '10:30'])->assertSessionHasErrors('planner');
        $this->open(['start' => '09:30'])->assertSessionHasErrors('planner');
        $this->open(['start' => '10:00', 'context' => $this->open->id])->assertSessionHasErrors('planner');
        $this->assertSame(1, CalendarEntry::count());

        // Touching at the edge is fine.
        $this->open(['start' => '11:00'])->assertSessionHasNoErrors();
        $this->assertSame(2, CalendarEntry::count());
    }

    public function test_it_keeps_the_buffer_around_a_booked_session_and_clear_of_personal_blocks(): void
    {
        $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'));       // 10:00-11:00, buffer 15 minutes
        $this->block($this->aina, '2026-11-06 14:00');

        $this->open(['start' => '11:10'])->assertSessionHasErrors('planner');
        $this->open(['start' => '11:15'])->assertSessionHasNoErrors();
        $this->open(['date' => '2026-11-06', 'start' => '14:30'])->assertSessionHasErrors('planner');
    }

    public function test_two_counselors_do_not_affect_each_other(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->slot($bakar, $this->platform, '2026-11-05 10:00');

        $this->open()->assertSessionHasNoErrors();

        $this->assertSame(2, CalendarEntry::count());
    }

    // ---- removing --------------------------------------------------------------------------------------------------------

    public function test_an_open_time_can_be_removed(): void
    {
        $slot = $this->slot($this->aina, $this->platform, '2026-11-05 10:00');

        $this->as()->delete(route('counselor.planner.destroy', $slot->public_id))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(0, CalendarEntry::count());
    }

    public function test_a_booked_time_cannot_be_removed(): void
    {
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'));

        $this->as()->delete(route('counselor.planner.destroy', CalendarEntry::sole()->public_id))->assertSessionHasErrors('planner');

        $this->assertSame(1, CalendarEntry::count());
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_a_time_that_once_had_a_booking_cannot_be_removed_even_if_it_was_cancelled(): void
    {
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'));
        $this->cancelBooking($booking);

        $this->as()->delete(route('counselor.planner.destroy', CalendarEntry::sole()->public_id))->assertSessionHasErrors('planner');

        $this->assertSame(1, CalendarEntry::count());
        $this->assertSame($booking->calendar_entry_id, Booking::sole()->calendar_entry_id);
    }

    public function test_another_counselors_time_a_block_and_an_unknown_one_are_the_same_404(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->slot($bakar, $this->platform, '2026-11-05 10:00');
        $block = $this->block($this->aina, '2026-11-06 14:00');

        $this->as()->delete(route('counselor.planner.destroy', $theirs->public_id))->assertNotFound();
        $this->as()->delete(route('counselor.planner.destroy', $block->public_id))->assertNotFound();
        $this->as()->delete(route('counselor.planner.destroy', '01hzzzzzzzzzzzzzzzzzzzzzzz'))->assertNotFound();

        $this->assertSame(1, CalendarEntry::where('entry_type', 'available_slot')->count());
        $this->assertNotNull(CalendarEntry::find($theirs->id));
        $this->assertNotNull(CalendarEntry::find($block->id));
    }
}
