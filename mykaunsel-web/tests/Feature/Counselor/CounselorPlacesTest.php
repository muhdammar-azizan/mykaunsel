<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\PracticeLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * "My places": where an independent counselor sees clients in person. Clock: Monday 2 November 2026, 09:00 Malaysian time.
 */
class CounselorPlacesTest extends TestCase
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

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Klinik Aman', 'address' => '12 Jalan Mawar, Taman Indah', 'city' => 'Kuantan', 'state' => 'Pahang', 'postcode' => '25000', 'description' => 'Second floor, lift on the left.'];
    }

    private function add(array $over = [], ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.places.store'), $this->payload($over));
    }

    private function place(array $over = [], ?CounselorProfile $who = null): PracticeLocation
    {
        return PracticeLocation::create($over + [
            'owner_type' => 'counselor', 'owner_id' => ($who ?? $this->aina)->user_id, 'name' => 'Klinik Aman', 'address' => '12 Jalan Mawar, Taman Indah',
            'city' => 'Kuantan', 'state' => 'Pahang', 'postcode' => '25000', 'description' => 'Second floor, lift on the left.',
        ]);
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->where('actor_user_id', $this->aina->user_id)->get();
    }

    // ---- who has places -----------------------------------------------------------------------------------------------------------------

    public function test_the_section_is_for_an_independent_counselor_only(): void
    {
        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('My places')->assertSee('Add a place')->assertSee('You have no places yet.');

        $orgOnly = $this->counselor('Bakar', [$this->open]);
        $this->as($orgOnly)->get(route('counselor.profile'))->assertOk()->assertDontSee('My places');
        $this->add([], $orgOnly)->assertSessionHasErrors('place');
        $this->assertSame(0, PracticeLocation::where('owner_type', 'counselor')->count());
    }

    // ---- adding -------------------------------------------------------------------------------------------------------------------------

    public function test_a_place_is_added_with_no_map_pin_and_audited_with_ids_only(): void
    {
        $this->add(['name' => "  Klinik   Aman \n"])->assertSessionHasNoErrors()->assertRedirect(route('counselor.profile').'#my-places');

        $place = PracticeLocation::where('owner_type', 'counselor')->sole();
        $this->assertSame([$this->aina->user_id, 'Klinik Aman', 'Kuantan', 'Pahang', '25000'], [$place->owner_id, $place->name, $place->city, $place->state, $place->postcode]);
        $this->assertNull($place->latitude);
        $this->assertNull($place->archived_at);

        $entry = $this->logs('counselor.place_added')->sole();
        $this->assertSame($place->id, $entry->metadata['place_id']);
        $this->assertStringNotContainsString('Jalan Mawar', json_encode($entry->getAttributes()));
        $this->assertStringNotContainsString('Klinik Aman', json_encode($entry->getAttributes()));

        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('Klinik Aman')->assertSee('12 Jalan Mawar, Taman Indah');
    }

    public function test_every_field_is_checked_and_nothing_is_made_when_one_is_wrong(): void
    {
        foreach ([['name' => ''], ['address' => ''], ['city' => ''], ['state' => 'Atlantis'], ['state' => ''], ['postcode' => '2500'], ['postcode' => 'ABCDE'], ['name' => str_repeat('a', 101)], ['description' => str_repeat('d', 501)]] as $bad) {
            $this->add($bad)->assertSessionHasErrors();
        }

        $this->assertSame(0, PracticeLocation::where('owner_type', 'counselor')->count());
        $this->assertCount(0, $this->logs('counselor.place_added'));
    }

    public function test_at_most_five_places(): void
    {
        foreach (range(1, 5) as $i) {
            $this->add(['name' => "Place {$i}"])->assertSessionHasNoErrors();
        }

        $this->add(['name' => 'One too many'])->assertSessionHasErrors('place');

        $this->assertSame(5, PracticeLocation::where('owner_type', 'counselor')->count());
        $this->as()->get(route('counselor.profile'))->assertSee('the most places allowed');
    }

    // ---- using them ---------------------------------------------------------------------------------------------------------------------

    public function test_an_in_person_time_for_the_independent_practice_needs_one_of_the_counselors_own_places(): void
    {
        $open = fn (array $over = []) => $this->as()->post(route('counselor.planner.store'), $over + [
            'context' => $this->platform->id, 'date' => '2026-11-10', 'start' => '10:00', 'duration' => 60, 'mode' => 'physical',
        ]);

        // No place yet: refused, and the message says where to add one.
        $open()->assertSessionHasErrors('planner');
        $this->assertSame(0, CalendarEntry::count());

        $mine = $this->place();
        $open(['location' => $mine->id])->assertSessionHasNoErrors();
        $this->assertSame($mine->id, CalendarEntry::sole()->location_id);

        // Someone else's place and an organization's place are not hers to use for her independent practice.
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->place(['name' => 'Bakar Clinic'], $bakar);
        $orgPlace = $this->place(['owner_type' => 'organization', 'owner_id' => $this->open->id, 'name' => 'Klinik Damai']);
        $open(['start' => '12:00', 'location' => $theirs->id])->assertSessionHasErrors('planner');
        $open(['start' => '13:00', 'location' => $orgPlace->id])->assertSessionHasErrors('planner');
        $this->assertSame(1, CalendarEntry::count());
    }

    public function test_a_weekly_line_for_the_independent_practice_can_use_an_own_place(): void
    {
        $mine = $this->place();

        $this->as()->post(route('counselor.planner.hours.store'), [
            'context' => $this->platform->id, 'weekday' => 2, 'from' => '10:00', 'to' => '13:00', 'duration' => 60, 'mode' => 'physical', 'location' => $mine->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($mine->id, CounselorWeeklyHour::sole()->location_id);
    }

    public function test_a_client_sees_the_name_and_the_city_but_only_a_client_who_booked_sees_the_address(): void
    {
        $mine = $this->place();
        $slot = $this->slot($this->aina, $this->platform, '2026-11-05 10:00', SessionMode::Physical, $mine);
        $client = $this->client();

        $body = $this->availability($this->aina)->assertOk()->getContent();
        $this->assertStringContainsString('Klinik Aman', $body);
        $this->assertStringContainsString('Kuantan', $body);
        $this->assertStringNotContainsString('Jalan Mawar', $body);
        $this->assertStringNotContainsString('25000', $body);

        $booking = $this->book($slot, $client);
        $this->getJson('/api/v1/bookings/'.$booking->public_id)->assertOk()->assertJsonPath('booking.place.address', '12 Jalan Mawar, Taman Indah');
    }

    // ---- changing -----------------------------------------------------------------------------------------------------------------------

    public function test_a_place_is_changed_and_audited_with_the_names_of_the_fields_only(): void
    {
        $place = $this->place();

        $this->as()->put(route('counselor.places.update', $place->id), $this->payload(['name' => 'Klinik Aman Baru', 'postcode' => '25100']))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Place saved.');

        $this->assertSame(['Klinik Aman Baru', '25100'], [$place->fresh()->name, $place->fresh()->postcode]);

        $entry = $this->logs('counselor.place_updated')->sole();
        $this->assertEqualsCanonicalizing(['name', 'postcode'], $entry->metadata['fields']);
        $this->assertStringNotContainsString('Baru', json_encode($entry->getAttributes()));
    }

    public function test_saving_the_same_again_changes_and_records_nothing(): void
    {
        $place = $this->place();

        $this->as()->put(route('counselor.places.update', $place->id), $this->payload())->assertSessionHas('status', 'Nothing changed.');

        $this->assertCount(0, $this->logs('counselor.place_updated'));
    }

    public function test_a_place_a_client_was_told_about_can_not_be_changed(): void
    {
        $place = $this->place();
        $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00', SessionMode::Physical, $place), User::factory()->create());

        $this->as()->put(route('counselor.places.update', $place->id), $this->payload(['address' => 'Somewhere else']))->assertSessionHasErrors('place');

        $this->assertSame('12 Jalan Mawar, Taman Indah', $place->fresh()->address);
        $this->assertCount(0, $this->logs('counselor.place_updated'));
        $this->as()->get(route('counselor.profile'))->assertSee('was told this address');
    }

    // ---- removing -----------------------------------------------------------------------------------------------------------------------

    public function test_a_place_nothing_ever_used_is_deleted(): void
    {
        $place = $this->place();

        $this->as()->delete(route('counselor.places.destroy', $place->id))->assertSessionHas('status', 'Place removed.');

        $this->assertNull(PracticeLocation::find($place->id));
        $this->assertFalse($this->logs('counselor.place_removed')->sole()->metadata['archived']);
    }

    public function test_a_place_that_history_points_at_is_archived_not_deleted_and_never_offered_again(): void
    {
        $place = $this->place();
        $past = $this->book($this->slot($this->aina, $this->platform, '2026-10-28 10:00', SessionMode::Physical, $place), User::factory()->create(), BookingStatus::Completed);

        $this->as()->delete(route('counselor.places.destroy', $place->id))->assertSessionHasNoErrors();

        $this->assertNotNull(PracticeLocation::find($place->id)->archived_at, 'kept: the past session still points at it');
        $this->assertSame($place->id, CalendarEntry::find($past->calendar_entry_id)->location_id);
        $this->assertTrue($this->logs('counselor.place_removed')->sole()->metadata['archived']);

        $this->as()->get(route('counselor.profile'))->assertDontSee('Klinik Aman');
        $this->as()->post(route('counselor.planner.store'), ['context' => $this->platform->id, 'date' => '2026-11-10', 'start' => '10:00', 'duration' => 60, 'mode' => 'physical', 'location' => $place->id])
            ->assertSessionHasErrors('planner');
    }

    public function test_a_place_that_a_time_a_weekly_line_or_a_waiting_request_still_uses_can_not_be_removed(): void
    {
        $withTime = $this->place(['name' => 'With a time']);
        $withLine = $this->place(['name' => 'With a line']);
        $withRequest = $this->place(['name' => 'With a request']);

        $this->slot($this->aina, $this->platform, '2026-11-10 10:00', SessionMode::Physical, $withTime);
        CounselorWeeklyHour::create(['counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'weekday' => 3, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'slot_minutes' => 60, 'session_mode' => 'physical', 'location_id' => $withLine->id]);
        BookingRequest::create([
            'user_id' => User::factory()->create()->id, 'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'requested_date' => '2026-11-12',
            'requested_time' => '10:00:00', 'duration_minutes' => 60, 'session_mode' => 'physical', 'location_id' => $withRequest->id,
            'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(40),
        ]);

        foreach ([$withTime, $withLine, $withRequest] as $place) {
            $this->as()->delete(route('counselor.places.destroy', $place->id))->assertSessionHasErrors('place');
            $this->assertNull($place->fresh()->archived_at);
        }

        $this->assertCount(0, $this->logs('counselor.place_removed'));
    }

    // ---- whose place it is --------------------------------------------------------------------------------------------------------------

    public function test_someone_elses_place_and_an_organizations_place_are_a_404_and_untouched(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->place(['name' => 'Bakar Clinic'], $bakar);
        $orgPlace = $this->place(['owner_type' => 'organization', 'owner_id' => $this->open->id, 'name' => 'Klinik Damai']);

        foreach ([$theirs, $orgPlace] as $place) {
            $this->as()->put(route('counselor.places.update', $place->id), $this->payload(['name' => 'Taken over']))->assertNotFound();
            $this->as()->delete(route('counselor.places.destroy', $place->id))->assertNotFound();
            $this->assertNotSame('Taken over', $place->fresh()->name);
            $this->assertNull($place->fresh()->archived_at);
        }

        $this->as()->get(route('counselor.profile'))->assertDontSee('Bakar Clinic')->assertDontSee('Klinik Damai');
    }

    public function test_only_a_counselor_may_use_the_routes(): void
    {
        $client = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($client)->post(route('counselor.places.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, PracticeLocation::where('owner_type', 'counselor')->count());
    }
}
