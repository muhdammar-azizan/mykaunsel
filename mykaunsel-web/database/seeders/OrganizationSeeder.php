<?php

namespace Database\Seeders;

use App\Enums\AccessModel;
use App\Enums\DomainMatchType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\OrgInviteCode;
use App\Models\Organization;
use App\Models\PracticeLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // UMPSA
        $umpsa = Organization::create([
            'name' => 'Universiti Malaysia Pahang Al-Sultan Abdullah (UMPSA)',
            'org_type' => OrgType::University,
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Active,
        ]);

        OrgDomain::create([
            'org_id' => $umpsa->id,
            'domain' => 'umpsa.edu.my',
            'default_role' => MembershipRole::Staff,
            'match_type' => DomainMatchType::Exact,
            'dns_verified' => true,
            'verified_at' => now(),
        ]);

        OrgDomain::create([
            'org_id' => $umpsa->id,
            'domain' => 'adab.umpsa.edu.my',
            'default_role' => MembershipRole::Student,
            'match_type' => DomainMatchType::Exact,
            'dns_verified' => true,
            'verified_at' => now(),
        ]);

        OrgInviteCode::create([
            'org_id' => $umpsa->id,
            'code' => 'UMPSA-7K2M',
            'expires_at' => null,
            'max_uses' => 100,
        ]);

        $afif = User::create([
            'name' => 'Afif Hakimi bin Zainuddin',
            'email' => 'afif@umpsa.edu.my',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'user_id' => $afif->id,
            'org_id' => $umpsa->id,
            'role' => MembershipRole::OrgAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        // Klinik Kaunseling Damai
        $klinikDamai = Organization::create([
            'name' => 'Klinik Kaunseling Damai',
            'org_type' => OrgType::Clinic,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        PracticeLocation::create([
            'owner_type' => 'organization',
            'owner_id' => $klinikDamai->id,
            'name' => 'Klinik Kaunseling Damai - Cawangan Kuantan',
            'address' => 'No. 12, Jalan Beserah, Taman Damai',
            'city' => 'Kuantan',
            'state' => 'Pahang',
            'postcode' => '25300',
            'is_primary' => true,
        ]);

        $klinikAdmin = User::create([
            'name' => 'Nur Aisyah binti Mahmud',
            'email' => 'admin@klinikdamai.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'user_id' => $klinikAdmin->id,
            'org_id' => $klinikDamai->id,
            'role' => MembershipRole::OrgAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }
}
