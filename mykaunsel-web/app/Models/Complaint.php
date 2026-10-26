<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use App\Exceptions\InvalidComplaintTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A complaint a client made about a counselor. The text (description) is sensitive: only Platform Admins read it,
 * and it is never copied into the audit log (entries hold the complaint id only).
 */
class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_user_id',
        'counselor_user_id',
        'booking_id',
        'description',
        'status',
        'closing_reason',
        'closed_at',
        'text_purged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'closed_at' => 'datetime',
            'text_purged_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_user_id');
    }

    /** The counselor's profile (KB number, status…), found through the counselor's user id. */
    public function counselorProfile(): HasOne
    {
        return $this->hasOne(CounselorProfile::class, 'user_id', 'counselor_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** The warnings sent to the counselor about this complaint (their messages are erased by the retention rule). */
    public function warnings(): HasMany
    {
        return $this->hasMany(ComplaintWarning::class);
    }

    /** The reporter's name, or "Deleted user" if they have deleted their account (the complaint stays). */
    public function reporterName(): string
    {
        return $this->reporter?->name ?? 'Deleted user';
    }

    /** The retention rule has erased the text (what was reported, the closing reason, the warning messages). */
    public function isTextPurged(): bool
    {
        return $this->text_purged_at !== null;
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ComplaintReviewNote::class);
    }

    /** Shown in the audit log as the target's name. Never the complaint text. */
    public function auditName(): string
    {
        return 'Complaint #'.$this->getKey();
    }

    /**
     * Move to a new status, enforcing the allowed moves. The row is re-read under a lock first, so two admins acting at
     * the same moment cannot both win: pass $expectedFrom (the status the admin was looking at) to refuse a change made
     * on a stale page.
     *
     * @throws InvalidComplaintTransition
     */
    public function transitionTo(ComplaintStatus $to, ?ComplaintStatus $expectedFrom = null): void
    {
        DB::transaction(function () use ($to, $expectedFrom) {
            $current = static::query()->lockForUpdate()->findOrFail($this->getKey())->status;

            if ($expectedFrom !== null && $current !== $expectedFrom) {
                throw InvalidComplaintTransition::alreadyChanged($expectedFrom, $current);
            }

            if (! $current->canTransitionTo($to)) {
                throw InvalidComplaintTransition::between($current, $to);
            }

            $this->forceFill(['status' => $to])->save();
        });
    }

    public function isOpenForReview(): bool
    {
        return in_array($this->status, [ComplaintStatus::Open, ComplaintStatus::UnderReview], true);
    }

    public function waitingDays(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }

    /** 'ok' → under 5 days, 'amber' → 5+ days, 'red' → 8+ days (same thresholds as applications). */
    public function waitingTone(): string
    {
        $days = $this->waitingDays();

        return $days >= 8 ? 'red' : ($days >= 5 ? 'amber' : 'ok');
    }

    // ---- scopes ----------------------------------------------------------------------------

    public function scopeWithStatus(Builder $query, ComplaintStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** Complaints about one counselor, given their counselor profile id. */
    public function scopeAboutCounselorProfile(Builder $query, int $profileId): Builder
    {
        return $query->whereIn('counselor_user_id', CounselorProfile::query()->whereKey($profileId)->select('user_id'));
    }

    /** Complaints about a session booked through one organization. */
    public function scopeThroughOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->whereHas('booking', fn (Builder $b) => $b->where('org_id', $organizationId));
    }

    /** Counselor name or KB number, the reporter's name, or the reference ("12", "#12" or "CMP-12"; the last two look at the reference only). The complaint text is deliberately not searched. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // "!" is the LIKE escape character (a backslash means different things to SQLite and MySQL).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        // "#12" and "CMP-12" can only mean the reference.
        if (preg_match('/^\s*(?:#|cmp-?)(\d{1,9})\s*$/i', $term, $m) === 1) {
            return $query->where('complaints.id', (int) $m[1]);
        }

        return $query->where(function (Builder $q) use ($like, $term) {
            $q->whereHas('counselor', fn (Builder $u) => $u->whereRaw("name like ? escape '!'", [$like]))
                ->orWhereHas('counselorProfile', fn (Builder $p) => $p->whereRaw("kb_number like ? escape '!'", [$like]))
                ->orWhereHas('reporter', fn (Builder $u) => $u->whereRaw("name like ? escape '!'", [$like]));

            // A plain "12" may be the reference or part of a KB number or a name, so it looks in all of them.
            if (preg_match('/^\s*(\d{1,9})\s*$/', $term, $m) === 1) {
                $q->orWhere('complaints.id', (int) $m[1]);
            }
        });
    }
}
