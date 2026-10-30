<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\User;
use App\Notifications\BookingCancelledCounselor;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * POST /api/v1/bookings/{id}/cancel: cancelling one's own booking before its deadline gives the time back (B9) in one transaction, tells the
 * counselor by e-mail, and is safe to repeat. The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class BookingCancelApiTest extends TestCase
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

    private function mine(string $when, ?User $who = null, BookingStatus $status = BookingStatus::Confirmed, bool $link = true): Booking
    {
        $booking = $this->book($this->slot($this->aina(), $this->platform, $when), $who ?? $this->me, $status);

        if (! $link) {
            $booking->forceFill(['meeting_url' => null])->save();
        }

        return $booking;
    }

    private function cancel(Booking $booking)
    {
        return $this->postJson('/api/v1/bookings/'.$booking->public_id.'/cancel');
    }

    // ---- who may ask -----------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401_and_unconfirmed_email_403_and_maintenance_503(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/bookings/'.$booking->public_id.'/cancel')->assertUnauthorized();

        $this->client(verified: false);
        $this->cancel($booking)->assertForbidden()->assertJson(['code' => 'email_not_verified']);

        $this->client();
        app(PlatformSettings::class)->setMaintenance(null, true);
        $this->cancel($booking)->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    // ---- cancelling ---------------------------------------------------------------------------------------------------

    public function test_cancelling_changes_the_status_and_gives_the_time_back_together(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');
        $slot = CalendarEntry::sole();
        $this->assertFalse($slot->is_available);

        $response = $this->cancel($booking)->assertOk()->assertJsonPath('booking.id', $booking->public_id)->assertJsonPath('booking.status', 'cancelled');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertTrue($slot->fresh()->is_available, 'the contract: ending a booking gives the time back');
        $this->assertFalse($response->json('booking.can_cancel'));
        $this->assertNull($response->json('booking.meeting'));
        $this->assertNull($response->json('booking.place'));
    }

    public function test_the_freed_time_is_offered_again_and_can_be_booked_by_someone_else(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $this->assertNotContains('2026-11-05 10:00', $this->startsOf($this->availability($this->aina()), 'available'));

        $this->cancel($booking)->assertOk();

        $this->assertContains('2026-11-05 10:00', $this->startsOf($this->availability($this->aina()), 'available'));

        $other = $this->client();
        $slot = CalendarEntry::sole();
        $this->postJson('/api/v1/bookings', [
            'slot' => $slot->public_id, 'acknowledged_policy' => true, 'expected_starts_at' => '2026-11-05T10:00:00+08:00',
            'expected_duration_minutes' => 60, 'expected_mode' => 'online',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame($other->id, Booking::where('status', 'confirmed')->sole()->user_id);
    }

    public function test_the_counselor_is_told_by_e_mail_with_the_clients_name_only(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');

        $this->cancel($booking)->assertOk();

        Notification::assertSentTo($this->aina()->user, BookingCancelledCounselor::class, function (BookingCancelledCounselor $n) {
            $mail = $n->toMail($this->aina()->user);
            $text = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines]);

            $this->assertStringContainsString($this->me->name, $text);
            $this->assertStringContainsString('Thursday, 5 November 2026', $text);
            $this->assertStringContainsString('10:00 AM – 11:00 AM', $text);
            $this->assertStringContainsString('open for booking again', $text);
            $this->assertStringNotContainsString($this->me->email, $text);
            $this->assertStringNotContainsString('http', $text);

            return true;
        });
        Notification::assertSentToTimes($this->me, BookingCancelledCounselor::class, 0);
    }

    public function test_an_e_mail_that_cannot_be_queued_does_not_undo_the_cancellation(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->atLeast()->once()->andThrow(new RuntimeException('mail down')));

        $this->cancel($booking)->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertTrue(CalendarEntry::sole()->is_available);
    }

    // ---- repeating -----------------------------------------------------------------------------------------------------

    public function test_cancelling_again_is_safe_and_sends_no_second_e_mail(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-05 10:00');

        $first = $this->cancel($booking)->assertOk();
        $second = $this->cancel($booking)->assertOk();

        $this->assertSame($first->json('booking'), $second->json('booking'));
        Notification::assertSentToTimes($this->aina()->user, BookingCancelledCounselor::class, 1);
    }

    public function test_a_repeat_never_frees_a_time_someone_else_has_booked_meanwhile(): void
    {
        $booking = $this->mine('2026-11-05 10:00');
        $slot = CalendarEntry::sole();
        $this->cancel($booking)->assertOk();

        // Someone else takes the freed time.
        $this->book($slot->refresh(), User::factory()->create());
        $this->assertFalse($slot->fresh()->is_available);

        // The first person's retry (a lost answer) must not touch the new booking.
        $this->cancel($booking)->assertOk()->assertJsonPath('booking.status', 'cancelled');
        $this->assertFalse($slot->fresh()->is_available, 'the other person still has the time');
        $this->assertSame(1, Booking::where('status', 'confirmed')->count());
    }

    // ---- the deadline ----------------------------------------------------------------------------------------------------

    public function test_inside_the_cancellation_window_it_is_refused_and_nothing_changes(): void
    {
        Notification::fake();
        $booking = $this->mine('2026-11-02 19:00');           // 10 hours away, the window is 24

        $this->cancel($booking)->assertStatus(409)->assertJson(['code' => 'cancellation_closed', 'cancellation_hours' => 24]);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertFalse(CalendarEntry::sole()->is_available);
        Notification::assertNothingSent();
    }

    public function test_the_deadline_itself_is_closed_and_one_second_before_is_open(): void
    {
        $booking = $this->mine('2026-11-04 10:00');            // deadline: Tuesday 3 November 10:00

        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'Asia/Kuala_Lumpur'));
        $this->cancel($booking)->assertStatus(409)->assertJson(['code' => 'cancellation_closed']);

        Carbon::setTestNow(Carbon::parse('2026-11-03 09:59:59', 'Asia/Kuala_Lumpur'));
        $this->cancel($booking)->assertOk();
    }

    public function test_the_organizations_hours_decide(): void
    {
        $uni = $this->closed;
        $uni->update(['cancellation_deadline_hours' => 72]);
        $this->me = $this->client([$uni]);
        $aina = $this->counselor('Uni', [$uni]);
        $booking = $this->book($this->slot($aina, $uni, '2026-11-04 10:00'), $this->me);   // 49 hours away

        $this->cancel($booking)->assertStatus(409)->assertJson(['code' => 'cancellation_closed', 'cancellation_hours' => 72]);

        $uni->update(['cancellation_deadline_hours' => 24]);
        $this->cancel($booking)->assertOk();
    }

    public function test_the_details_and_the_cancelling_always_agree(): void
    {
        $open = $this->mine('2026-11-05 10:00');
        $closed = $this->mine('2026-11-02 19:00');

        $this->getJson('/api/v1/bookings/'.$open->public_id)->assertJsonPath('booking.can_cancel', true);
        $this->getJson('/api/v1/bookings/'.$closed->public_id)->assertJsonPath('booking.can_cancel', false);

        $this->cancel($closed)->assertStatus(409);
        $this->cancel($open)->assertOk();
    }

    public function test_completed_no_show_and_over_are_not_cancellable(): void
    {
        $done = $this->mine('2026-11-06 10:00', status: BookingStatus::Completed);
        $noShow = $this->mine('2026-11-07 10:00', status: BookingStatus::NoShow);
        $over = $this->mine('2026-11-02 07:00');               // ended an hour ago, still marked confirmed

        foreach ([$done, $noShow, $over] as $booking) {
            $this->cancel($booking)->assertStatus(409)->assertJson(['code' => 'not_cancellable']);
            $this->assertNotSame(BookingStatus::Cancelled, $booking->fresh()->status);
        }
    }

    // ---- someone else's -----------------------------------------------------------------------------------------------------

    public function test_another_persons_booking_and_an_unknown_one_are_the_same_404_and_nothing_changes(): void
    {
        $stranger = User::factory()->create();
        $theirs = $this->mine('2026-11-05 10:00', $stranger);

        $someoneElses = $this->cancel($theirs);
        $unknown = $this->postJson('/api/v1/bookings/01hzzzzzzzzzzzzzzzzzzzzzzz/cancel');

        $someoneElses->assertNotFound()->assertJson(['code' => 'booking_not_found']);
        $this->assertSame($unknown->content(), $someoneElses->content());
        $this->assertSame(BookingStatus::Confirmed, $theirs->fresh()->status);
        $this->assertFalse(CalendarEntry::sole()->is_available);
        $this->postJson('/api/v1/bookings/12/cancel')->assertNotFound();
    }

    // ---- all or nothing -------------------------------------------------------------------------------------------------------

    public function test_a_failure_while_giving_the_time_back_rolls_the_status_back_too(): void
    {
        $booking = $this->mine('2026-11-05 10:00');

        DB::listen(function ($query) {
            if (str_contains(self::plainSql($query->sql), 'update calendar_entries')) {
                throw new RuntimeException('disk full');
            }
        });

        $this->cancel($booking)->assertStatus(500);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status, 'the status change was rolled back');
        $this->assertFalse(CalendarEntry::sole()->is_available);
    }

    // ---- limits ----------------------------------------------------------------------------------------------------------------

    public function test_cancelling_is_rate_limited_with_retry_after(): void
    {
        config(['booking.cancel_rate_per_minute' => 2]);
        $booking = $this->mine('2026-11-05 10:00');

        $this->cancel($booking)->assertOk();
        $this->cancel($booking)->assertOk();
        $this->cancel($booking)->assertStatus(429)->assertHeader('Retry-After');
    }
}
