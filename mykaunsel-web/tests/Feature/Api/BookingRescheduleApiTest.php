<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\User;
use App\Notifications\BookingRescheduledCounselor;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * POST /api/v1/bookings/{id}/reschedule: moving one's own booking to another time of the same counselor claims the new time and gives the
 * old one back (B9) in one transaction, under the same time limit as cancelling, at most twice, and is safe to repeat. The clock stands at
 * Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class BookingRescheduleApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    private ?\App\Models\CounselorProfile $aina = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->me = $this->client();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function aina()
    {
        return $this->aina ??= $this->counselor('Aina', [$this->platform]);
    }

    private function mine(string $when, ?User $who = null, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        return $this->book($this->slot($this->aina(), $this->platform, $when), $who ?? $this->me, $status);
    }

    private function freeSlot(string $when, ?SessionMode $mode = SessionMode::Online, $location = null): CalendarEntry
    {
        return $this->slot($this->aina(), $this->platform, $when, $mode, $location);
    }

    /** Moves [booking] to [slot], with what the person saw (the slot as it is now) unless [over] says otherwise. */
    private function move(Booking $booking, CalendarEntry $slot, array $over = [])
    {
        $slot = $slot->fresh();
        $start = Carbon::parse($slot->entry_date->format('Y-m-d').' '.$slot->start_time, 'Asia/Kuala_Lumpur');

        return $this->postJson('/api/v1/bookings/'.$booking->public_id.'/reschedule', $over + [
            'slot' => $slot->public_id,
            'expected_starts_at' => $start->toIso8601String(),
            'expected_duration_minutes' => (int) $slot->duration_minutes,
            'expected_mode' => ($slot->session_mode ?? SessionMode::Online)->value,
        ]);
    }

    // ---- who may ask -----------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401_and_unconfirmed_email_403_and_maintenance_503(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 10:00');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/bookings/'.$booking->public_id.'/reschedule', ['slot' => $new->public_id])->assertUnauthorized();

        $this->client(verified: false);
        $this->move($booking, $new)->assertForbidden()->assertJson(['code' => 'email_not_verified']);

        $this->client();
        app(PlatformSettings::class)->setMaintenance(null, true);
        $this->move($booking, $new)->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertSame(0, $booking->fresh()->reschedule_count);
    }

    public function test_the_fields_are_checked(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->postJson('/api/v1/bookings/'.$booking->public_id.'/reschedule', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slot', 'expected_starts_at', 'expected_duration_minutes', 'expected_mode']);
    }

    // ---- moving -----------------------------------------------------------------------------------------------------

    public function test_moving_claims_the_new_time_and_gives_the_old_one_back_together(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');
        $old = $booking->calendarEntry;
        $new = $this->freeSlot('2026-11-06 14:00');

        $response = $this->move($booking, $new)->assertOk()
            ->assertJsonPath('booking.id', $booking->public_id)
            ->assertJsonPath('booking.status', 'confirmed')
            ->assertJsonPath('booking.starts_at', '2026-11-06T14:00:00+08:00')
            ->assertJsonPath('booking.reschedules_left', 1);

        $this->assertTrue($response->json('booking.can_reschedule'));
        $this->assertFalse($new->fresh()->is_available, 'the new time is claimed');
        $this->assertTrue($old->fresh()->is_available, 'the contract: the old time is given back');
        $this->assertSame($new->id, $booking->fresh()->calendar_entry_id);
        $this->assertSame(1, $booking->fresh()->reschedule_count);
        $this->assertSame(1, Booking::count(), 'it is the same booking, not a new one');
    }

    public function test_the_old_time_is_offered_again_and_the_new_one_is_not(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');

        $this->move($booking, $new)->assertOk();

        $starts = $this->startsOf($this->availability($this->aina()), 'available');
        $this->assertContains('2026-11-05 10:00', $starts);
        $this->assertNotContains('2026-11-06 14:00', $starts);
    }

    public function test_the_counselor_is_told_with_the_old_and_new_time_and_the_clients_name_only(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');

        $this->move($booking, $this->freeSlot('2026-11-06 14:00'))->assertOk();

        Notification::assertSentTo($this->aina()->user, BookingRescheduledCounselor::class, function (BookingRescheduledCounselor $n) {
            $mail = $n->toMail($this->aina()->user);
            $text = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines]);

            $this->assertStringContainsString($this->me->name, $text);
            $this->assertStringContainsString('Thursday, 5 November 2026, 10:00 AM – 11:00 AM', $text);
            $this->assertStringContainsString('Friday, 6 November 2026, 2:00 PM – 3:00 PM', $text);
            $this->assertStringContainsString('old time is open', $text);
            $this->assertStringNotContainsString($this->me->email, $text);
            $this->assertStringNotContainsString('http', $text);

            return true;
        });
    }

    public function test_a_failing_mail_never_undoes_the_move(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail down'));
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');

        $this->move($booking, $new)->assertOk();

        $this->assertSame($new->id, $booking->fresh()->calendar_entry_id);
    }

    public function test_moving_to_an_in_person_time_changes_the_mode_and_the_place(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $clinic = $this->place($this->platform, 'Pekan', 'Pahang');
        $new = $this->freeSlot('2026-11-06 14:00', SessionMode::Physical, $clinic);

        $response = $this->move($booking, $new)->assertOk()->assertJsonPath('booking.mode', 'physical');

        $this->assertSame($clinic->id, $booking->fresh()->location_id);
        $this->assertSame(SessionMode::Physical, $booking->fresh()->session_mode);
        $this->assertNull($response->json('booking.meeting'));
        $this->assertSame('Pekan', $response->json('booking.place.city'));
    }

    public function test_a_person_at_the_upcoming_cap_can_still_move_a_session(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $this->mine('2026-11-09 10:00');
        $this->mine('2026-11-10 10:00');

        $this->move($booking, $this->freeSlot('2026-11-06 14:00'))->assertOk();
    }

    // ---- safe to repeat ----------------------------------------------------------------------------------------------------

    public function test_asking_again_for_the_same_time_answers_the_same_and_changes_nothing(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');
        $old = $booking->calendarEntry;
        $new = $this->freeSlot('2026-11-06 14:00');

        $first = $this->move($booking, $new)->assertOk();

        // Someone else books the freed time meanwhile.
        $this->book($old->fresh());

        $again = $this->move($booking, $new)->assertOk();

        $this->assertSame($first->json('booking'), $again->json('booking'));
        $this->assertSame(1, $booking->fresh()->reschedule_count, 'counted once');
        $this->assertFalse($old->fresh()->is_available, 'the repeat must not touch a time that is someone else\'s now');
        Notification::assertSentToTimes($this->aina()->user, BookingRescheduledCounselor::class, 1);
    }

    // ---- the rules ---------------------------------------------------------------------------------------------------------

    public function test_inside_the_cancellation_window_it_is_closed_and_nothing_changes(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-02 19:00');      // 10 hours away, the default is 24
        $new = $this->freeSlot('2026-11-06 14:00');

        $this->getJson('/api/v1/bookings/'.$booking->public_id)->assertJsonPath('booking.can_reschedule', false);

        $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'reschedule_closed', 'cancellation_hours' => 24]);

        $this->assertSame(0, $booking->fresh()->reschedule_count);
        $this->assertTrue($new->fresh()->is_available);
        Notification::assertNothingSent();
    }

    public function test_the_same_deadline_as_cancelling_to_the_second(): void
    {
        $booking = $this->mine('2026-11-03 10:00');         // 25 hours away
        $new = $this->freeSlot('2026-11-06 14:00');

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:59:59', 'Asia/Kuala_Lumpur'));
        $this->getJson('/api/v1/bookings/'.$booking->public_id)->assertJsonPath('booking.can_cancel', true)->assertJsonPath('booking.can_reschedule', true);

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Kuala_Lumpur'));
        $this->getJson('/api/v1/bookings/'.$booking->public_id)->assertJsonPath('booking.can_cancel', false)->assertJsonPath('booking.can_reschedule', false);
        $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'reschedule_closed']);
    }

    public function test_at_most_the_configured_number_of_moves(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->move($booking, $this->freeSlot('2026-11-06 14:00'))->assertOk()->assertJsonPath('booking.reschedules_left', 1);
        $this->move($booking, $this->freeSlot('2026-11-07 14:00'))->assertOk()->assertJsonPath('booking.reschedules_left', 0)->assertJsonPath('booking.can_reschedule', false);

        $third = $this->freeSlot('2026-11-09 14:00');
        $this->move($booking, $third)->assertStatus(409)->assertJson(['code' => 'reschedule_limit_reached', 'max_reschedules' => 2]);

        $this->assertTrue($third->fresh()->is_available);
        $this->assertSame(2, $booking->fresh()->reschedule_count);
    }

    public function test_completed_cancelled_no_show_and_over_cannot_be_moved(): void
    {
        $new = $this->freeSlot('2026-11-09 14:00');
        $done = $this->mine('2026-11-06 10:00', status: BookingStatus::Completed);
        $noShow = $this->mine('2026-11-07 10:00', status: BookingStatus::NoShow);
        $cancelled = $this->mine('2026-11-08 10:00', status: BookingStatus::Cancelled);
        $over = $this->mine('2026-11-02 07:00');

        foreach ([$done, $noShow, $cancelled, $over] as $booking) {
            $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'not_reschedulable']);
        }

        $this->assertTrue($new->fresh()->is_available);
    }

    public function test_a_time_someone_else_took_is_slot_taken(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');
        $this->book($new, User::factory()->create());

        $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'slot_taken']);

        $this->assertSame(0, $booking->fresh()->reschedule_count);
        $this->assertFalse($booking->calendarEntry->fresh()->is_available, 'the old time is still the booking\'s');
    }

    public function test_a_time_that_is_not_bookable_any_more_is_slot_taken_and_says_no_more(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');
        $this->block($this->aina(), '2026-11-06 14:00');

        $response = $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'slot_taken']);

        $this->assertStringNotContainsString('dentist', $response->content());
    }

    public function test_a_time_edited_after_the_person_saw_it_is_slot_changed_with_the_new_details(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');

        $response = $this->move($booking, $new, ['expected_duration_minutes' => 30])->assertStatus(409)->assertJson(['code' => 'slot_changed']);

        $this->assertSame(60, $response->json('slot.duration_minutes'));
        $this->assertSame(0, $booking->fresh()->reschedule_count);
        $this->assertTrue($new->fresh()->is_available);

        $this->move($booking, $new, ['expected_mode' => 'physical'])->assertStatus(409)->assertJson(['code' => 'slot_changed']);
    }

    public function test_it_cannot_overlap_another_session_of_the_person(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $other = $this->counselor('Bakar', [$this->platform]);
        $this->book($this->slot($other, $this->platform, '2026-11-06 14:00'), $this->me);
        $new = $this->freeSlot('2026-11-06 14:00');

        $this->move($booking, $new)->assertStatus(409)->assertJson(['code' => 'time_conflict']);

        $this->assertSame(0, $booking->fresh()->reschedule_count);
        $this->assertTrue($new->fresh()->is_available);
    }

    public function test_only_the_same_counselor(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $other = $this->counselor('Bakar', [$this->platform]);
        $elsewhere = $this->slot($other, $this->platform, '2026-11-06 14:00');

        $this->move($booking, $elsewhere)->assertStatus(422)->assertJson(['code' => 'different_counselor']);

        $this->assertTrue($elsewhere->fresh()->is_available);
        $this->assertSame(0, $booking->fresh()->reschedule_count);
    }

    public function test_an_unknown_time_and_a_block_are_the_counselor_unavailable_answer(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $block = $this->block($this->aina(), '2026-11-06 14:00');

        $this->postJson('/api/v1/bookings/'.$booking->public_id.'/reschedule', [
            'slot' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'expected_starts_at' => '2026-11-06T14:00:00+08:00', 'expected_duration_minutes' => 60, 'expected_mode' => 'online',
        ])->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);

        $this->move($booking, $block)->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);
    }

    // ---- someone else's ----------------------------------------------------------------------------------------------------

    public function test_another_persons_booking_and_an_unknown_one_are_the_same_404_and_nothing_changes(): void
    {
        $stranger = User::factory()->create();
        $theirs = $this->mine('2026-11-05 10:00', $stranger);
        $new = $this->freeSlot('2026-11-06 14:00');

        $someoneElses = $this->move($theirs, $new);
        $unknown = $this->postJson('/api/v1/bookings/01hzzzzzzzzzzzzzzzzzzzzzzz/reschedule', [
            'slot' => $new->public_id, 'expected_starts_at' => '2026-11-06T14:00:00+08:00', 'expected_duration_minutes' => 60, 'expected_mode' => 'online',
        ]);

        $someoneElses->assertNotFound()->assertJson(['code' => 'booking_not_found']);
        $this->assertSame($unknown->content(), $someoneElses->content());
        $this->assertSame(0, $theirs->fresh()->reschedule_count);
        $this->assertTrue($new->fresh()->is_available);
    }

    // ---- all or nothing -------------------------------------------------------------------------------------------------------

    public function test_a_failure_while_giving_the_old_time_back_rolls_the_whole_move_back(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $old = $booking->calendarEntry;
        $new = $this->freeSlot('2026-11-06 14:00');

        $updates = 0;
        DB::listen(function ($query) use (&$updates) {
            if (str_contains(self::plainSql($query->sql), 'update calendar_entries') && ++$updates === 2) {
                throw new RuntimeException('disk full');
            }
        });

        $this->move($booking, $new)->assertStatus(500);

        $this->assertSame($old->id, $booking->fresh()->calendar_entry_id);
        $this->assertSame(0, $booking->fresh()->reschedule_count);
        $this->assertTrue($new->fresh()->is_available, 'the claim of the new time was rolled back');
        $this->assertFalse($old->fresh()->is_available);
    }

    public function test_cancelling_after_a_move_gives_back_the_new_time_only(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $old = $booking->calendarEntry;
        $new = $this->freeSlot('2026-11-06 14:00');
        $this->move($booking, $new)->assertOk();

        // Someone else books the old time meanwhile.
        $this->book($old->fresh());

        $this->postJson('/api/v1/bookings/'.$booking->public_id.'/cancel')->assertOk();

        $this->assertTrue($new->fresh()->is_available);
        $this->assertFalse($old->fresh()->is_available, 'the old time is someone else\'s now');
    }

    // ---- limits ----------------------------------------------------------------------------------------------------------------

    public function test_moving_is_rate_limited_with_retry_after(): void
    {
        config(['booking.reschedule_rate_per_minute' => 2]);
        $booking = $this->mine('2026-11-05 10:00');
        $new = $this->freeSlot('2026-11-06 14:00');

        $this->move($booking, $new)->assertOk();
        $this->move($booking, $new)->assertOk();
        $this->move($booking, $new)->assertStatus(429)->assertHeader('Retry-After');
    }
}
