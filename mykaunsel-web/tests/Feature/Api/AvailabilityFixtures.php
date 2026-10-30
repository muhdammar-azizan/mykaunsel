<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PracticeLocation;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Builders for the availability tests. The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
trait AvailabilityFixtures
{
    protected Organization $platform;

    protected Organization $open;

    protected Organization $closed;

    protected function startClock(string $at = '2026-11-02 09:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Kuala_Lumpur'));

        $this->seed(PlatformSeeder::class);
        $this->platform = Organization::where('org_type', 'platform')->firstOrFail();
        $this->open = $this->org('Klinik Damai', AccessModel::Open);
        $this->closed = $this->org('Universiti Contoh', AccessModel::Closed, OrgType::University);
    }

    protected function org(string $name, AccessModel $access, OrgType $type = OrgType::Clinic, OrgStatus $status = OrgStatus::Active): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => $type, 'access_model' => $access, 'subscription_status' => $status]);
    }

    /** A signed-in individual, optionally a member of some organizations. */
    protected function client(array $memberOf = [], bool $verified = true, bool $signIn = true): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);

        foreach ($memberOf as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }

        if ($signIn) {
            Sanctum::actingAs($user);
        }

        return $user;
    }

    /**
     * A listable counselor working in the given organizations.
     *
     * @param  list<Organization>  $orgs
     */
    protected function counselor(string $name, array $orgs, array $options = []): CounselorProfile
    {
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug().'@counselor.example']);

        $profile = CounselorProfile::create([
            'user_id' => $user->id,
            'kb_number' => $options['kb'] ?? 'KB'.random_int(50000, 99999),
            'pa_number' => $options['pa'] ?? 'PA'.random_int(50000, 99999),
            'pa_valid_until' => $options['pa_until'] ?? now()->addYear()->toDateString(),
            'verification_type' => VerificationType::OrgAssigned,
            'verification_status' => $options['status'] ?? VerificationStatus::Approved,
            'meeting_provider' => 'Google Meet',
            'display_name_org' => $options['display_org'] ?? null,
            'display_name_independent' => $options['display_independent'] ?? null,
            'accepts_requests' => $options['accepts_requests'] ?? true,
            'buffer_minutes' => $options['buffer'] ?? 15,
        ]);

        foreach ($orgs as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Counselor, 'status' => $options['membership_status'] ?? MembershipStatus::Active, 'joined_at' => now()]);
        }

        $profile->languages()->create(['language' => 'English']);
        $profile->specializations()->create(['specialization' => 'Stress']);

        return $profile;
    }

    /** @param  string  $when  Malaysian time, for example "2026-11-03 10:00" */
    protected function slot(CounselorProfile $counselor, Organization $org, string $when, ?SessionMode $mode = SessionMode::Online, ?PracticeLocation $location = null, bool $available = true, int $minutes = 60): CalendarEntry
    {
        $at = Carbon::parse($when, 'Asia/Kuala_Lumpur');

        return CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id,
            'entry_type' => CalendarEntryType::AvailableSlot,
            'context_org_id' => $org->id,
            'entry_date' => $at->toDateString(),
            'start_time' => $at->format('H:i:s'),
            'duration_minutes' => $minutes,
            'session_mode' => $mode,
            'location_id' => $location?->id,
            'is_available' => $available,
        ]);
    }

    protected function block(CounselorProfile $counselor, string $when, int $minutes = 60): CalendarEntry
    {
        $at = Carbon::parse($when, 'Asia/Kuala_Lumpur');

        return CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::PersonalBlock, 'title' => 'Private dentist visit',
            'entry_date' => $at->toDateString(), 'start_time' => $at->format('H:i:s'), 'duration_minutes' => $minutes, 'is_available' => true,
        ]);
    }

    /** A booking of a slot: the flag goes off and a booking row points at it, like the confirm step will do. */
    protected function book(CalendarEntry $slot, ?User $client = null, BookingStatus $status = BookingStatus::Confirmed, bool $clearFlag = true): Booking
    {
        $client ??= User::factory()->create();

        if ($clearFlag && $status !== BookingStatus::Cancelled) {
            $slot->forceFill(['is_available' => false])->save();
        }

        return Booking::create([
            'org_id' => $slot->context_org_id, 'user_id' => $client->id, 'counselor_user_id' => $slot->counselor_user_id, 'calendar_entry_id' => $slot->id,
            'booking_mode' => BookingMode::SlotBased, 'status' => $status, 'session_mode' => $slot->session_mode ?? SessionMode::Online,
            'meeting_provider' => 'Google Meet', 'meeting_url' => 'https://meet.example/secret-room-123',
        ]);
    }

    /**
     * The contract of EVERY path that ends a booking (cancel, reschedule, a counselor or member leaving): the status changes AND the time is
     * given back (`is_available` on) in the SAME transaction. Cancelling does not exist as a feature yet; when it does, it must do this, and
     * its test can use this helper to say what "given back" means. See docs/AVAILABILITY.md.
     */
    protected function cancelBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
            CalendarEntry::query()->whereKey($booking->calendar_entry_id)->update(['is_available' => true]);
        });
    }

    protected function place(Organization $org, string $city = 'Kuantan', string $state = 'Pahang'): PracticeLocation
    {
        return PracticeLocation::create([
            'owner_type' => 'organization', 'owner_id' => $org->id, 'name' => 'Klinik Damai Kuantan', 'address' => 'No 12, Jalan Rahsia Besar, Taman Sulit',
            'city' => $city, 'state' => $state, 'postcode' => '25300', 'latitude' => 3.80770001, 'longitude' => 103.32600002, 'description' => 'Tingkat dua', 'is_primary' => true,
        ]);
    }

    protected function availability(CounselorProfile $counselor, array $query = [])
    {
        return $this->getJson('/api/v1/counselors/'.$counselor->public_id.'/availability'.($query ? '?'.http_build_query($query) : ''));
    }

    /** Every key and every text value in a JSON answer, for the "never shown" checks. */
    protected function flatten(mixed $data, array &$keys = [], array &$values = []): array
    {
        foreach ((array) $data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            is_array($value) ? $this->flatten($value, $keys, $values) : $values[] = (string) $value;
        }

        return [$keys, $values];
    }

    /** @return list<array<string, mixed>> every slot of an answer, day after day */
    protected function slotsOf($response): array
    {
        return collect($response->json('days'))->flatMap(fn ($day) => $day['slots'])->values()->all();
    }

    /** @return list<string> the start times ("2026-11-03 10:00") of the slots of an answer, in Malaysian time */
    protected function startsOf($response, ?string $state = null): array
    {
        return collect($this->slotsOf($response))
            ->when($state !== null, fn ($slots) => $slots->where('state', $state))
            ->map(fn ($slot) => Carbon::parse($slot['starts_at'])->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))
            ->values()->all();
    }
}
