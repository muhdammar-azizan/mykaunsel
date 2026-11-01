<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\SessionCancelledByCounselor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** A counselor cancels one of their own sessions on the portal, and opens one session's details. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorCancelSessionTest extends TestCase
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

    private function mine(string $when, BookingStatus $status = BookingStatus::Confirmed, ?CounselorProfile $who = null): Booking
    {
        return $this->book($this->slot($who ?? $this->aina, $this->platform, $when), User::factory()->create(['name' => 'Siti Client']), $status);
    }

    private function cancel(Booking $booking, string $reason = 'I am unwell today.', ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.appointments.cancel', $booking->public_id), ['reason' => $reason]);
    }

    public function test_cancelling_records_who_and_why_gives_the_time_back_and_tells_the_client(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');
        $slot = CalendarEntry::find($booking->calendar_entry_id);

        $this->cancel($booking)->assertRedirect(route('counselor.appointments.show', $booking->public_id))->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('counselor', $booking->cancelled_by);
        $this->assertSame('I am unwell today.', $booking->cancel_reason);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertTrue((bool) $slot->fresh()->is_available);

        Notification::assertSentTo($booking->user, SessionCancelledByCounselor::class);
        $this->assertSame(1, UserNotification::query()->ownedBy($booking->user)->where('type', 'booking.cancelled')->count());
    }

    public function test_cancelling_twice_changes_and_sends_nothing_more(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');

        $this->cancel($booking);
        $this->cancel($booking, 'A different reason');

        $this->assertSame('I am unwell today.', $booking->fresh()->cancel_reason);
        Notification::assertSentToTimes($booking->user, SessionCancelledByCounselor::class, 1);
    }

    public function test_a_reason_is_needed_and_a_session_that_has_ended_or_is_marked_can_not_be_cancelled(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->cancel($booking, '')->assertSessionHasErrors('reason');
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        $ended = $this->mine('2026-11-02 07:00');
        $this->cancel($ended)->assertSessionHasErrors('session');
        $this->assertSame(BookingStatus::Confirmed, $ended->fresh()->status);

        $done = $this->mine('2026-10-30 10:00', BookingStatus::Completed);
        $this->cancel($done)->assertSessionHasErrors('session');
        $this->assertSame(BookingStatus::Completed, $done->fresh()->status);
    }

    public function test_someone_elses_session_is_a_404_and_is_not_touched(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->mine('2026-11-05 10:00', who: $bakar);

        $this->cancel($theirs)->assertNotFound();
        $this->as()->get(route('counselor.appointments.show', $theirs->public_id))->assertNotFound();
        $this->assertSame(BookingStatus::Confirmed, $theirs->fresh()->status);
    }

    public function test_the_details_page_offers_what_fits_the_state(): void
    {
        $upcoming = $this->mine('2026-11-05 10:00');
        $ended = $this->mine('2026-11-02 07:00');

        $this->as()->get(route('counselor.appointments.show', $upcoming->public_id))->assertOk()
            ->assertSee('Siti Client')->assertSee('Cancel this session')->assertSee('Save link')->assertDontSee('What happened?');
        $this->as()->get(route('counselor.appointments.show', $ended->public_id))->assertOk()
            ->assertSee('What happened?')->assertDontSee('Cancel this session');

        $this->cancel($upcoming);
        $this->as()->get(route('counselor.appointments.show', $upcoming->public_id))->assertOk()
            ->assertSee('Cancelled by')->assertSee('I am unwell today.')->assertDontSee('Cancel this session');
    }

    public function test_the_list_links_to_each_session(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->as()->get(route('counselor.appointments'))->assertOk()->assertSee(route('counselor.appointments.show', $booking->public_id), false);
    }
}
