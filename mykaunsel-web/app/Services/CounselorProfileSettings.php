<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Support\LkmComparison;
use App\Support\PaValidity;
use Illuminate\Support\Facades\DB;

/**
 * What a counselor sees and may change on their own Profile page (portal).
 *
 * - Read only here: verification status, KB and PA numbers and the PA date. They come from the application and the LKM check; only the MyKaunsel
 *   team changes them.
 * - Changeable by the counselor: the names clients see (one for an organization, one for independent work; empty = the account name), the languages and
 *   specializations (picked from lists in config/counselors.php, so no free text reaches the public profile), whether time requests are taken, the
 *   usual meeting app, and the buffer between sessions.
 * - There is no photo and no free-text bio: a public photo or text needs an approval step first (docs/PUBLIC_COUNSELOR_DATA.md).
 * - Audit: that the profile changed and which fields, never the values.
 */
class CounselorProfileSettings
{
    /** How many specializations of their own a counselor may add (they are shown on the profile only: not a search filter, not in the analytics). */
    public const CUSTOM_MAX = 3;

    /** One of their own: at most this many characters and words, letters only (with spaces, & - ' /), so no number, address or link fits. */
    public const CUSTOM_MAX_CHARS = 30;

    public const CUSTOM_MAX_WORDS = 3;

    /** @return array<string, mixed> */
    public function for(User $counselor): array
    {
        $profile = $this->profile($counselor);

        $languages = $profile->languages()->pluck('language')->all();
        $specializations = $profile->specializations()->where('is_custom', false)->pluck('specialization')->all();
        $custom = $profile->specializations()->where('is_custom', true)->pluck('specialization')->all();
        $left = PaValidity::daysLeft($profile->pa_valid_until);

        return [
            'status' => $profile->verification_status,
            'independent' => $profile->isIndependent(),
            'kb_number' => $profile->kb_number,
            'pa_number' => $profile->pa_number,
            'pa_valid_until' => $profile->pa_valid_until?->format('j M Y'),
            'pa_days_left' => $left,
            'account_name' => (string) $counselor->name,
            'display_name_org' => (string) $profile->display_name_org,
            'display_name_independent' => (string) $profile->display_name_independent,
            'accepts_requests' => (bool) $profile->accepts_requests,
            'meeting_provider' => $profile->meeting_provider,
            'buffer_minutes' => (int) $profile->buffer_minutes,
            'languages' => $languages,
            'specializations' => $specializations,
            'custom_specializations' => $custom,
            'custom_specialization_max' => self::CUSTOM_MAX,
            // The lists, plus anything the counselor already has that is not on them (so saving never silently drops it).
            'language_options' => $this->options(config('counselors.profile.languages'), $languages),
            'specialization_options' => $this->options(config('counselors.profile.specializations'), $specializations),
            'buffer_options' => config('counselors.profile.buffer_minutes'),
            'providers' => CounselorSessions::PROVIDERS,
            // What was sent and checked when they applied (only for someone who applied on the web).
            'lkm' => $profile->isIndependent() ? $this->lkmResult($profile) : null,
            'documents' => $this->documents($profile),
        ];
    }

    /**
     * @param  array{display_name_org?: ?string, display_name_independent?: ?string, languages?: list<string>, specializations?: list<string>, accepts_requests?: bool, meeting_provider?: ?string, buffer_minutes?: int}  $input
     * @return list<string> the names of the fields that changed
     */
    public function update(User $counselor, array $input): array
    {
        return DB::transaction(function () use ($counselor, $input) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->firstOrFail();
            $changed = [];

            $columns = [
                'display_name_org' => $this->name($input['display_name_org'] ?? null),
                'display_name_independent' => $this->name($input['display_name_independent'] ?? null),
                'accepts_requests' => (bool) ($input['accepts_requests'] ?? false),
                'meeting_provider' => ($input['meeting_provider'] ?? null) ?: null,
                'buffer_minutes' => (int) ($input['buffer_minutes'] ?? 0),
            ];

            foreach ($columns as $column => $value) {
                if ($profile->{$column} !== $value) {
                    $changed[] = $column;
                    $profile->{$column} = $value;
                }
            }

            $profile->save();

            if ($this->sync($profile->languages(), 'language', $input['languages'] ?? [])) {
                $changed[] = 'languages';
            }

            // The listed ones and the ones the counselor wrote themselves are kept apart, so saving one never removes the other.
            $listedChanged = $this->sync($profile->specializations()->where('is_custom', false), 'specialization', $input['specializations'] ?? [], ['is_custom' => false]);
            $customChanged = $this->sync($profile->specializations()->where('is_custom', true), 'specialization', $input['custom_specializations'] ?? [], ['is_custom' => true]);

            if ($listedChanged || $customChanged) {
                $changed[] = 'specializations';
            }

            if ($changed !== []) {
                Audit::log('counselor.profile_updated', $profile, ['counselor_id' => $profile->getKey(), 'fields' => $changed], $counselor);
            }

            return $changed;
        });
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * How the application compares with the LKM directory record MyKaunsel holds (a copy, not a live connection), in plain words.
     * Only the result: the record's own details stay with the Platform Admin review.
     *
     * @return array{tone: string, text: string}
     */
    private function lkmResult(CounselorProfile $profile): array
    {
        return match (app(LkmVerificationService::class)->compare($profile)->outcome) {
            LkmComparison::MATCH => ['tone' => 'ok', 'text' => 'Your details match the LKM directory record we hold.'],
            LkmComparison::MISMATCH => ['tone' => 'warn', 'text' => 'Some of your details differ from the LKM directory record we hold. Our team will check.'],
            LkmComparison::INACTIVE => ['tone' => 'bad', 'text' => 'The LKM directory record we hold is not active.'],
            LkmComparison::EXPIRED => ['tone' => 'bad', 'text' => 'The LKM directory record we hold shows an expired practising certificate.'],
            default => ['tone' => 'bad', 'text' => 'We could not find your KB number in the LKM directory record we hold.'],
        };
    }

    /**
     * The documents of an application and what our team made of each (the note is the one written for the applicant). Never the file itself.
     *
     * @return list<array{type: string, label: string, status: string, status_label: string, note: ?string, file_name: ?string, uploaded_on: ?string, needs_replacement: bool}>
     */
    public function documents(CounselorProfile $profile): array
    {
        $documents = $profile->documents()->get()->keyBy('type');
        $labels = [
            CounselorDocument::STATUS_UNREVIEWED => 'Received, not checked yet',
            CounselorDocument::STATUS_OK => 'Checked and fine',
            CounselorDocument::STATUS_NEEDS_REPLACEMENT => 'Needs replacement',
        ];

        return collect(CounselorDocument::TYPES)->map(function (string $label, string $type) use ($documents, $labels) {
            /** @var CounselorDocument|null $document */
            $document = $documents->get($type);
            $has = $document !== null && $document->hasFile();

            return [
                'type' => $type,
                'label' => $label,
                'status' => $has ? $document->status : 'missing',
                'status_label' => $has ? ($labels[$document->status] ?? 'Received') : 'Not uploaded yet',
                'note' => $has && $document->needsReplacement() ? $document->note : null,
                'file_name' => $has ? $document->original_name : null,
                'uploaded_on' => $has ? $document->uploaded_at?->format('j M Y') : null,
                'needs_replacement' => $has && $document->needsReplacement(),
            ];
        })->values()->all();
    }

    private function profile(User $counselor): CounselorProfile
    {
        return CounselorProfile::query()->where('user_id', $counselor->getKey())->firstOrFail();
    }

    private function name(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    /**
     * Makes the rows equal the given values (each value once). True when something changed.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany  $relation
     * @param  list<string>  $values
     */
    private function sync($relation, string $column, array $values, array $extra = []): bool
    {
        $wanted = collect($values)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        $current = $relation->pluck($column);

        if ($wanted->sort()->values()->all() === $current->sort()->values()->all()) {
            return false;
        }

        $relation->whereNotIn($column, $wanted->all())->delete();

        foreach ($wanted->diff($current) as $value) {
            $relation->create([$column => $value] + $extra);
        }

        return true;
    }

    /**
     * @param  list<string>  $listed
     * @param  list<string>  $current
     * @return list<string>
     */
    private function options(array $listed, array $current): array
    {
        return collect($listed)->merge($current)->unique()->values()->all();
    }
}
