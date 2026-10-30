<?php

namespace Database\Seeders;

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
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEMO data for the mobile app's "Pick a slot" screen (the availability endpoint). Made up; local and testing only; run by hand:
 *   php artisan db:seed --class=AvailabilityDemoSeeder
 * Safe to run twice (it does nothing the second time). Every name starts with "Demo" or "[Demo]", every email ends in @example.test, and
 * nobody can sign in as them (random passwords) — in a test, act as the user with Sanctum::actingAs.
 *
 * COUNSELORS: one offering both modes, one online only, a closed university's counselor, an open clinic's counselor, an independent one, a
 * suspended one and one whose practising certificate has expired (the last two must never be bookable).
 * SLOTS: over the coming days; some booked (one online booking whose meeting link has not been added yet), some in the past, some inside the
 * minimum lead time, some beyond the booking horizon, and a personal block that makes the slot under it disappear.
 * PEOPLE: an active member of the university, a non-member, and a person whose email address is not confirmed.
 * CONFIRM SCENARIOS: see confirmScenarios() (a person at the upcoming-sessions cap, one with an overlapping session, two overlapping contexts, a slot
 * inside the cancellation window, a slot to edit between preview and confirm).
 */
class AvailabilityDemoSeeder extends Seeder
{
    use RefusesOutsideDevelopment;

    private CarbonImmutable $today;

    private int $counter = 0;

    public function run(): void
    {
        $this->refuseOutsideDevelopment();

        if (Organization::where('name', 'like', '[Demo] Availability %')->exists()) {
            return;
        }

        $zone = (string) config('counselors.directory.slot_timezone');
        $this->today = CarbonImmutable::now($zone)->startOfDay();

        $platform = Organization::where('org_type', OrgType::Platform->value)->first()
            ?? Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        $university = $this->organization('[Demo] Availability University', OrgType::University, AccessModel::Closed);
        $clinic = $this->organization('[Demo] Availability Clinic', OrgType::Clinic, AccessModel::Open);
        $place = PracticeLocation::create([
            'owner_type' => 'organization', 'owner_id' => $clinic->id, 'name' => 'Demo Clinic Damai', 'address' => '12, Jalan Contoh (made up)',
            'city' => 'Kuantan', 'state' => 'Pahang', 'postcode' => '25300', 'is_primary' => true,
        ]);

        // People.
        $member = $this->person('Demo Member', 'demo.availability.member@example.test');
        Membership::create(['user_id' => $member->id, 'org_id' => $university->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->person('Demo Non-member', 'demo.availability.nonmember@example.test');
        $this->person('Demo Unverified', 'demo.availability.unverified@example.test', verified: false);

        // Counselors.
        $both = $this->counselor('Demo Both Modes', [$platform, $clinic]);
        $online = $this->counselor('Demo Online Only', [$platform]);
        $closed = $this->counselor('Demo University Counselor', [$university]);
        $open = $this->counselor('Demo Clinic Counselor', [$clinic]);
        $suspended = $this->counselor('Demo Suspended', [$platform], VerificationStatus::Suspended);
        $expired = $this->counselor('Demo Expired PA', [$platform], paUntil: $this->today->subMonth()->toDateString());

        // Both modes: online as an independent, in person at the clinic.
        foreach ([1, 2, 3, 4] as $day) {
            $this->slot($both, $platform, $day, '09:00');
            $this->slot($both, $platform, $day, '11:00');
            $this->slot($both, $clinic, $day, '14:00', SessionMode::Physical, $place);
        }

        // Online only.
        foreach ([1, 2, 5, 6] as $day) {
            $this->slot($online, $platform, $day, '10:00');
            $this->slot($online, $platform, $day, '15:00');
        }

        // A slot with NO mode at all counts as online.
        $this->slot($online, $platform, 7, '10:00', null);

        // Booked: the slot is taken (flag off + a booking). The online booking has no meeting link yet.
        $booked = $this->slot($online, $platform, 3, '10:00', SessionMode::Online, null, available: false);
        $this->booking($member, $online, $platform, $booked, SessionMode::Online);

        // A personal block (14:00-15:00, with the 15 minute buffer until 15:15): a slot under it, and one starting inside the buffer, are not
        // offered at all (the regular 15:00 slot of that day goes too). One that starts after the buffer stays.
        $this->block($online, 3, '14:00');
        $this->slot($online, $platform, 3, '14:30');           // overlaps the block
        $this->slot($online, $platform, 3, '15:10');           // starts inside the buffer after the block
        $this->slot($online, $platform, 3, '15:30');           // clear of the buffer: offered

        // Times that must not show: in the past, inside the minimum lead time, beyond the booking horizon.
        $this->slot($both, $platform, -1, '10:00');
        $this->slotAt($both, $platform, CarbonImmutable::now($this->today->timezone)->addMinutes(60), SessionMode::Online);
        $this->slot($both, $platform, (int) config('booking.horizon_days') + 5, '10:00');

        // A closed university's counselor (only members see them) and an open clinic's counselor (everyone).
        foreach ([1, 2, 3] as $day) {
            $this->slot($closed, $university, $day, '09:00');
            $this->slot($open, $clinic, $day, '10:00', SessionMode::Physical, $place);
        }

        // Never bookable: suspended, expired PA.
        $this->slot($suspended, $platform, 1, '09:00');
        $this->slot($expired, $platform, 1, '09:00');

        $this->confirmScenarios($platform, $clinic, $place);
    }

    /**
     * For the "Confirm booking" screen (POST /bookings). Two counselors of their own, so the counts above stay as they are:
     *   Demo Booking Lab One   the TARGET: every slot below belongs to them.
     *   Demo Booking Lab Two   the other counselor whose sessions the people below already hold.
     * and the people (act as them with Sanctum::actingAs):
     *   demo.availability.atcap     already holds the most upcoming sessions allowed (3): booking one more must be refused
     *   demo.availability.overlap   holds a session with Lab Two that overlaps Lab One's day-2 09:00 slot: time_conflict
     *   (the existing member / non-member / unverified people cover the closed organization and the unconfirmed email)
     * The slots of Lab One:
     *   day 2 09:00  plain online slot, overlapped by the "overlap" person's session
     *   day 3 10:00  plain online slot (book this one)
     *   day 4 14:00 (independent) and 14:30 (clinic, in person)  two contexts, overlapping: only ONE of them can ever be booked
     *   day 6 11:00  the slot to EDIT between preview and confirm (change its length or start in a test, then confirm: slot_changed)
     *   in 5 hours   inside the cancellation window (24 hours by default): the screen warns it cannot be cancelled
     */
    private function confirmScenarios(Organization $platform, Organization $clinic, PracticeLocation $place): void
    {
        $one = $this->counselor('Demo Booking Lab One', [$platform, $clinic]);
        $two = $this->counselor('Demo Booking Lab Two', [$platform]);

        $this->slot($one, $platform, 2, '09:00');
        $this->slot($one, $platform, 3, '10:00');
        $this->slot($one, $platform, 4, '14:00');
        $this->slot($one, $clinic, 4, '14:30', SessionMode::Physical, $place);
        $this->slot($one, $platform, 6, '11:00');
        $this->slotAt($one, $platform, CarbonImmutable::now($this->today->timezone)->addHours(5)->setSecond(0), SessionMode::Online);

        $overlap = $this->person('Demo Overlap', 'demo.availability.overlap@example.test');
        $this->booking($overlap, $two, $platform, $this->slot($two, $platform, 2, '09:30', SessionMode::Online, null, available: false), SessionMode::Online);

        $atCap = $this->person('Demo At Cap', 'demo.availability.atcap@example.test');

        foreach ([10, 11, 12] as $day) {
            $this->booking($atCap, $two, $platform, $this->slot($two, $platform, $day, '09:00', SessionMode::Online, null, available: false), SessionMode::Online);
        }
    }

    private function organization(string $name, OrgType $type, AccessModel $access): Organization
    {
        $organization = Organization::create(['name' => $name, 'org_type' => $type, 'access_model' => $access, 'subscription_status' => OrgStatus::Active]);
        $organization->forceFill(['submitted_at' => now()->subMonths(2), 'reviewed_at' => now()->subMonth()])->saveQuietly();

        return $organization;
    }

    private function person(string $name, string $email, bool $verified = true): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(40))]);
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $user;
    }

    /** @param  list<Organization>  $orgs */
    private function counselor(string $name, array $orgs, VerificationStatus $status = VerificationStatus::Approved, ?string $paUntil = null): CounselorProfile
    {
        $n = ++$this->counter;
        $user = $this->person($name, 'demo.availability.counselor'.$n.'@example.test');

        $profile = CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => 'KBDA'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'pa_number' => 'PADA'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'pa_valid_until' => $paUntil ?? $this->today->addYear()->toDateString(), 'verification_type' => VerificationType::OrgAssigned,
            'verification_status' => $status, 'accepts_requests' => $n % 2 === 0, 'buffer_minutes' => 15,
        ]);

        foreach ($orgs as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }

        $profile->specializations()->create(['specialization' => 'Stress']);
        $profile->languages()->create(['language' => 'English']);

        return $profile;
    }

    private function slot(CounselorProfile $counselor, Organization $org, int $dayOffset, string $time, ?SessionMode $mode = SessionMode::Online, ?PracticeLocation $place = null, bool $available = true): CalendarEntry
    {
        $at = $this->today->addDays($dayOffset)->setTimeFromTimeString($time);

        return $this->slotAt($counselor, $org, $at, $mode, $place, $available);
    }

    private function slotAt(CounselorProfile $counselor, Organization $org, CarbonImmutable $at, ?SessionMode $mode, ?PracticeLocation $place = null, bool $available = true): CalendarEntry
    {
        return CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::AvailableSlot, 'context_org_id' => $org->id,
            'entry_date' => $at->toDateString(), 'start_time' => $at->format('H:i:00'), 'duration_minutes' => 60,
            'session_mode' => $mode, 'location_id' => $place?->id, 'is_available' => $available,
        ]);
    }

    private function block(CounselorProfile $counselor, int $dayOffset, string $time): void
    {
        $at = $this->today->addDays($dayOffset)->setTimeFromTimeString($time);

        CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::PersonalBlock, 'title' => 'Demo private appointment',
            'entry_date' => $at->toDateString(), 'start_time' => $at->format('H:i:00'), 'duration_minutes' => 60, 'is_available' => true,
        ]);
    }

    private function booking(User $client, CounselorProfile $counselor, Organization $org, CalendarEntry $slot, SessionMode $mode): void
    {
        Booking::create([
            'org_id' => $org->id, 'user_id' => $client->id, 'counselor_user_id' => $counselor->user_id, 'calendar_entry_id' => $slot->id,
            'booking_mode' => BookingMode::SlotBased, 'status' => BookingStatus::Confirmed, 'session_mode' => $mode,
            // An online session whose meeting link the counselor has not pasted yet: meeting_url stays empty.
        ]);
    }
}
