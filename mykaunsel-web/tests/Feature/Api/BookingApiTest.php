<?php

namespace Tests\Feature\Api;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\BookingIdempotencyKey;
use App\Models\CalendarEntry;
use App\Models\User;
use App\Notifications\BookingConfirmedClient;
use App\Notifications\NewBookingCounselor;
use App\Services\BookingIdempotency;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * GET /api/v1/bookings/preview and POST /api/v1/bookings: who may book, what is refused and with which code, idempotency, privacy, limits.
 * The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class BookingApiTest extends TestCase
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
        Booking::flushEventListeners();
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    private function body(CalendarEntry $slot, array $over = []): array
    {
        $start = Carbon::parse($slot->entry_date->format('Y-m-d').' '.$slot->start_time, 'Asia/Kuala_Lumpur');

        return array_merge([
            'slot' => $slot->public_id,
            'acknowledged_policy' => true,
            'expected_starts_at' => $start->toIso8601String(),
            'expected_duration_minutes' => (int) $slot->duration_minutes,
            'expected_mode' => ($slot->session_mode ?? SessionMode::Online)->value,
        ], $over);
    }

    private function confirm(CalendarEntry $slot, array $over = [], ?string $key = null)
    {
        return $this->postJson('/api/v1/bookings', $this->body($slot, $over), ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function preview(CalendarEntry $slot)
    {
        return $this->getJson('/api/v1/bookings/preview?slot='.$slot->public_id);
    }

    /**
     * Preview first, then the booking: they must agree. Returns the booking response.
     * (slot_changed has no preview twin: the preview does not know what the person expected.)
     */
    private function both(CalendarEntry $slot, array $over = [])
    {
        $preview = $this->preview($slot);
        $made = $this->confirm($slot, $over);

        if ($made->status() === 201) {
            $preview->assertOk()->assertJson(['valid' => true]);
        } elseif ($made->status() === 404) {
            $preview->assertStatus(404)->assertJson(['code' => 'counselor_unavailable']);
        } elseif ($made->json('code') !== 'slot_changed') {
            $preview->assertOk()->assertJson(['valid' => false, 'reason' => $made->json('code')]);
        }

        return $made;
    }

    private function aina(array $options = [])
    {
        return $this->counselor('Aina', [$this->platform], $options);
    }

    // ---- who may ask ------------------------------------------------------------------------------------------

    public function test_signed_out_gets_401_on_both_endpoints(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');

        $this->getJson('/api/v1/bookings/preview?slot='.$slot->public_id)->assertUnauthorized();
        $this->postJson('/api/v1/bookings', $this->body($slot), ['Idempotency-Key' => (string) Str::uuid()])->assertUnauthorized();
    }

    public function test_an_unconfirmed_email_gets_403_email_not_verified(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client(verified: false);

        $this->preview($slot)->assertForbidden()->assertJson(['code' => 'email_not_verified']);
        $this->confirm($slot)->assertForbidden()->assertJson(['code' => 'email_not_verified']);
        $this->assertSame(0, Booking::count());
    }

    public function test_maintenance_answers_503_in_json(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        app(PlatformSettings::class)->setMaintenance(null, true);

        $this->confirm($slot)->assertStatus(503)->assertJsonStructure(['message'])->assertHeader('Retry-After');
        $this->preview($slot)->assertStatus(503);
        $this->assertSame(0, Booking::count());
    }

    // ---- a booking ---------------------------------------------------------------------------------------------

    public function test_an_eligible_person_books_a_slot(): void
    {
        Notification::fake();
        $aina = $this->aina();
        $slot = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $client = $this->client();

        $response = $this->both($slot);

        $response->assertCreated()->assertJsonPath('booking.status', 'confirmed')
            ->assertJsonPath('booking.starts_at', '2026-11-03T10:00:00+08:00')
            ->assertJsonPath('booking.ends_at', '2026-11-03T11:00:00+08:00')
            ->assertJsonPath('booking.duration_minutes', 60)
            ->assertJsonPath('booking.mode', 'online')
            ->assertJsonPath('booking.counselor.display_name', 'Aina')
            ->assertJsonPath('booking.location', null);
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $response->json('booking.id'));

        $booking = Booking::sole();
        $this->assertSame($response->json('booking.id'), $booking->public_id);
        $this->assertSame($client->id, $booking->user_id);
        $this->assertSame($aina->user_id, $booking->counselor_user_id);
        $this->assertSame($slot->id, $booking->calendar_entry_id);
        $this->assertSame(BookingMode::SlotBased, $booking->booking_mode);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(0, $booking->reschedule_count);
        $this->assertNull($booking->meeting_url);
        $this->assertFalse($slot->fresh()->is_available);
    }

    public function test_the_booked_time_is_no_longer_offered_by_the_availability_screen(): void
    {
        $aina = $this->aina();
        $slot = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $this->client();

        $this->assertContains('2026-11-03 10:00', $this->startsOf($this->availability($aina), 'available'));
        $this->confirm($slot)->assertCreated();

        $this->assertNotContains('2026-11-03 10:00', $this->startsOf($this->availability($aina), 'available'));
        $this->assertContains('2026-11-03 10:00', $this->startsOf($this->availability($aina), 'booked'));
    }

    public function test_both_e_mails_are_queued_to_the_right_people_with_nothing_private(): void
    {
        Notification::fake();
        $aina = $this->aina();
        $slot = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $client = $this->client();

        $this->confirm($slot)->assertCreated();

        Notification::assertSentTo($client, BookingConfirmedClient::class, function (BookingConfirmedClient $n) use ($client) {
            $text = $this->mailText($n, $client);
            $this->assertStringContainsString('Aina', $text);
            $this->assertStringContainsString('Tuesday, 3 November 2026', $text);
            $this->assertStringContainsString('10:00 AM – 11:00 AM', $text);
            $this->assertStringContainsString('Monday, 2 November 2026', $text, 'the cancellation deadline');
            $this->assertStringNotContainsString('http', $text);

            return true;
        });

        Notification::assertSentTo($aina->user, NewBookingCounselor::class, function (NewBookingCounselor $n) use ($aina, $client) {
            $text = $this->mailText($n, $aina->user);
            $this->assertStringContainsString($client->name, $text);
            $this->assertStringContainsString('no meeting link yet', $text);
            $this->assertStringNotContainsString($client->email, $text);
            $this->assertStringNotContainsString('http', $text);

            return true;
        });

        Notification::assertSentToTimes($client, NewBookingCounselor::class, 0);
    }

    private function mailText($notification, $notifiable): string
    {
        $mail = $notification->toMail($notifiable);

        return implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);
    }

    public function test_an_e_mail_that_cannot_be_queued_does_not_undo_the_booking(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->atLeast()->once()->andThrow(new RuntimeException('mail down')));

        $this->confirm($slot)->assertCreated();
        $this->assertSame(1, Booking::count());
    }

    public function test_the_booking_takes_org_location_and_mode_from_the_slot(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $place = $this->place($this->open);
        $inPerson = $this->slot($aina, $this->open, '2026-11-03 10:00', SessionMode::Physical, $place);
        $noMode = $this->slot($aina, $this->open, '2026-11-04 10:00', null);
        $this->client();

        $first = $this->both($inPerson)->assertCreated();
        $first->assertJsonPath('booking.mode', 'physical')->assertJsonPath('booking.location', ['name' => 'Klinik Damai Kuantan', 'city' => 'Kuantan']);
        $this->both($noMode)->assertCreated()->assertJsonPath('booking.mode', 'online');

        $a = Booking::where('calendar_entry_id', $inPerson->id)->sole();
        $b = Booking::where('calendar_entry_id', $noMode->id)->sole();
        $this->assertSame($this->open->id, $a->org_id);
        $this->assertSame($place->id, $a->location_id);
        $this->assertSame(SessionMode::Physical, $a->session_mode);
        $this->assertSame(SessionMode::Online, $b->session_mode);
        $this->assertNull($b->location_id);
    }

    public function test_nothing_private_is_in_the_answers(): void
    {
        $uni = $this->closed;
        $aina = $this->counselor('Aina', [$uni]);
        $place = $this->place($uni);
        $slot = $this->slot($aina, $uni, '2026-11-03 10:00', SessionMode::Physical, $place);
        $this->client([$uni]);
        $slot->contextOrg->update(['cancellation_deadline_hours' => 48]);

        $preview = $this->preview($slot)->assertOk();
        $made = $this->confirm($slot)->assertCreated();

        foreach ([$preview, $made] as $response) {
            [$keys, $values] = $this->flatten($response->json());
            $text = json_encode($response->json());

            foreach (['meeting_url', 'meeting_link', 'address', 'email', 'phone', 'price', 'fee', 'org_id', 'user_id', 'context', 'organization'] as $forbidden) {
                $this->assertNotContains($forbidden, $keys, "key {$forbidden}");
            }
            $this->assertStringNotContainsString('Jalan Rahsia', $text);
            $this->assertStringNotContainsString('Universiti Contoh', $text);
            $this->assertStringNotContainsString('secret-room', $text);
        }
    }

    // ---- the form ----------------------------------------------------------------------------------------------

    public function test_bad_input_is_a_422_and_books_nothing(): void
    {
        config(['booking.create_rate_per_minute' => 100]);
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $key = (string) Str::uuid();

        $this->postJson('/api/v1/bookings', $this->body($slot), [])->assertStatus(422)->assertJsonValidationErrors('key');
        $this->postJson('/api/v1/bookings', $this->body($slot), ['Idempotency-Key' => 'not-a-uuid'])->assertStatus(422)->assertJsonValidationErrors('key');

        foreach ([
            ['acknowledged_policy' => false], ['acknowledged_policy' => null], ['acknowledged_policy' => 'yes'], ['acknowledged_policy' => 'true'],
            ['expected_starts_at' => 'tomorrow-ish'], ['expected_duration_minutes' => 'long'], ['expected_duration_minutes' => 0], ['expected_mode' => 'phone'], ['slot' => null],
        ] as $bad) {
            $this->postJson('/api/v1/bookings', array_merge($this->body($slot), $bad), ['Idempotency-Key' => $key])->assertStatus(422);
        }

        $payload = $this->body($slot);
        unset($payload['acknowledged_policy']);
        $this->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])->assertStatus(422)->assertJsonValidationErrors('acknowledged_policy');

        $this->assertSame(0, Booking::count());
        $this->assertTrue($slot->fresh()->is_available);
    }

    // ---- idempotency -------------------------------------------------------------------------------------------

    public function test_the_same_key_and_request_returns_the_first_answer_and_books_once(): void
    {
        Notification::fake();
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $key = (string) Str::uuid();

        $first = $this->confirm($slot, [], $key)->assertCreated();
        $second = $this->confirm($slot, [], $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, Booking::count());
        Notification::assertSentTimes(BookingConfirmedClient::class, 1);
    }

    public function test_the_same_request_spelled_differently_is_still_the_same_request(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $key = (string) Str::uuid();

        $this->confirm($slot, [], $key)->assertCreated();
        $this->confirm($slot, ['slot' => strtoupper($slot->public_id), 'expected_starts_at' => '2026-11-03T02:00:00Z'], $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, Booking::count());
    }

    public function test_the_same_key_with_a_different_request_is_a_422(): void
    {
        $aina = $this->aina();
        $one = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $two = $this->slot($aina, $this->platform, '2026-11-04 10:00');
        $this->client();
        $key = (string) Str::uuid();

        $this->confirm($one, [], $key)->assertCreated();
        $this->confirm($two, [], $key)->assertStatus(422)->assertJson(['code' => 'idempotency_key_reuse']);
        $this->assertSame(1, Booking::count());
    }

    public function test_the_same_key_while_the_first_is_still_processing_is_409_request_in_progress(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $client = $this->client();
        $key = (string) Str::uuid();
        $this->reserve($client, $key, $slot);

        $this->confirm($slot, [], $key)->assertStatus(409)->assertJson(['code' => 'request_in_progress'])->assertHeader('Retry-After');
        $this->assertSame(0, Booking::count());
    }

    public function test_a_reservation_left_processing_stops_blocking_after_sixty_seconds(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $client = $this->client();
        $key = (string) Str::uuid();
        $this->reserve($client, $key, $slot);

        Carbon::setTestNow(now()->addSeconds(59));
        $this->confirm($slot, [], $key)->assertStatus(409)->assertJson(['code' => 'request_in_progress']);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->confirm($slot, [], $key)->assertCreated();
        $this->assertSame(1, Booking::count());
    }

    public function test_a_refusal_frees_the_key_so_the_person_can_try_again_with_it(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $client = $this->client();
        $key = (string) Str::uuid();
        $other = $this->book($slot);

        $this->confirm($slot, [], $key)->assertStatus(409)->assertJson(['code' => 'slot_taken']);
        $this->assertSame(0, BookingIdempotencyKey::count());

        $this->cancelBooking($other);
        $this->confirm($slot, [], $key)->assertCreated();
        $this->assertSame($client->id, Booking::where('status', 'confirmed')->sole()->user_id);
    }

    public function test_keys_of_two_people_never_meet(): void
    {
        $aina = $this->aina();
        $one = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $two = $this->slot($aina, $this->platform, '2026-11-04 10:00');
        $key = (string) Str::uuid();

        $this->client();
        $this->confirm($one, [], $key)->assertCreated();
        $this->client();
        $this->confirm($two, [], $key)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(2, Booking::count());
    }

    private function reserve(User $user, string $key, CalendarEntry $slot): void
    {
        $service = app(BookingIdempotency::class);
        $body = $this->body($slot);

        $service->reserve($user->id, $key, $service->fingerprint($body['slot'], true, $body['expected_starts_at'], $body['expected_duration_minutes'], $body['expected_mode']));
    }

    // ---- the slot changed or is gone ----------------------------------------------------------------------------

    public function test_a_slot_the_counselor_edited_meanwhile_is_409_slot_changed_with_the_fresh_details(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $payload = $this->body($slot);
        $slot->update(['duration_minutes' => 45]);

        $response = $this->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJson(['code' => 'slot_changed'])
            ->assertJsonPath('slot.duration_minutes', 45)->assertJsonPath('slot.ends_at', '2026-11-03T10:45:00+08:00');

        $this->assertSame($slot->public_id, $response->json('slot.slot'));
        $this->assertSame(0, Booking::count());
        $this->assertTrue($slot->fresh()->is_available);
        $this->assertSame(0, BookingIdempotencyKey::count());
    }

    public function test_a_moved_start_or_a_changed_mode_is_slot_changed_too(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        $payload = $this->body($slot);
        $slot->update(['start_time' => '12:00:00']);
        $this->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409)->assertJson(['code' => 'slot_changed']);

        $payload = $this->body($slot->fresh());
        $slot->update(['session_mode' => SessionMode::Physical]);
        $this->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409)->assertJson(['code' => 'slot_changed']);
    }

    public function test_every_reason_a_time_cannot_be_booked_gives_the_identical_slot_taken_answer(): void
    {
        $aina = $this->aina();
        $client = $this->client();

        $taken = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $this->book($taken);
        $soon = $this->slot($aina, $this->platform, '2026-11-02 10:00');                  // 1 hour from now: inside the lead time
        $far = $this->slot($aina, $this->platform, '2026-12-05 10:00');                   // beyond the horizon
        $blocked = $this->slot($aina, $this->platform, '2026-11-04 10:00');
        $this->block($aina, '2026-11-04 10:30');                                            // a private block made after the slot
        $buffered = $this->slot($aina, $this->platform, '2026-11-05 10:00');
        $this->block($aina, '2026-11-05 11:10');                                            // starts inside the 15 minute buffer
        $flagOff = $this->slot($aina, $this->platform, '2026-11-06 10:00', available: false);

        $bodies = [];

        foreach ([$taken, $soon, $far, $blocked, $buffered, $flagOff] as $slot) {
            $made = $this->both($slot);
            $made->assertStatus(409)->assertJson(['code' => 'slot_taken']);
            $bodies[] = $made->content();
            $this->preview($slot)->assertOk()->assertExactJson(['valid' => false, 'reason' => 'slot_taken']);
        }

        $this->assertCount(1, array_unique($bodies), 'one body for every reason');
        $this->assertSame(0, Booking::where('user_id', $client->id)->count());
    }

    public function test_every_counselor_related_no_gives_the_identical_404(): void
    {
        $suspended = $this->counselor('Susp', [$this->platform], ['status' => VerificationStatus::Suspended]);
        $susp = $this->slot($suspended, $this->platform, '2026-11-03 10:00');
        $inClosed = $this->slot($this->counselor('Uni', [$this->closed]), $this->closed, '2026-11-03 10:00');
        $aina = $this->aina();
        $theBlock = $this->block($aina, '2026-11-03 14:00');
        $ok = $this->slot($aina, $this->platform, '2026-11-04 10:00');

        $this->client(); // not a member of the closed organization
        $bodies = [];

        foreach ([$susp, $inClosed, $theBlock] as $entry) {
            $made = $this->both($entry);
            $made->assertNotFound()->assertJson(['code' => 'counselor_unavailable']);
            $bodies[] = $made->content();
        }

        foreach (['', 'zzzzzzzzzzzzzzzzzzzzzzzzzz', '01hzzzzzzzzzzzzzzzzzzzzzzz', str_repeat('a', 30), "x' or 1=1 --"] as $junk) {
            $made = $this->postJson('/api/v1/bookings', $this->body($ok, ['slot' => $junk === '' ? 'x' : $junk]), ['Idempotency-Key' => (string) Str::uuid()]);
            $made->assertNotFound();
            $bodies[] = $made->content();
            $this->getJson('/api/v1/bookings/preview?slot='.urlencode($junk === '' ? 'x' : $junk))->assertNotFound();
        }

        // Booking yourself: a counselor acting as a client.
        Sanctum::actingAs($aina->user);
        $made = $this->both($ok);
        $made->assertNotFound();
        $bodies[] = $made->content();

        $this->assertCount(1, array_unique($bodies), 'one body for every reason');
        $this->assertSame(0, Booking::count());
    }

    public function test_a_member_may_book_a_closed_organizations_counselor(): void
    {
        $slot = $this->slot($this->counselor('Uni', [$this->closed]), $this->closed, '2026-11-03 10:00');
        $this->client([$this->closed]);

        $this->both($slot)->assertCreated();
        $this->assertSame($this->closed->id, Booking::sole()->org_id);
    }

    public function test_a_counselor_who_left_the_slots_organization_cannot_be_booked_there(): void
    {
        $aina = $this->counselor('Aina', [$this->open, $this->platform]);
        $slot = $this->slot($aina, $this->open, '2026-11-03 10:00');
        $aina->user->memberships()->where('org_id', $this->open->id)->update(['status' => 'inactive']);
        $this->client();

        $this->both($slot)->assertNotFound();
    }

    // ---- the person's own limits ---------------------------------------------------------------------------------

    public function test_a_session_overlapping_another_confirmed_one_is_409_time_conflict(): void
    {
        $aina = $this->aina();
        $mine = $this->slot($this->counselor('Other', [$this->platform]), $this->platform, '2026-11-03 10:30');
        $client = $this->client();
        $this->book($mine, $client);
        $slot = $this->slot($aina, $this->platform, '2026-11-03 10:00');

        $this->both($slot)->assertStatus(409)->assertJson(['code' => 'time_conflict']);
        $this->assertSame(1, Booking::count());
        $this->assertTrue($slot->fresh()->is_available);

        // Touching exactly at the edge is not an overlap.
        $edge = $this->slot($aina, $this->platform, '2026-11-03 11:30');
        $this->both($edge)->assertCreated();
    }

    public function test_a_cancelled_session_does_not_conflict(): void
    {
        $aina = $this->aina();
        $mine = $this->slot($this->counselor('Other', [$this->platform]), $this->platform, '2026-11-03 10:30');
        $client = $this->client();
        $this->book($mine, $client, BookingStatus::Cancelled);
        $slot = $this->slot($aina, $this->platform, '2026-11-03 10:00');

        $this->both($slot)->assertCreated();
    }

    public function test_the_upcoming_cap_counts_only_confirmed_sessions_that_have_not_started(): void
    {
        $other = $this->counselor('Other', [$this->platform]);
        $client = $this->client();

        // Not counted: cancelled, completed, no-show, and one that already started.
        $this->book($this->slot($other, $this->platform, '2026-11-10 09:00'), $client, BookingStatus::Cancelled);
        $this->book($this->slot($other, $this->platform, '2026-11-11 09:00'), $client, BookingStatus::Completed);
        $this->book($this->slot($other, $this->platform, '2026-11-12 09:00'), $client, BookingStatus::NoShow);
        $this->book($this->slot($other, $this->platform, '2026-11-02 08:00'), $client);
        // Counted: two.
        $this->book($this->slot($other, $this->platform, '2026-11-13 09:00'), $client);
        $this->book($this->slot($other, $this->platform, '2026-11-14 09:00'), $client);

        $aina = $this->aina();
        $third = $this->slot($aina, $this->platform, '2026-11-03 10:00');
        $fourth = $this->slot($aina, $this->platform, '2026-11-04 10:00');

        $this->both($third)->assertCreated();
        $this->both($fourth)->assertStatus(409)->assertJson(['code' => 'upcoming_limit_reached', 'max_upcoming_sessions' => 3]);
        $this->assertTrue($fourth->fresh()->is_available);
    }

    public function test_the_cap_stops_one_account_from_draining_a_counselors_calendar(): void
    {
        $aina = $this->aina();
        $slots = collect(range(3, 9))->map(fn ($d) => $this->slot($aina, $this->platform, "2026-11-0{$d} 10:00"));
        $this->client();

        $results = $slots->map(fn ($slot) => $this->confirm($slot)->status());

        $this->assertSame(3, $results->filter(fn ($s) => $s === 201)->count());
        $this->assertSame(4, $results->filter(fn ($s) => $s === 409)->count());
        $this->assertSame(3, Booking::count());
        $this->assertSame(4, CalendarEntry::where('is_available', true)->count());
    }

    public function test_freeing_the_time_when_a_booking_ends_lets_it_be_booked_again(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $first = $this->client();
        $this->confirm($slot)->assertCreated();
        $this->assertFalse($slot->fresh()->is_available);

        $this->cancelBooking(Booking::sole());
        $this->assertTrue($slot->fresh()->is_available, 'the contract: ending a booking gives the time back');

        $second = $this->client();
        $this->confirm($slot)->assertCreated();
        $this->assertSame(2, Booking::count());
        $this->assertSame(1, Booking::where('status', 'confirmed')->count());
        $this->assertNotSame($first->id, Booking::where('status', 'confirmed')->sole()->user_id);
    }

    // ---- all or nothing -----------------------------------------------------------------------------------------

    public function test_a_failure_after_the_claim_rolls_the_claim_back(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();
        Booking::creating(fn () => throw new RuntimeException('disk full'));

        $this->confirm($slot)->assertStatus(500);

        $this->assertTrue($slot->fresh()->is_available, 'the claim was rolled back');
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingIdempotencyKey::count(), 'nothing was booked, so the key is free');

        Booking::flushEventListeners();
        $this->confirm($slot)->assertCreated();
    }

    // ---- the preview ---------------------------------------------------------------------------------------------

    public function test_the_preview_tells_what_the_screen_needs_and_claims_nothing(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-05 10:00');
        $this->client();

        $this->preview($slot)->assertOk()->assertExactJson([
            'valid' => true,
            'reason' => null,
            'slot' => $slot->public_id,
            'counselor' => ['id' => $slot->counselor->counselorProfile->public_id, 'display_name' => 'Aina', 'verified' => true],
            'starts_at' => '2026-11-05T10:00:00+08:00',
            'ends_at' => '2026-11-05T11:00:00+08:00',
            'duration_minutes' => 60,
            'mode' => 'online',
            'location' => null,
            'cancellation_deadline_at' => '2026-11-04T10:00:00+08:00',
            'cancellation_hours' => 24,
            'max_reschedules' => 2,
            'meeting_link_visible_minutes' => 15,
        ]);

        $this->assertTrue($slot->fresh()->is_available);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingIdempotencyKey::count());
    }

    public function test_the_cancellation_rule_comes_from_the_slots_organization(): void
    {
        $slot = $this->slot($this->counselor('Uni', [$this->closed]), $this->closed, '2026-11-05 10:00');
        $this->closed->update(['cancellation_deadline_hours' => 48]);
        $this->client([$this->closed]);

        $this->preview($slot)->assertJsonPath('cancellation_hours', 48)->assertJsonPath('cancellation_deadline_at', '2026-11-03T10:00:00+08:00');
    }

    public function test_a_session_starting_inside_the_cancellation_window_has_no_deadline(): void
    {
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-02 19:00');
        $this->client();

        $this->preview($slot)->assertOk()->assertJson(['valid' => true, 'cancellation_deadline_at' => null, 'cancellation_hours' => 24]);
    }

    public function test_the_upcoming_count_is_only_said_when_the_person_is_at_the_cap_or_one_below(): void
    {
        $other = $this->counselor('Other', [$this->platform]);
        $client = $this->client();
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');

        $this->assertArrayNotHasKey('upcoming_sessions', $this->preview($slot)->json());

        $this->book($this->slot($other, $this->platform, '2026-11-13 09:00'), $client);
        $this->assertArrayNotHasKey('upcoming_sessions', $this->preview($slot)->json());

        $this->book($this->slot($other, $this->platform, '2026-11-14 09:00'), $client);
        $this->preview($slot)->assertJson(['valid' => true, 'upcoming_sessions' => 2, 'max_upcoming_sessions' => 3]);

        $this->book($this->slot($other, $this->platform, '2026-11-15 09:00'), $client);
        $this->preview($slot)->assertJson(['valid' => false, 'reason' => 'upcoming_limit_reached', 'upcoming_sessions' => 3, 'max_upcoming_sessions' => 3]);
    }

    public function test_a_preview_without_a_slot_is_a_422(): void
    {
        $this->client();

        $this->getJson('/api/v1/bookings/preview')->assertStatus(422);
    }

    // ---- limits ----------------------------------------------------------------------------------------------------

    public function test_booking_is_rate_limited_with_retry_after(): void
    {
        config(['booking.create_rate_per_minute' => 2]);
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();

        $this->confirm($slot, ['acknowledged_policy' => false])->assertStatus(422);
        $this->confirm($slot, ['acknowledged_policy' => false])->assertStatus(422);
        $this->confirm($slot)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, Booking::count());
    }

    public function test_the_preview_has_its_own_limit(): void
    {
        config(['booking.preview_rate_per_minute' => 2]);
        $slot = $this->slot($this->aina(), $this->platform, '2026-11-03 10:00');
        $this->client();

        $this->preview($slot)->assertOk();
        $this->preview($slot)->assertOk();
        $this->preview($slot)->assertStatus(429)->assertHeader('Retry-After');
        $this->confirm($slot)->assertCreated(); // the booking limit is a different one
    }
}
