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
use App\Models\User;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DEMO data for the Platform Admin Analytics page: about six months of organizations and counselors approved at different times,
 * applications with different outcomes and decision times, and completed / no-show / cancelled sessions for all four kinds of
 * organization. Every name starts with "[Demo]" or "Demo", every email ends in @example.test, and the data is made up. Local and testing only.
 *
 * Built to show the edge cases too: an approved organization and approved counselors with NO approval date, a suspended organization and
 * counselor, a resubmitted application, an application without a submission date, a counselor whose rare specializations have fewer than
 * five sessions (they are grouped into "Other"), and a spelling variant of one specialization. Safe to run twice.
 */
class AnalyticsDemoSeeder extends Seeder
{
    use RefusesOutsideDevelopment;

    private int $counter = 0;

    public function run(): void
    {
        $this->refuseOutsideDevelopment();

        if (Organization::where('name', 'like', '[Demo] Analytics %')->exists()) {
            return;
        }

        mt_srand(20261028);   // the same demo data every time

        $platform = Organization::where('org_type', OrgType::Platform->value)->first()
            ?? Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);

        $orgs = $this->organizations();
        $independent = $this->independentCounselors($platform);
        $organizational = $this->organizationCounselors($orgs);

        $this->sessions($platform, $orgs, $independent, $organizational);
    }

    // ---- organizations ---------------------------------------------------------------------------------------

    /** @return array<string, list<Organization>> approved organizations by type */
    private function organizations(): array
    {
        // [type, status, submitted days ago, decided days ago (null = not decided / no date)]
        $rows = [
            ['university', 'active', 158, 152], ['university', 'active', 130, 124], ['university', 'active', 100, 93], ['university', 'active', 45, 41],
            ['university', 'suspended', 160, 155],
            ['corporate', 'active', 125, 118], ['corporate', 'active', 70, 66], ['corporate', 'active', 38, 30],
            ['corporate', 'active', 170, null],                                  // approved, but no approval date recorded
            ['clinic', 'active', 95, 90], ['clinic', 'active', 35, 33],
            ['university', 'pending', 3, null], ['corporate', 'pending', 10, null],
            ['corporate', 'rejected', 60, 55], ['clinic', 'rejected', 20, 12],
        ];

        $approved = ['university' => [], 'corporate' => [], 'clinic' => []];

        foreach ($rows as $i => [$type, $status, $submitted, $decided]) {
            $org = Organization::create([
                'name' => sprintf('[Demo] Analytics %s %02d', ucfirst($type), $i + 1),
                'org_type' => OrgType::from($type),
                'access_model' => AccessModel::Closed,
                'subscription_status' => OrgStatus::from($status),
                'estimated_size' => '51–200 members',
            ]);

            $org->forceFill([
                'submitted_at' => now()->subDays($submitted),
                'reviewed_at' => $decided === null ? null : now()->subDays($decided),
                'submission_count' => 1,
                'rejection_reason' => $status === 'rejected' ? '[Demo] Made-up reason.' : null,
                'suspension_reason' => $status === 'suspended' ? '[Demo] Made-up reason.' : null,
                'suspended_at' => $status === 'suspended' ? now()->subDays(20) : null,
                'created_at' => now()->subDays($submitted),
            ])->saveQuietly();

            if (in_array($status, ['active', 'suspended'], true)) {
                $approved[$type][] = $org;
            }
        }

        return $approved;
    }

    // ---- counselors ------------------------------------------------------------------------------------------

    /** @return array<string, CounselorProfile> by label (I1 ...) */
    private function independentCounselors(Organization $platform): array
    {
        // label => [status, submitted days ago (null = never completed), decided days ago, submission count, specializations]
        $stress = 'Stres & Kebimbangan';
        $rows = [
            'I1' => ['approved', 165, 160, 1, [$stress, 'Kemurungan']],
            'I2' => ['approved', 140, 133, 1, ['stres & kebimbangan', 'Kemurungan']],          // another spelling of the same thing
            'I3' => ['approved', 120, 119, 2, [$stress, 'Kemurungan', 'Hubungan & Keluarga']],   // resubmitted once
            'I4' => ['approved', 100, 92, 1, [$stress, 'Kemurungan', 'Hubungan & Keluarga']],
            'I5' => ['approved', 85, 80, 1, [$stress, 'Kemurungan', 'Kerjaya']],
            'I6' => ['approved', 60, 57, 1, [$stress, 'Hubungan & Keluarga', 'Kerjaya']],
            'I7' => ['approved', 50, 49, 1, [$stress, 'Kemurungan', 'Hubungan & Keluarga']],
            'I8' => ['approved', 30, 22, 1, [$stress, 'Trauma']],                               // "Trauma": fewer than five sessions
            'I9' => ['approved', 15, 6, 1, [$stress, 'Penagihan']],                             // "Penagihan": fewer than five sessions
            'I10' => ['suspended', 110, 100, 1, [$stress]],
            'P1' => ['pending', 4, null, 1, [$stress]],
            'P2' => ['pending', 12, null, 1, ['Kerjaya']],
            'P3' => ['pending', null, null, 1, ['Kemurungan']],                                  // documents not sent yet: no submission date
            'R1' => ['rejected', 70, 66, 1, [$stress]],
            'R2' => ['rejected', 25, 21, 1, ['Kerjaya']],
            'R3' => ['rejected', 10, 8, 1, ['Kemurungan']],
        ];

        $out = [];

        foreach ($rows as $label => [$status, $submitted, $decided, $count, $specializations]) {
            $profile = $this->counselor("Demo Independent {$label}", VerificationType::PlatformVerified, VerificationStatus::from($status), $platform, $specializations);

            $profile->forceFill([
                'submitted_at' => $submitted === null ? null : now()->subDays($submitted),
                'reviewed_at' => $decided === null ? null : now()->subDays($decided),
                'submission_count' => $count,
                'suspended_at' => $status === 'suspended' ? now()->subDays(30) : null,
                'suspension_reason' => $status === 'suspended' ? '[Demo] Made-up reason.' : null,
            ])->saveQuietly();

            $out[$label] = $profile;
        }

        return $out;
    }

    /** @return array<string, CounselorProfile> approved organization counselors: no approval flow, so no dates */
    private function organizationCounselors(array $orgs): array
    {
        $out = [];

        foreach ([['O1', $orgs['university'][0]], ['O2', $orgs['university'][1]], ['O3', $orgs['corporate'][0]], ['O4', $orgs['clinic'][0]]] as [$label, $org]) {
            $out[$label] = $this->counselor("Demo Organization {$label}", VerificationType::OrgAssigned, VerificationStatus::Approved, $org, ['Stres & Kebimbangan', 'Kerjaya', 'Kemurungan']);
        }

        return $out;
    }

    private function counselor(string $name, VerificationType $type, VerificationStatus $status, Organization $org, array $specializations): CounselorProfile
    {
        $n = ++$this->counter;

        $user = User::create([
            'name' => $name,
            'email' => sprintf('demo.analytics.%03d@example.test', $n),
            'password' => Str::random(40),    // nobody can sign in as a demo person
            'email_verified_at' => now(),
        ]);

        $profile = CounselorProfile::create([
            'user_id' => $user->id,
            'kb_number' => sprintf('DEMO-KB-%04d', $n),
            'pa_number' => sprintf('DEMO-PA-%04d', $n),
            'pa_valid_until' => now()->addYear()->toDateString(),
            'verification_type' => $type,
            'verification_status' => $status,
            'meeting_provider' => 'Google Meet',
            'accepts_requests' => false,
            'buffer_minutes' => 15,
        ]);

        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        foreach ($specializations as $specialization) {
            $profile->specializations()->create(['specialization' => $specialization]);
        }

        return $profile;
    }

    // ---- sessions --------------------------------------------------------------------------------------------

    private function sessions(Organization $platform, array $orgs, array $independent, array $organizational): void
    {
        $clients = [];
        foreach (range(1, 6) as $i) {
            $clients[] = User::create(['name' => "Demo Client {$i}", 'email' => sprintf('demo.client.%d@example.test', $i), 'password' => Str::random(40), 'email_verified_at' => now()]);
        }

        // type => [how many sessions, the counselors who deliver them, the organizations the sessions belong to]
        $main = array_values(array_filter($independent, fn ($p, $label) => in_array($label, ['I1', 'I2', 'I3', 'I4', 'I5', 'I6', 'I7'], true), ARRAY_FILTER_USE_BOTH));
        $plan = [
            'university' => [44, [$organizational['O1'], $organizational['O2']], $orgs['university']],
            'corporate' => [28, [$organizational['O3']], $orgs['corporate']],
            'clinic' => [14, [$organizational['O4']], $orgs['clinic']],
            'independent' => [26, $main, [$platform]],
        ];

        foreach ($plan as [$count, $counselors, $organizations]) {
            for ($i = 0; $i < $count; $i++) {
                $this->session($clients, $counselors[$i % count($counselors)], $organizations[$i % count($organizations)], $this->statusFor($i), mt_rand(2, 175));
            }
        }

        // Two independent counselors with only a few sessions each, so their rare specializations stay under the "fewer than five" line.
        foreach ([['I8', 3], ['I9', 2]] as [$label, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $this->session($clients, $independent[$label], $platform, BookingStatus::Completed, mt_rand(2, 60));
            }
        }

        // A few booked sessions still to come: they are not completed, so they are not counted.
        foreach ([1, 2, 5] as $daysAhead) {
            $this->session($clients, $organizational['O1'], $orgs['university'][0], BookingStatus::Confirmed, -$daysAhead);
        }
    }

    /** About 1 in 9 is a no-show and 1 in 8 is cancelled; the rest are completed. */
    private function statusFor(int $i): BookingStatus
    {
        return match (true) {
            $i % 9 === 4 => BookingStatus::NoShow,
            $i % 8 === 6 => BookingStatus::Cancelled,
            default => BookingStatus::Completed,
        };
    }

    private function session(array $clients, CounselorProfile $counselor, Organization $org, BookingStatus $status, int $daysAgo): void
    {
        $date = Carbon::now('Asia/Kuala_Lumpur')->subDays($daysAgo)->toDateString();

        $entry = CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id, 'entry_type' => CalendarEntryType::AvailableSlot, 'context_org_id' => $org->id,
            'entry_date' => $date, 'start_time' => sprintf('%02d:00:00', 9 + mt_rand(0, 8)), 'duration_minutes' => 60,
            'session_mode' => SessionMode::Online, 'is_available' => false,
        ]);

        Booking::create([
            'org_id' => $org->id, 'user_id' => $clients[mt_rand(0, count($clients) - 1)]->id, 'counselor_user_id' => $counselor->user_id, 'calendar_entry_id' => $entry->id,
            'booking_mode' => BookingMode::SlotBased, 'status' => $status, 'session_mode' => SessionMode::Online,
        ]);
    }
}
