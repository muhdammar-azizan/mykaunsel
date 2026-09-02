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

    private function recordHasValidPa(LkmDirectorySnapshot $record): bool
    {
        return $record->pa_valid_until->greaterThanOrEqualTo(now()->startOfDay());
    }

    private function namesMatch(string $a, string $b): bool
    {
        return Str::lower(trim($a)) === Str::lower(trim($b));
    }
}
