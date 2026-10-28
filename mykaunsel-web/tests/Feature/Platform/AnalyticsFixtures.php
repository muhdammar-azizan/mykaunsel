<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;

/**
 * Builds the small, known data the Analytics tests count. Every date is a text such as "2026-10-05 10:30" in the zone it is meant in.
 */
trait AnalyticsFixtures
{
    private int $fixtureCounter = 0;

    private ?Organization $platformOrg = null;

    protected function platform(): Organization
    {
        return $this->platformOrg ??= Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
    }

    /** @param  string|null  $reviewed  UTC text like "2026-10-05 08:00:00", or null for "no approval date" */
    protected function org(string $type, string $status, ?string $reviewed = null, ?string $submitted = null, int $count = 1): Organization
    {
        $n = ++$this->fixtureCounter;

        $org = Organization::create([
            'name' => "Fixture Org {$n}",
            'org_type' => OrgType::from($type),
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::from($status),
        ]);

        $org->forceFill(['reviewed_at' => $reviewed, 'submitted_at' => $submitted, 'submission_count' => $count])->saveQuietly();

        return $org;
    }

    /** @param  list<string>  $specializations */
    protected function counselor(string $type, string $status, ?string $reviewed = null, ?string $submitted = null, int $count = 1, array $specializations = []): CounselorProfile
    {
        $n = ++$this->fixtureCounter;

        $user = User::factory()->create(['name' => "Fixture Counselor {$n}", 'email' => "fixture.counselor.{$n}@example.test"]);

        $profile = CounselorProfile::create([
            'user_id' => $user->id,
            'kb_number' => "FIX-KB-{$n}",
            'pa_number' => "FIX-PA-{$n}",
            'pa_valid_until' => '2030-01-01',
            'verification_type' => $type === 'independent' ? VerificationType::PlatformVerified : VerificationType::OrgAssigned,
            'verification_status' => VerificationStatus::from($status),
        ]);

        $profile->forceFill(['reviewed_at' => $reviewed, 'submitted_at' => $submitted, 'submission_count' => $count])->saveQuietly();

        foreach ($specializations as $specialization) {
            $profile->specializations()->create(['specialization' => $specialization]);
        }

        return $profile;
    }

    /**
     * A booking. $at is the session's Malaysian wall-clock time ("2026-10-05 10:30"); null = a booking with no calendar entry (no date).
     * $org null = no organization at all.
     */
    protected function booking(CounselorProfile $counselor, ?Organization $org, string $status, ?string $at): Booking
    {
        $client = User::factory()->create();
        $entry = null;

        if ($at !== null) {
            [$date, $time] = explode(' ', $at);
            $entry = CalendarEntry::create([
                'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::AvailableSlot, 'context_org_id' => $org?->id,
                'entry_date' => $date, 'start_time' => $time.':00', 'duration_minutes' => 60, 'session_mode' => SessionMode::Online, 'is_available' => false,
            ]);
        }

        return Booking::create([
            'org_id' => $org?->id, 'user_id' => $client->id, 'counselor_user_id' => $counselor->user_id, 'calendar_entry_id' => $entry?->id,
            'booking_mode' => BookingMode::SlotBased, 'status' => BookingStatus::from($status), 'session_mode' => SessionMode::Online,
        ]);
    }

    /** Several bookings of one status on consecutive mornings in October 2026, starting at $firstDay. */
    protected function sessions(int $howMany, CounselorProfile $counselor, ?Organization $org, string $status = 'completed', int $firstDay = 2, string $month = '2026-10'): void
    {
        foreach (range(0, $howMany - 1) as $i) {
            $this->booking($counselor, $org, $status, sprintf('%s-%02d 10:00', $month, $firstDay + ($i % 20)));
        }
    }
}
