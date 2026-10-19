<?php

namespace App\Services;

use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use Illuminate\Support\Str;

class LkmVerificationService
{
    private const ACTIVE_STATUS = 'Aktif';

    public function verify(string $kbNumber, string $paNumber, string $fullName): bool
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kbNumber)->first();

        if (! $record) {
            return false;
        }

        if ($record->status !== self::ACTIVE_STATUS) {
            return false;
        }

        if ($record->pa_number !== $paNumber) {
            return false;
        }

        if (! $this->namesMatch($record->full_name, $fullName)) {
            return false;
        }

        return $this->recordHasValidPa($record);
    }

    public function isPaValid(string $kbNumber): bool
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kbNumber)->first();

        return $record !== null && $this->recordHasValidPa($record);
    }

    public function isAlreadyRegistered(string $kbNumber): bool
    {
        return CounselorProfile::where('kb_number', $kbNumber)->exists();
    }

    /**
     * Returns a specific, human-readable reason the given details failed
     * verification, or null if they would pass. Checked in priority order
     * so the counselor sees the single most relevant problem first.
     */
    public function verificationFailureReason(string $kbNumber, string $paNumber, string $fullName): ?string
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kbNumber)->first();

        if (! $record) {
            return 'No record found for this KB number.';
        }

        if ($this->isAlreadyRegistered($kbNumber)) {
            return 'This KB number is already registered on MyKaunsel.';
        }

        if (! $this->namesMatch($record->full_name, $fullName)) {
            return "The name provided doesn't match our records for {$kbNumber}. Please check the spelling matches your official LKM registration exactly.";
        }

        if ($record->pa_number !== $paNumber) {
            return "The PA number provided doesn't match our records for {$kbNumber}.";
        }

        if ($record->status !== self::ACTIVE_STATUS || ! $this->recordHasValidPa($record)) {
            return 'Your practicing certificate (PA) has expired.';
        }

        return null;
    }

    private function recordHasValidPa(LkmDirectorySnapshot $record): bool
    {
        return $record->pa_valid_until->greaterThanOrEqualTo(now()->startOfDay());
    }

    private function namesMatch(string $a, string $b): bool
    {
        return Str::lower(trim($a)) === Str::lower(trim($b));
    }
}
