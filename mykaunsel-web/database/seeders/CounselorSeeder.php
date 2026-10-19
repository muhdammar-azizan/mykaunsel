<?php

namespace Database\Seeders;

use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CounselorSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Organization::where('org_type', 'platform')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $klinikDamai = Organization::where('name', 'Klinik Kaunseling Damai')->firstOrFail();

        $this->createCounselor(
            name: 'Ahmad Zulkarnain bin Yusof',
            email: 'ahmad.zulkarnain@umpsa.edu.my',
            kbNumber: 'KB10234',
            org: $umpsa,
            verificationType: VerificationType::OrgAssigned,
            displayNameOrg: 'Kaunselor Ahmad Zulkarnain',
        );

        $this->createCounselor(
            name: 'Siti Nurhaliza binti Rahman',
            email: 'siti.nurhaliza@umpsa.edu.my',
            kbNumber: 'KB10567',
            org: $umpsa,
            verificationType: VerificationType::OrgAssigned,
            displayNameOrg: 'Kaunselor Siti Nurhaliza',
        );

        $this->createCounselor(
            name: 'Tan Wei Ming',
            email: 'tan.weiming@gmail.com',
            kbNumber: 'KB10891',
            org: $platform,
            verificationType: VerificationType::PlatformVerified,
            displayNameIndependent: 'Kaunselor Tan Wei Ming',
        );

        $this->createCounselor(
            name: 'Aina Syazwani binti Rosli',
            email: 'aina.syazwani@klinikdamai.com',
            kbNumber: 'KB14245',
            org: $klinikDamai,
            verificationType: VerificationType::OrgAssigned,
            displayNameOrg: 'Kaunselor Aina Syazwani',
        );
    }

    private function createCounselor(
        string $name,
        string $email,
        string $kbNumber,
        Organization $org,
        VerificationType $verificationType,
        ?string $displayNameOrg = null,
        ?string $displayNameIndependent = null,
    ): void {
        $lkmRecord = LkmDirectorySnapshot::where('kb_number', $kbNumber)->firstOrFail();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $profile = CounselorProfile::create([
            'user_id' => $user->id,
            'kb_number' => $lkmRecord->kb_number,
            'pa_number' => $lkmRecord->pa_number,
            'pa_valid_until' => $lkmRecord->pa_valid_until,
            'verification_type' => $verificationType,
            'verification_status' => VerificationStatus::Approved,
            'meeting_provider' => 'Google Meet',
            'display_name_org' => $displayNameOrg,
            'display_name_independent' => $displayNameIndependent,
            'accepts_requests' => true,
            'buffer_minutes' => 15,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'org_id' => $org->id,
            'role' => MembershipRole::Counselor,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        foreach ([1, 2, 3] as $daysAhead) {
            CalendarEntry::create([
                'counselor_user_id' => $user->id,
                'entry_type' => CalendarEntryType::AvailableSlot,
                'context_org_id' => $org->id,
                'entry_date' => now()->addDays($daysAhead)->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 60,
                'session_mode' => SessionMode::Online,
                'is_available' => true,
            ]);
        }

        $profile->languages()->create(['language' => 'Bahasa Malaysia']);
        $profile->languages()->create(['language' => 'English']);
        $profile->specializations()->create(['specialization' => 'Stres & Kebimbangan']);
    }
}
