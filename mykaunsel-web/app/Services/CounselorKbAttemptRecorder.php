<?php

namespace App\Services;

use App\Facades\Audit;
use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a blocked attempt to register a KB number that already belongs to an account, so Platform Admin can see
 * it on the ORIGINAL account (a stolen identity, or someone helping a colleague "register again").
 *
 * - One record per KB number + IP per hour, so repeated clicks (or a script) cannot flood the table.
 * - The attempt row and its audit entry are written together.
 * - It never tells the person trying anything about the account holder (the caller keeps showing the same message).
 */
class CounselorKbAttemptRecorder
{
    public const WINDOW_MINUTES = 60;

    public function record(string $kbNumber, string $submittedName, ?string $ip): ?CounselorKbAttempt
    {
        $profile = CounselorProfile::where('kb_number', $kbNumber)->first();

        if ($profile === null) {
            return null;
        }

        $alreadyRecorded = CounselorKbAttempt::query()
            ->where('counselor_profile_id', $profile->id)
            ->where('kb_number', $kbNumber)
            ->where('ip', $ip)
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        return DB::transaction(function () use ($profile, $kbNumber, $submittedName, $ip) {
            $attempt = CounselorKbAttempt::create([
                'counselor_profile_id' => $profile->id,
                'kb_number' => $kbNumber,
                'submitted_name' => Str::limit(trim($submittedName), 255, ''),
                'ip' => $ip,
            ]);

            // Reference id only: the typed name stays in this table, not in the audit log.
            Audit::log('counselor.kb_duplicate_flagged', $profile, ['attempt_id' => $attempt->id]);

            return $attempt;
        });
    }
}
