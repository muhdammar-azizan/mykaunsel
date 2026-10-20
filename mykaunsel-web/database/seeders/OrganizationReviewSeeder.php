<?php

namespace Database\Seeders;

use App\Enums\AccessModel;
use App\Enums\DomainMatchType;
use App\Enums\JoinMethod;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\LocationPhoto;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\PracticeLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample applications for the Platform Admin → Organizations review pages.
 */
class OrganizationReviewSeeder extends Seeder
{
    /** Safe to run again: each group is skipped when its first organization already exists. */
    public function run(): void
    {
        $platformAdmin = User::whereHas('memberships', fn ($q) => $q->where('role', MembershipRole::PlatformAdmin))->first();

        if (! Organization::where('name', 'Universiti Teknologi Contoh')->exists()) {
            $this->seedWaitingAndSuspended($platformAdmin);
        }

        if (! Organization::where('name', 'Syarikat Ditolak Enterprise')->exists()) {
            $this->application('Syarikat Ditolak Enterprise', OrgType::Corporate, [
                'estimated_size' => '10 - 50',
                'join_method' => JoinMethod::InviteCode,
                'subscription_status' => OrgStatus::Rejected,
                'rejection_reason' => 'We could not verify this company. The SSM certificate provided is expired.',
                'reviewed_by' => $platformAdmin?->id,
                'reviewed_at' => now()->subDays(12),
                'submitted_at' => now()->subDays(15),
            ], 'Encik Razak Ali', 'razak@ditolak.com.my', 'Director');
        }
    }

    private function seedWaitingAndSuspended(?User $platformAdmin): void
    {
        // 1. University, DNS verified — waiting 2 days
        $uni = $this->application('Universiti Teknologi Contoh', OrgType::University, [
            'estimated_size' => '5,000 - 20,000',
            'join_method' => JoinMethod::Domain,
            'submitted_at' => now()->subDays(2),
        ], 'Dr. Hafiz Rahman', 'hafiz@utc.edu.my', 'Registrar');
        $this->domain($uni, 'utc.edu.my', MembershipRole::Staff, verified: true);
        $this->domain($uni, 'student.utc.edu.my', MembershipRole::Student, verified: true);

        // 2. University that skipped DNS — waiting 6 days (amber)
        $skipped = $this->application('Kolej Universiti Skip DNS', OrgType::University, [
            'estimated_size' => '1,000 - 5,000',
            'join_method' => JoinMethod::Domain,
            'submitted_at' => now()->subDays(6),
        ], 'Puan Salmah Ibrahim', 'salmah@kuskip.edu.my', 'Student Affairs Officer');
        $this->domain($skipped, 'kuskip.edu.my', MembershipRole::Student, verified: false, attempts: 3);

        // 3. Corporate without SSM — waiting 9 days (red)
        $this->application('Syarikat Tanpa SSM Sdn Bhd', OrgType::Corporate, [
            'estimated_size' => '50 - 200',
            'join_method' => JoinMethod::InviteCode,
            'ssm_number' => null,
            'submitted_at' => now()->subDays(9),
        ], 'Encik Kamal Yusof', 'kamal@tanpassm.com.my', 'HR Manager');

        // 4. Clinic with premises photos — waiting 1 day
        $clinic = $this->application('Klinik Minda Sihat', OrgType::Clinic, [
            'access_model' => AccessModel::Open,
            'ssm_number' => '202301045678',
            'submitted_at' => now()->subDay(),
        ], 'Dr. Aina Sofea', 'aina@mindasihat.com', 'Clinic Director');
        $location = PracticeLocation::create([
            'owner_type' => 'organization',
            'owner_id' => $clinic->id,
            'name' => 'Klinik Minda Sihat - Shah Alam',
            'address' => 'No. 8, Jalan Kemuning 33/2, Seksyen 33',
            'city' => 'Shah Alam',
            'state' => 'Selangor',
            'postcode' => '40400',
            'is_primary' => true,
        ]);
        foreach ([1, 2, 3] as $i) {
            LocationPhoto::create([
                'practice_location_id' => $location->id,
                'photo_url' => "https://placehold.co/800x600?text=Premises+{$i}",
                'sort_order' => $i,
            ]);
        }

        // 5. Rejected once, then resubmitted (attempt 2) — waiting 3 days
        $this->application('Syarikat Hantar Semula Berhad', OrgType::Corporate, [
            'estimated_size' => '200 - 1,000',
            'join_method' => JoinMethod::InviteCode,
            'ssm_number' => '201901234567',
            'submission_count' => 2,
            'rejection_reason' => 'SSM number does not match the company name. Please check and resubmit.',
            'reviewed_by' => $platformAdmin?->id,
            'reviewed_at' => now()->subDays(5),
            'submitted_at' => now()->subDays(3),
        ], 'Cik Nurul Huda', 'huda@hantarsemula.com.my', 'Operations Manager');

        // 6. Suspended organization
        $this->application('Pusat Kaunseling Digantung', OrgType::Clinic, [
            'access_model' => AccessModel::Open,
            'ssm_number' => '201801098765',
            'subscription_status' => OrgStatus::Suspended,
            'suspension_reason' => 'Repeated complaints about unlicensed practitioners.',
            'suspended_at' => now()->subDays(4),
            'reviewed_by' => $platformAdmin?->id,
            'reviewed_at' => now()->subMonths(2),
            'submitted_at' => now()->subMonths(3),
        ], 'Encik Zulkifli Hassan', 'zul@pusatdigantung.com', 'Owner');
    }

    /** Creates the organization plus its registering admin (with the authority confirmation recorded). */
    private function application(string $name, OrgType $type, array $overrides, string $adminName, string $adminEmail, string $adminTitle): Organization
    {
        $submittedAt = $overrides['submitted_at'] ?? now();

        $organization = Organization::create($overrides + [
            'name' => $name,
            'org_type' => $type,
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Pending,
            'ssm_number' => $type === OrgType::University ? null : '202001011234',
            'authority_confirmed_at' => $submittedAt,
        ]);

        $admin = User::create([
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'user_id' => $admin->id,
            'org_id' => $organization->id,
            'role' => MembershipRole::OrgAdmin,
            'title' => $adminTitle,
            'status' => MembershipStatus::Active,
            'joined_at' => $submittedAt,
        ]);

        return $organization;
    }

    private function domain(Organization $org, string $domain, MembershipRole $role, bool $verified, int $attempts = 0): void
    {
        OrgDomain::create([
            'org_id' => $org->id,
            'domain' => $domain,
            'default_role' => $role,
            'match_type' => DomainMatchType::Exact,
            'verification_token' => 'mykaunsel-verify='.str_repeat('a', 32),
            'dns_verified' => $verified,
            'verified_at' => $verified ? now()->subDay() : null,
            'last_checked_at' => ($verified || $attempts) ? now()->subHours(3) : null,
            'check_attempts' => $verified ? 1 : $attempts,
        ]);
    }
}
