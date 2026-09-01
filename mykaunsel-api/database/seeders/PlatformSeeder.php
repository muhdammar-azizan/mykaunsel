<?php

namespace Database\Seeders;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'admin@mykaunsel.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'user_id' => $admin->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }
}
