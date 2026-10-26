<?php

namespace Database\Seeders;

use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\PracticeLocation;
use Illuminate\Database\Seeder;

/**
 * Sample content for the mobile app's counselor search (made-up; safe to run twice). Run it AFTER the counselor
 * seeders:  php artisan db:seed --class=DirectorySampleSeeder
 *
 * Gives each sample counselor a few specializations and languages and slots over the next two weeks (online, and
 * in person at a made-up clinic), so the search, the filters and "next available" have something to show.
 */
class DirectorySampleSeeder extends Seeder
{
    private const PEOPLE = [
        // kb => [specializations, languages, mode of the slots]
        'KB10234' => [['Stres & Kebimbangan', 'Pelajar & Kerjaya'], ['Bahasa Malaysia', 'English'], 'online'],
        'KB10567' => [['Kemurungan', 'Hubungan'], ['Bahasa Malaysia'], 'online'],
        'KB10891' => [['Trauma', 'Kesedihan & Kehilangan', 'Stres & Kebimbangan'], ['English', 'Mandarin', 'Bahasa Malaysia'], 'both'],
        'KB14245' => [['Keluarga & Perkahwinan', 'Kemurungan'], ['Bahasa Malaysia', 'English'], 'both'],
    ];

    public function run(): void
    {
        $clinic = Organization::where('name', 'Klinik Kaunseling Damai')->first();

        $place = $clinic ? PracticeLocation::firstOrCreate(
            ['owner_type' => 'organization', 'owner_id' => $clinic->id, 'name' => 'Klinik Damai (sample)'],
            ['address' => '1 Jalan Contoh (made up)', 'city' => 'Kuantan', 'state' => 'Pahang', 'postcode' => '25000', 'description' => 'Sample', 'is_primary' => false],
        ) : null;

        foreach (self::PEOPLE as $kb => [$specializations, $languages, $modes]) {
            $profile = CounselorProfile::where('kb_number', $kb)->first();

            if ($profile === null) {
                continue;
            }

            foreach ($specializations as $name) {
                $profile->specializations()->firstOrCreate(['specialization' => $name]);
            }

            foreach ($languages as $name) {
                $profile->languages()->firstOrCreate(['language' => $name]);
            }

            // Slots in every organization the counselor really works for.
            foreach ($profile->memberships()->where('role', 'counselor')->where('status', 'active')->get() as $membership) {
                foreach (range(1, 14) as $day) {
                    if (in_array(now()->addDays($day)->dayOfWeekIso, [6, 7], true)) {
                        continue;
                    }

                    foreach (['10:00:00', '14:00:00'] as $time) {
                        $physical = $modes === 'both' && $place !== null && $membership->org_id === $clinic?->id && $day % 2 === 0;

                        $date = now()->addDays($day)->toDateString();

                        // The date column is a "date" cast (stored with a time part), so it is compared with whereDate.
                        $exists = CalendarEntry::where('counselor_user_id', $profile->user_id)
                            ->where('context_org_id', $membership->org_id)
                            ->whereDate('entry_date', $date)
                            ->where('start_time', $time)
                            ->exists();

                        if (! $exists) {
                            CalendarEntry::create([
                                'counselor_user_id' => $profile->user_id,
                                'context_org_id' => $membership->org_id,
                                'entry_date' => $date,
                                'start_time' => $time,
                                'entry_type' => CalendarEntryType::AvailableSlot,
                                'duration_minutes' => 60,
                                'session_mode' => $physical ? SessionMode::Physical : SessionMode::Online,
                                'location_id' => $physical ? $place->id : null,
                                'is_available' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
