<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        $students = [
            ['Nur Amalina binti Zulkifli', 'amalina.zulkifli@adab.umpsa.edu.my'],
            ['Amirul Hakim bin Rosli', 'amirul.hakim@adab.umpsa.edu.my'],
            ['Chong Li Wen', 'chong.liwen@adab.umpsa.edu.my'],
        ];

        foreach ($students as [$name, $email]) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            Membership::create([
                'user_id' => $user->id,
                'org_id' => $umpsa->id,
                'role' => MembershipRole::Student,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
        }

        $publicUsers = [
            ['Farid Iskandar bin Adnan', 'farid.iskandar@gmail.com'],
            ['Michelle Tan Xin Yi', 'michelle.tan@gmail.com'],
        ];

        foreach ($publicUsers as [$name, $email]) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
