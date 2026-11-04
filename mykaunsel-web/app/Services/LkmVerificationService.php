<?php

namespace App\Services;

use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Support\LkmComparison;
use App\Support\PaValidity;
use Illuminate\Support\Str;

class LkmVerificationService
{
    public const ACTIVE_STATUS = 'Aktif';

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

    /** The one answer for "this KB number, name and PA number do not match a record". It never says which part is wrong, so the sign-up can not be used to search the directory. */
    public const NOT_VERIFIED_MESSAGE = 'We could not verify these details against the LKM directory. Please check your KB number, PA number and full name exactly as they are in your LKM registration.';

    public const ALREADY_REGISTERED_MESSAGE = 'This KB number is already registered on MyKaunsel.';

    public const NOT_ACTIVE_MESSAGE = 'The LKM directory record for this KB number is not active, so we can not register you yet. Please check your registration with LKM, or contact support.';

    /**
     * Returns a human-readable reason the given details failed verification, or null if they would pass.
     *
     * Nothing specific is said until the KB number, the name AND the PA number all match one record (someone who knows all three is
     * the person the record belongs to). Before that every failure gets the same message: no record, a wrong name and a wrong PA
     * number look identical, and so does a KB number that already has an account. Only then: already registered, or a PA that has run out.
     */
    public function verificationFailureReason(string $kbNumber, string $paNumber, string $fullName): ?string
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kbNumber)->first();

        if (! $record || ! $this->namesMatch($record->full_name, $fullName) || $record->pa_number !== $paNumber) {
            return self::NOT_VERIFIED_MESSAGE;
        }

        if ($this->isAlreadyRegistered($kbNumber)) {
            return self::ALREADY_REGISTERED_MESSAGE;
        }

        // Said apart: an LKM record that is not active (suspended or lapsed by LKM) is not an expired certificate.
        if ($record->status !== self::ACTIVE_STATUS) {
            return self::NOT_ACTIVE_MESSAGE;
        }

        if (! $this->recordHasValidPa($record)) {
            return 'Your practicing certificate (PA) has expired.';
        }

        return null;
    }

    /**
     * Compare an existing profile with the LKM snapshot, field by field (for the Platform Admin review card).
     * Unlike verificationFailureReason() this is for a profile that is ALREADY registered, so it never says
     * "already registered".
     */
    public function compare(CounselorProfile $profile): LkmComparison
    {
        return $this->buildComparison($profile, LkmDirectorySnapshot::where('kb_number', $profile->kb_number)->first());
    }

    /**
     * Compare many profiles with one query. Keyed by profile id.
     *
     * @param  iterable<CounselorProfile>  $profiles
     * @return \Illuminate\Support\Collection<int, LkmComparison>
     */
    public function compareMany(iterable $profiles): \Illuminate\Support\Collection
    {
        $profiles = collect($profiles);
        $records = LkmDirectorySnapshot::whereIn('kb_number', $profiles->pluck('kb_number')->unique())->get()->keyBy('kb_number');

        return $profiles->mapWithKeys(fn (CounselorProfile $p) => [$p->id => $this->buildComparison($p, $records->get($p->kb_number))]);
    }

    /**
     * Copy the PA valid-until date from the LKM record into the profile — only if the record exists, has this KB
     * number and is Active. Never touches the PA number or the name, so a mismatch there keeps showing as a warning.
     * Database only: call it inside the transaction that also writes the audit entry.
     *
     * @return array<string, array{old: ?string, new: ?string}> the change, ready for the audit log (empty = nothing changed)
     */
    public function refreshPaDate(CounselorProfile $profile): array
    {
        $record = LkmDirectorySnapshot::where('kb_number', $profile->kb_number)->first();

        if ($record === null || $record->status !== self::ACTIVE_STATUS || $record->pa_valid_until->isSameDay($profile->pa_valid_until)) {
            return [];
        }

        $old = $profile->pa_valid_until?->toDateString();
        $profile->update(['pa_valid_until' => $record->pa_valid_until->toDateString()]);

        return ['pa_valid_until' => ['old' => $old, 'new' => $record->pa_valid_until->toDateString()]];
    }

    private function buildComparison(CounselorProfile $profile, ?LkmDirectorySnapshot $record): LkmComparison
    {
        $name = $profile->user?->name ?? '';
        $problems = [];
        $fields = [];

        $row = fn (string $label, ?string $submitted, ?string $recordValue, bool $matches) => [
            'label' => $label,
            'submitted' => $submitted,
            'record' => $recordValue,
            'state' => $record === null ? 'info' : ($matches ? 'match' : 'mismatch'),
        ];

        $nameMatches = $record !== null && $this->namesMatch($record->full_name, $name);
        $paMatches = $record !== null && $record->pa_number === $profile->pa_number;
        $statusActive = $record !== null && $record->status === self::ACTIVE_STATUS;
        $recordPaValid = $record !== null && $this->recordHasValidPa($record);
        $sameDate = $record !== null && $record->pa_valid_until->isSameDay($profile->pa_valid_until);

        $fields[] = $row('Name', $name, $record?->full_name, $nameMatches);
        $fields[] = $row('KB number', $profile->kb_number, $record?->kb_number, $record !== null);
        $fields[] = $row('PA number', $profile->pa_number, $record?->pa_number, $paMatches);
        $fields[] = ['label' => 'Status', 'submitted' => null, 'record' => $record?->status, 'state' => $record === null ? 'info' : ($statusActive ? 'match' : 'mismatch')];
        $fields[] = $row('PA valid until', $profile->pa_valid_until?->format('j M Y'), $record?->pa_valid_until->format('j M Y'), $sameDate && $recordPaValid);

        if ($record === null) {
            return new LkmComparison(LkmComparison::NOT_FOUND, null, $fields, ['No record for this KB number in the LKM directory.']);
        }

        $outcome = LkmComparison::MATCH;

        if (! $statusActive) {
            $outcome = LkmComparison::INACTIVE;
            $problems[] = "The LKM record is “{$record->status}”, not active.";
        } elseif (! $recordPaValid) {
            $outcome = LkmComparison::EXPIRED;
            $problems[] = 'The practising certificate expired on '.$record->pa_valid_until->format('j M Y').' in LKM.';
        }

        $warnings = [];
        if (! $nameMatches) {
            $warnings[] = 'The name does not match the LKM record.';
        }
        if (! $paMatches) {
            $warnings[] = 'The PA number does not match the LKM record.';
        }
        if (! $sameDate) {
            $warnings[] = 'The PA valid-until date differs from the LKM record (it may have changed since the application).';
        }

        if ($warnings && $outcome === LkmComparison::MATCH) {
            $outcome = LkmComparison::MISMATCH;
        }

        return new LkmComparison($outcome, $record, $fields, [...$problems, ...$warnings]);
    }

    private function recordHasValidPa(LkmDirectorySnapshot $record): bool
    {
        return PaValidity::isValid($record->pa_valid_until);
    }

    /**
     * Two spellings of one name: capitals, spacing, punctuation and accents do not matter ("AINA  BINTI   ali" is "Aina binti Ali").
     * A word is never expanded, cut or swapped: "Aina bt. Ali" is NOT "Aina binti Ali". See normalizeName().
     */
    private function namesMatch(string $a, string $b): bool
    {
        $left = self::normalizeName($a);

        return $left !== '' && $left === self::normalizeName($b);
    }

    /**
     * Lower case, accents removed (é becomes e: letters of any script stay as they are, nothing is transliterated), apostrophes dropped,
     * every other mark (dot, comma, hyphen, slash) read as a space, spaces collapsed. "Sa'adah", "SAADAH" and "saadah" are one name;
     * "a/p" and "A. P." are too. "bin", "binti", "bt" and "b." are different words and stay different.
     */
    public static function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        if (class_exists(\Normalizer::class)) {
            $name = (string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($name, \Normalizer::FORM_D) ?: $name);
        }

        $name = (string) preg_replace('/[\'’‘`]+/u', '', $name);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    }
}
