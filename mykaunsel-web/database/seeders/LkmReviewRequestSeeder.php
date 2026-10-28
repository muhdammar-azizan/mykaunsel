<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Facades\Audit;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\LkmReviewRequest;
use App\Models\User;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample "check my LKM record again" requests for Platform Admin → LKM Directory → Review Requests. All made up. The counselor
 * portal that sends these is not built yet, so this is the only way to see them. Run CounselorReviewSeeder first.
 *
 *   pending   an approved counselor whose certificate was renewed (KB11935), and one suspended by the system because the
 *             certificate expired (KB13090): approving that one must NOT lift the suspension
 *   resolved  one rejected (evidence still kept), one approved (evidence still kept), and one approved long ago whose
 *             evidence was already removed under the retention rules (that counselor is suspended for another reason)
 *
 * The evidence is a tiny made-up PDF on the private disk. Safe to run twice.
 */
class LkmReviewRequestSeeder extends Seeder
{
    use RefusesOutsideDevelopment;

    private User $admin;

    public function run(): void
    {
        $this->refuseOutsideDevelopment();

        if (LkmReviewRequest::query()->exists()) {
            return;
        }

        $this->admin = User::whereHas('memberships', fn ($q) => $q->where('role', MembershipRole::PlatformAdmin->value))->first()
            ?? User::factory()->create(['name' => 'Platform Administrator']);

        // Resolved first, so the pending ones are the newest.
        $this->approved('KB12168', requested: now()->subDays(48), decided: now()->subDays(46), purged: true);
        $this->rejected('KB11935', requested: now()->subDays(7), decided: now()->subDays(6),
            reason: 'The photo of the certificate is too dark to read. Please send a clearer photo or the PDF.');
        $this->approved('KB10891', requested: now()->subDays(12), decided: now()->subDays(10));

        $this->pending('KB11935', requested: now()->subDays(1), until: now()->addYears(2));
        $this->pending('KB13090', requested: now()->subDays(4), until: now()->addYear());
    }

    // ---- builders --------------------------------------------------------------------------

    private function pending(string $kb, Carbon $requested, Carbon $until): void
    {
        if ($made = $this->make($kb, $requested, ['pa_valid_until' => $until->toDateString()], LkmReviewRequest::PENDING)) {
            [$review, $profile] = $made;
            $this->requestedEntry($review, $profile, $requested);
        }
    }

    private function approved(string $kb, Carbon $requested, Carbon $decided, bool $purged = false): void
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kb)->first();

        if ($record === null) {
            return;
        }

        $after = $record->pa_valid_until->toDateString();
        $before = $record->pa_valid_until->copy()->subYear()->toDateString();

        if ($made = $this->make($kb, $requested, ['pa_valid_until' => $after], LkmReviewRequest::APPROVED, current: ['pa_valid_until' => $before], decided: $decided, purged: $purged)) {
            [$review, $profile] = $made;
            $review->update(['before' => ['pa_valid_until' => $before], 'after' => ['pa_valid_until' => $after]]);
            $this->requestedEntry($review, $profile, $requested);
            $this->decisionEntry('lkm.review_approved', $review, $profile, $decided, [
                'changes' => ['pa_valid_until' => ['old' => $before, 'new' => $after]],
                'still_suspended' => $profile->verification_status->value === 'suspended',
                'summary' => 'PA valid until '.Carbon::parse($after)->format('j M Y'),
            ]);
        }
    }

    private function rejected(string $kb, Carbon $requested, Carbon $decided, string $reason): void
    {
        $record = LkmDirectorySnapshot::where('kb_number', $kb)->first();

        if ($record === null) {
            return;
        }

        if ($made = $this->make($kb, $requested, ['pa_valid_until' => $record->pa_valid_until->copy()->addYears(2)->toDateString()], LkmReviewRequest::REJECTED, decided: $decided, reason: $reason)) {
            [$review, $profile] = $made;
            $this->requestedEntry($review, $profile, $requested);
            $this->decisionEntry('lkm.review_rejected', $review, $profile, $decided, ['reason' => $reason]);
        }
    }

    /**
     * @param  array<string, string>  $claimed
     * @param  array<string, string>|null  $current  what the record said at the time (default: what it says now)
     * @return array{0: LkmReviewRequest, 1: CounselorProfile}|null
     */
    private function make(string $kb, Carbon $requested, array $claimed, string $status, ?array $current = null, ?Carbon $decided = null, bool $purged = false, ?string $reason = null): ?array
    {
        $profile = CounselorProfile::where('kb_number', $kb)->with('user')->first();
        $record = LkmDirectorySnapshot::where('kb_number', $kb)->first();

        if ($profile === null || $record === null) {
            return null;
        }

        $path = null;
        if (! $purged) {
            $path = Str::random(40).'.pdf';
            Storage::disk(config('lkm.review.disk'))->put($path, CounselorReviewSeeder::pdf('Renewed practising certificate'));
        }

        $review = new LkmReviewRequest([
            'counselor_profile_id' => $profile->id,
            'status' => $status,
            'pending_for' => $status === LkmReviewRequest::PENDING ? $profile->id : null,
            'current' => $current ?? ['pa_valid_until' => $record->pa_valid_until->toDateString()],
            'claimed' => $claimed,
            'evidence_path' => $path,
            'evidence_name' => $purged ? null : 'renewed-practising-certificate.pdf',
            'evidence_size' => $purged ? null : strlen(CounselorReviewSeeder::pdf('Renewed practising certificate')),
            'evidence_purged_at' => $purged ? ($decided ?? $requested)->copy()->addDays((int) config('lkm.review.evidence_retention_days')) : null,
            'decision_reason' => $reason,
            'reviewed_by' => $decided ? $this->admin->id : null,
            'reviewed_at' => $decided,
        ]);
        $review->created_at = $requested;
        $review->updated_at = $decided ?? $requested;
        $review->save();

        return [$review, $profile];
    }

    private function requestedEntry(LkmReviewRequest $review, CounselorProfile $profile, Carbon $at): void
    {
        Audit::log('lkm.review_requested', $profile, [
            'review_id' => $review->id,
            'kb_number' => $profile->kb_number,
            'fields' => array_keys($review->claimed),
            'summary' => 'Asked for PA valid until to be '.Carbon::parse($review->claimed['pa_valid_until'])->format('j M Y'),
            'seeded' => true,
        ], $profile->user, ['at' => $at, 'ip' => '203.0.113.30', 'user_agent' => null, 'request_id' => (string) Str::uuid()]);
    }

    private function decisionEntry(string $action, LkmReviewRequest $review, CounselorProfile $profile, Carbon $at, array $metadata): void
    {
        Audit::log($action, $profile, $metadata + ['review_id' => $review->id, 'kb_number' => $profile->kb_number, 'seeded' => true], $this->admin, [
            'at' => $at, 'ip' => '203.0.113.5', 'user_agent' => null, 'request_id' => (string) Str::uuid(),
        ]);
    }
}
