<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\LkmReviewAlreadyDecided;
use App\Facades\Audit;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\LkmReviewRequest;
use App\Models\User;
use App\Notifications\LkmReviewApproved;
use App\Notifications\LkmReviewRejected;
use App\Support\PaValidity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * A counselor's request to re-check their LKM record, and the Platform Admin's decision on it.
 *
 *  - request(): for the counselor portal (not built yet; the seeder makes samples). Evidence is always required. A counselor
 *    can have one pending request at a time (also enforced by a unique index).
 *  - approve(): the LKM record gets the values the admin confirms, the counselor's profile is refreshed from it, and the
 *    counselor is emailed. A suspended counselor is NOT reactivated: that stays a separate decision on the Counselors page.
 *  - reject(): a written reason is required and emailed.
 *
 * Same rules as the other review actions: the rows are locked and re-checked, the change and its audit entries share one
 * transaction (all or nothing), and emails go out only after the commit. Audit entries hold ids, dates and counts, never
 * the evidence.
 */
class LkmReviewService
{
    public function __construct(private LkmVerificationService $lkm) {}

    /**
     * @param  array{pa_valid_until: string, pa_number?: ?string, full_name?: ?string}  $claimed
     *
     * @throws CounselorDecisionBlocked
     * @throws \Illuminate\Validation\ValidationException
     */
    public function request(CounselorProfile $profile, array $claimed, UploadedFile $evidence): LkmReviewRequest
    {
        if (! in_array($profile->verification_status, [VerificationStatus::Approved, VerificationStatus::Suspended], true)) {
            throw new CounselorDecisionBlocked(['Only approved or suspended counselors can ask for their LKM record to be checked again.']);
        }

        $record = LkmDirectorySnapshot::where('kb_number', $profile->kb_number)->first();

        if ($record === null) {
            throw new CounselorDecisionBlocked(['There is no LKM record for your KB number, so there is nothing to check again. Please contact us.']);
        }

        $claimed = $this->cleanClaim($claimed);

        Validator::make(['evidence' => $evidence] + $claimed, [
            'pa_valid_until' => ['required', 'date_format:Y-m-d'],
            'pa_number' => ['sometimes', 'regex:/^PA\d{5}$/'],
            'full_name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'evidence' => ['required', 'file', 'extensions:'.implode(',', config('lkm.review.extensions')), 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.(int) config('lkm.review.max_kb')],
        ], [
            'pa_valid_until.date_format' => 'Write the date as YYYY-MM-DD.',
            'pa_number.regex' => 'A PA number looks like PA12345.',
            'evidence.required' => 'Attach a photo or PDF of your renewed certificate.',
            'evidence.extensions' => 'The evidence must be a PDF, JPG or PNG file.',
            'evidence.mimetypes' => 'The evidence must be a PDF, JPG or PNG file.',
            'evidence.max' => 'The evidence is larger than '.round(config('lkm.review.max_kb') / 1024, 1).' MB.',
        ])->after(function ($validator) use ($claimed) {
            if (! $validator->errors()->has('pa_valid_until') && PaValidity::isExpired(Carbon::parse($claimed['pa_valid_until']))) {
                $validator->errors()->add('pa_valid_until', 'That date has already passed. Send the date your renewed certificate runs until.');
            }
        })->validate();

        $current = $this->valuesOf($record);

        if (collect($claimed)->every(fn ($value, $field) => $current[$field] === $value)) {
            throw new CounselorDecisionBlocked(['Our LKM record already shows these details.']);
        }

        $disk = Storage::disk(config('lkm.review.disk'));
        $path = $disk->putFileAs('', $evidence, Str::random(40).'.'.strtolower($evidence->getClientOriginalExtension()));

        try {
            return DB::transaction(function () use ($profile, $current, $claimed, $evidence, $path) {
                $locked = CounselorProfile::query()->lockForUpdate()->findOrFail($profile->id);

                if ($locked->lkmReviewRequests()->pending()->exists()) {
                    throw new CounselorDecisionBlocked(['You already have a request waiting. We will email you when it has been looked at.']);
                }

                $review = LkmReviewRequest::create([
                    'counselor_profile_id' => $locked->id,
                    'status' => LkmReviewRequest::PENDING,
                    'pending_for' => $locked->id,
                    'current' => collect($current)->only(array_keys($claimed))->all(),
                    'claimed' => $claimed,
                    'evidence_path' => $path,
                    'evidence_name' => Str::limit(basename((string) $evidence->getClientOriginalName()), 120, ''),
                    'evidence_size' => $evidence->getSize(),
                ]);

                Audit::log('lkm.review_requested', $locked, [
                    'review_id' => $review->id,
                    'kb_number' => $locked->kb_number,
                    'fields' => array_keys($claimed),
                    'changes' => $this->changes($review->current, $claimed),
                    'summary' => 'Asked for PA valid until to be '.Carbon::parse($claimed['pa_valid_until'])->format('j M Y'),
                ], $locked->user);

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            $disk->delete($path);

            throw new CounselorDecisionBlocked(['You already have a request waiting. We will email you when it has been looked at.']);
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }

    /**
     * Update the LKM record with the confirmed values and refresh the counselor's profile from it. Only the fields the
     * counselor claimed can be set, and the admin may adjust their values.
     *
     * @param  array<string, ?string>  $values  pa_valid_until, and pa_number / full_name when those were claimed
     * @return array{changes: array<string, array{old: ?string, new: ?string}>, profile_changes: array<string, array{old: ?string, new: ?string}>, still_suspended: bool, profile: CounselorProfile, valid_until: string}
     *
     * @throws LkmReviewAlreadyDecided
     * @throws CounselorDecisionBlocked
     * @throws \Illuminate\Validation\ValidationException
     */
    public function approve(LkmReviewRequest $review, User $admin, array $values): array
    {
        $result = null;

        DB::transaction(function () use ($review, $admin, $values, &$result) {
            $locked = LkmReviewRequest::query()->lockForUpdate()->findOrFail($review->id);

            if (! $locked->isPending()) {
                throw new LkmReviewAlreadyDecided($locked);
            }

            $profile = CounselorProfile::query()->lockForUpdate()->findOrFail($locked->counselor_profile_id)->load('user');
            $record = LkmDirectorySnapshot::query()->where('kb_number', $profile->kb_number)->lockForUpdate()->first();

            if ($record === null) {
                throw new CounselorDecisionBlocked(['There is no LKM record for this KB number any more. Reject the request, or add the record first.']);
            }

            $new = $this->confirmedValues($locked, $record, $values);
            $before = $this->valuesOf($record);
            $changes = $this->changes($before, $new);

            if ($changes === []) {
                throw new CounselorDecisionBlocked(['The LKM record already has these values, so there is nothing to update. Reject the request, or change the values.']);
            }

            $record->update($new);

            Audit::log('lkm.record_updated', $record, [
                'kb_number' => $record->kb_number,
                'review_id' => $locked->id,
                'changes' => $changes,
                'summary' => "Updated from review request #{$locked->id}",
            ], $admin);

            // The profile takes the new date from the record (only an active record, and never the PA number or the name).
            $profileChanges = $this->lkm->refreshPaDate($profile);

            $locked->update([
                'status' => LkmReviewRequest::APPROVED,
                'pending_for' => null,
                'before' => collect($before)->only(array_keys($new))->all(),
                'after' => $new,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $stillSuspended = $profile->verification_status === VerificationStatus::Suspended;

            Audit::log('lkm.review_approved', $profile, [
                'review_id' => $locked->id,
                'kb_number' => $profile->kb_number,
                'changes' => $changes,
                'profile_changes' => $profileChanges ?: null,
                'still_suspended' => $stillSuspended,
                'summary' => 'PA valid until '.Carbon::parse($new['pa_valid_until'])->format('j M Y'),
            ], $admin);

            $result = ['changes' => $changes, 'profile_changes' => $profileChanges, 'still_suspended' => $stillSuspended, 'profile' => $profile, 'valid_until' => $new['pa_valid_until']];
        });

        DB::afterCommit(fn () => $this->tell($result['profile']->user, new LkmReviewApproved(Carbon::parse($result['valid_until']), $result['still_suspended'])));

        return $result;
    }

    /**
     * @throws LkmReviewAlreadyDecided
     */
    public function reject(LkmReviewRequest $review, User $admin, string $reason): void
    {
        $reason = trim($reason);
        $profile = null;

        DB::transaction(function () use ($review, $admin, $reason, &$profile) {
            $locked = LkmReviewRequest::query()->lockForUpdate()->findOrFail($review->id);

            if (! $locked->isPending()) {
                throw new LkmReviewAlreadyDecided($locked);
            }

            $profile = $locked->counselorProfile()->with('user')->firstOrFail();

            $locked->update([
                'status' => LkmReviewRequest::REJECTED,
                'pending_for' => null,
                'decision_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            Audit::log('lkm.review_rejected', $profile, [
                'review_id' => $locked->id,
                'kb_number' => $profile->kb_number,
                'reason' => $reason,
            ], $admin);
        });

        DB::afterCommit(fn () => $this->tell($profile->user, new LkmReviewRejected($reason)));
    }

    /** Written when an admin's browser really receives the evidence file. */
    public function recordEvidenceView(LkmReviewRequest $review, User $admin): void
    {
        DB::transaction(fn () => Audit::log('lkm.evidence_viewed', $review->counselorProfile, [
            'review_id' => $review->id,
            'kb_number' => $review->counselorProfile->kb_number,
        ], $admin));
    }

    // ---- internals -------------------------------------------------------------------------

    /**
     * Trim, PA number upper-case, and leave out optional claims that are empty.
     *
     * @param  array<string, mixed>  $claimed
     * @return array<string, string>
     */
    private function cleanClaim(array $claimed): array
    {
        $out = [];

        foreach (array_keys(LkmReviewRequest::FIELDS) as $field) {
            $value = isset($claimed[$field]) ? trim((string) $claimed[$field]) : '';

            if ($value !== '' || $field === 'pa_valid_until') {
                $out[$field] = $field === 'pa_number' ? strtoupper($value) : $value;
            }
        }

        return $out;
    }

    /** @return array<string, ?string> the fields a request can be about, as they are on the record */
    private function valuesOf(LkmDirectorySnapshot $record): array
    {
        return [
            'pa_valid_until' => $record->pa_valid_until?->format('Y-m-d'),
            'pa_number' => $record->pa_number,
            'full_name' => $record->full_name,
        ];
    }

    /**
     * The values the admin confirms, checked: only claimed fields, the date a real date that has not passed and not
     * before the certificate's own start date.
     *
     * @param  array<string, ?string>  $values
     * @return array<string, string>
     */
    private function confirmedValues(LkmReviewRequest $review, LkmDirectorySnapshot $record, array $values): array
    {
        $fields = $review->claimedFields();
        $input = collect($values)->only($fields)->map(fn ($v) => $v === null ? null : trim((string) $v))->all();

        if (isset($input['pa_number'])) {
            $input['pa_number'] = strtoupper($input['pa_number']);
        }

        $rules = collect([
            'pa_valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$record->pa_valid_from->format('Y-m-d')],
            'pa_number' => ['required', 'regex:/^PA\d{5}$/'],
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
        ])->only($fields)->all();

        Validator::make($input, $rules, [
            'pa_valid_until.after_or_equal' => 'The date cannot be before the certificate starts ('.$record->pa_valid_from->format('j M Y').').',
            'pa_valid_until.date_format' => 'Write the date as YYYY-MM-DD.',
            'pa_number.regex' => 'A PA number looks like PA12345.',
        ])->after(function ($validator) use ($input) {
            if (isset($input['pa_valid_until']) && ! $validator->errors()->has('pa_valid_until') && PaValidity::isExpired(Carbon::parse($input['pa_valid_until']))) {
                $validator->errors()->add('pa_valid_until', 'That date has already passed, so the certificate would still be expired. Reject the request instead.');
            }
        })->validate();

        return $input;
    }

    /**
     * Fields whose value differs, ready for the audit log.
     *
     * @param  array<string, ?string>  $old
     * @param  array<string, ?string>  $new
     * @return array<string, array{old: ?string, new: ?string}>
     */
    private function changes(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $field => $value) {
            if ((string) ($old[$field] ?? '') !== (string) $value) {
                $changes[$field] = ['old' => $old[$field] ?? null, 'new' => $value];
            }
        }

        return $changes;
    }

    private function tell(?User $user, object $notification): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Could not send an LKM review email.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
