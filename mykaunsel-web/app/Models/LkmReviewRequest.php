<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A counselor asking Platform Admin to check their LKM record again (for example after renewing the practising certificate),
 * with evidence. Decided by a Platform Admin: the LKM record is updated (approved) or left as it is (rejected).
 *
 * `current` / `claimed` / `before` / `after` hold values by field name: pa_valid_until always, pa_number and full_name only
 * when the counselor claims a change to them. The evidence file is on a private disk and is deleted after the retention period.
 */
class LkmReviewRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** Fields a request can be about, with the words shown for them. */
    public const FIELDS = ['pa_valid_until' => 'PA valid until', 'pa_number' => 'PA number', 'full_name' => 'Name'];

    protected $fillable = [
        'counselor_profile_id', 'status', 'pending_for', 'current', 'claimed',
        'evidence_path', 'evidence_name', 'evidence_size', 'evidence_purged_at',
        'decision_reason', 'before', 'after', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'current' => 'array',
            'claimed' => 'array',
            'before' => 'array',
            'after' => 'array',
            'evidence_size' => 'integer',
            'evidence_purged_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::APPROVED, self::REJECTED]);
    }

    /** For the Dashboard "Needs your attention" list and the tab badge (the Dashboard itself is not connected yet). */
    public static function pendingCount(): int
    {
        return static::query()->pending()->count();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** There is an evidence file to show. */
    public function hasEvidence(): bool
    {
        return filled($this->evidence_path) && $this->evidence_purged_at === null;
    }

    /** The claimed fields, in a fixed order. @return list<string> */
    public function claimedFields(): array
    {
        return array_values(array_filter(array_keys(self::FIELDS), fn (string $field) => array_key_exists($field, $this->claimed ?? [])));
    }
}
