<?php

namespace Database\Seeders;

use App\Models\LkmDirectorySnapshot;
use Illuminate\Database\Seeder;

class LkmSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            // 12 rekod: status Aktif, PA sah
            ['KB10234', 'PA20345', 'Ahmad Zulkarnain bin Yusof', 'Aktif', '2024-01-01', '2027-06-30', 'ahmad.zulkarnain@example.com', 'Kuantan', 'Pahang'],
            ['KB10567', 'PA20678', 'Siti Nurhaliza binti Rahman', 'Aktif', '2024-01-01', '2027-08-15', 'siti.nurhaliza@example.com', 'Kuantan', 'Pahang'],
            ['KB10891', 'PA20912', 'Tan Wei Ming', 'Aktif', '2024-02-01', '2027-09-20', 'tan.weiming@example.com', 'Kuala Lumpur', 'Wilayah Persekutuan'],
            ['KB11023', 'PA21034', 'Kavitha a/p Muniandy', 'Aktif', '2024-02-15', '2027-10-01', 'kavitha.muniandy@example.com', 'Petaling Jaya', 'Selangor'],
            ['KB11256', 'PA21267', 'Muhammad Haziq bin Ismail', 'Aktif', '2024-03-01', '2027-11-11', 'haziq.ismail@example.com', 'Kuantan', 'Pahang'],
            ['KB11489', 'PA21490', 'Lee Mei Ling', 'Aktif', '2024-03-15', '2027-12-05', 'lee.meiling@example.com', 'Ipoh', 'Perak'],
            ['KB11702', 'PA21713', 'Nur Ain binti Hashim', 'Aktif', '2024-04-01', '2028-01-20', 'nur.ain@example.com', 'Kuantan', 'Pahang'],
            ['KB11935', 'PA21946', 'Rajesh a/l Kumar', 'Aktif', '2024-04-15', '2028-02-14', 'rajesh.kumar@example.com', 'Johor Bahru', 'Johor'],
            ['KB12168', 'PA22179', 'Wong Kah Wai', 'Aktif', '2024-05-01', '2028-03-30', 'wong.kahwai@example.com', 'Kuching', 'Sarawak'],
            ['KB12391', 'PA22402', 'Farah Adilah binti Zainal', 'Aktif', '2024-05-15', '2028-04-18', 'farah.adilah@example.com', 'Kuantan', 'Pahang'],
            ['KB12624', 'PA22635', 'Chong Yee Sheng', 'Aktif', '2024-06-01', '2028-05-25', 'chong.yeesheng@example.com', 'Melaka', 'Melaka'],
            ['KB12857', 'PA22868', 'Aisyah binti Kamal', 'Aktif', '2024-06-15', '2028-06-30', 'aisyah.kamal@example.com', 'Kuantan', 'Pahang'],

            // 3 rekod: status Aktif, PA sudah luput
            ['KB13090', 'PA23101', 'Mohd Ridzuan bin Salleh', 'Aktif', '2022-06-01', '2025-11-30', 'ridzuan.salleh@example.com', 'Alor Setar', 'Kedah'],
            ['KB13323', 'PA23334', 'Priya a/p Segaran', 'Aktif', '2022-09-01', '2026-02-28', 'priya.segaran@example.com', 'Seremban', 'Negeri Sembilan'],
            ['KB13556', 'PA23567', 'Lim Chun Hong', 'Aktif', '2022-12-01', '2026-06-15', 'lim.chunhong@example.com', 'Kuantan', 'Pahang'],

            // 2 rekod: nama sengaja salah eja (untuk uji padanan gagal)
            ['KB13789', 'PA23800', 'Nurul Huda bt Othmn', 'Aktif', '2024-07-01', '2028-07-20', 'nurul.huda@example.com', 'Kuantan', 'Pahang'],
            ['KB14012', 'PA24023', 'Muhamad Firdaus b. Rzali', 'Aktif', '2024-07-15', '2028-08-05', 'firdaus.razali@example.com', 'Temerloh', 'Pahang'],

            // 1 rekod: sudah "didaftarkan di platform" (untuk uji duplikasi)
            ['KB14245', 'PA24256', 'Aina Syazwani binti Rosli', 'Aktif', '2024-08-01', '2028-09-10', 'aina.syazwani@example.com', 'Kuantan', 'Pahang'],

            // 2 rekod: status bukan Aktif
            ['KB14478', 'PA24489', 'Hafiz bin Omar', 'Tidak Aktif', '2020-01-01', '2023-01-01', 'hafiz.omar@example.com', 'Kuantan', 'Pahang'],
            ['KB14701', 'PA24712', 'Devi a/p Ramasamy', 'Digantung', '2021-01-01', '2024-01-01', 'devi.ramasamy@example.com', 'Shah Alam', 'Selangor'],
        ];

        foreach ($records as [$kb, $pa, $name, $status, $validFrom, $validUntil, $email, $city, $state]) {
            LkmDirectorySnapshot::create([
                'kb_number' => $kb,
                'pa_number' => $pa,
                'full_name' => $name,
                'status' => $status,
                'pa_valid_from' => $validFrom,
                'pa_valid_until' => $validUntil,
                'email' => $email,
                'city' => $city,
                'state' => $state,
            ]);
        }
    }
}
