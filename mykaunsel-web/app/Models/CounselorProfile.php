<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Exceptions\InvalidCounselorTransition;
use App\Models\Concerns\HasPaStatusScope;
use App\Support\PaValidity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorProfile extends Model
{
    use HasFactory;
    use HasPaStatusScope;

    /** The reviewers' reference checklist: key => [label, hint]. Never blocks a decision. */
    public const REVIEW_CHECKLIST = [
        'name_matches_lkm' => ['Name matches LKM', 'The name on the application matches the LKM record.'],
        'documents_legible' => ['Documents legible', 'The KB certificate, PA certificate and ID can be read clearly.'],
        'no_duplicate_kb' => ['No duplicate KB', 'No other account uses this KB number.'],
        'pa_not_expired' => ['PA not expired', 'The practising certificate is still valid.'],
    ];

    /** "PA expiring" badge / filter window. */
    public const PA_EXPIRING_DAYS = 30;

    protected $fillable = [
        'user_id',
        'kb_number',
        'pa_number',
        'pa_valid_until',
        'pa_reminder_for',
        'pa_reminder_days',
        'verification_type',
        'verification_status',
        'submitted_at',
        'submission_count',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'suspension_reason',
        'suspended_at',
        'suspended_by',
        'suspension_source',
        'review_checklist',
        'meeting_provider',
        'display_name_org',
        'display_name_independent',
        'accepts_requests',
        'buffer_minutes',
    ];

    protected static function booted(): void
    {
        // Every profile gets its public id the moment it is created (see the add_public_id migration).
        static::creating(function (self $profile) {
            $profile->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());
        });
    }

    protected function casts(): array
    {
        return [
            'pa_valid_until' => 'date',
            'pa_reminder_for' => 'date',
            'pa_reminder_days' => 'integer',
            'verification_type' => VerificationType::class,
            'verification_status' => VerificationStatus::class,
            'submitted_at' => 'datetime',
            'submission_count' => 'integer',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'review_checklist' => 'array',
            'accepts_requests' => 'boolean',
            'buffer_minutes' => 'integer',
        ];
    }

    // ---- relations -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(CounselorLanguage::class);
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(CounselorSpecialization::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(CounselorWorkingHour::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CounselorDocument::class);
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(CounselorReviewNote::class);
    }

    /** Requests to re-check this counselor's LKM record, newest first. */
    public function lkmReviewRequests(): HasMany
    {
        return $this->hasMany(LkmReviewRequest::class)->latest('id');
    }

    /** Blocked attempts to register this account's KB number again. */
    public function kbAttempts(): HasMany
    {
        return $this->hasMany(CounselorKbAttempt::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /** The counselor's memberships: one per organization they work in (independents belong to the Platform organization). */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id', 'user_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'counselor_user_id', 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'counselor_user_id', 'user_id');
    }

    /**
     * Move to a new status, enforcing the allowed moves. The row is re-read under a lock first, so two admins deciding
     * at the same moment cannot both win: pass $expectedFrom (the status the admin was looking at) to refuse a decision
     * made on stale data.
     *
     * @param  array<string, mixed>  $attributes  extra columns saved together with the status
     *
     * @throws InvalidCounselorTransition
     */
    public function transitionTo(VerificationStatus $to, array $attributes = [], ?VerificationStatus $expectedFrom = null): void
    {
        DB::transaction(function () use ($to, $attributes, $expectedFrom) {
            $current = static::query()->lockForUpdate()->findOrFail($this->getKey())->verification_status;

            if ($expectedFrom !== null && $current !== $expectedFrom) {
                throw InvalidCounselorTransition::alreadyChanged($expectedFrom, $current);
            }

            if (! $current->canTransitionTo($to)) {
                throw InvalidCounselorTransition::between($current, $to);
            }

            $this->forceFill($attributes + ['verification_status' => $to])->save();
        });
    }

    // ---- plain checks ----------------------------------------------------------------------

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    /** Valid for the whole of the "valid until" day (see PaValidity). */
    public function isPaValid(): bool
    {
        return PaValidity::isValid($this->pa_valid_until);
    }

    public function isExpired(): bool
    {
        return ! $this->isPaValid();
    }

    public function isIndependent(): bool
    {
        return $this->verification_type === VerificationType::PlatformVerified;
    }

    /** Shown in the audit log as the target's name. */
    public function auditName(): ?string
    {
        return $this->user?->name;
    }

    /** The single rule for "can clients find and book this counselor?" — same as scopeListable(). */
    public function isListable(): bool
    {
        return static::query()->listable()->whereKey($this->getKey())->exists();
    }

    /** Confirmed sessions from today on. A count only. */
    public function upcomingSessionsCount(): int
    {
        return $this->bookings()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereHas('calendarEntry', fn ($q) => $q->whereDate('entry_date', '>=', self::today()))
            ->count();
    }

    /** Session requests nobody has answered yet. A count only. */
    public function pendingRequestsCount(): int
    {
        return BookingRequest::where('counselor_user_id', $this->user_id)->where('status', BookingRequestStatus::Pending->value)->count();
    }

    /** When the application was (last) submitted; before the documents are in, the account creation time. */
    public function submittedAtOrCreated(): Carbon
    {
        return $this->submitted_at ?? $this->created_at;
    }

    public function waitingDays(): int
    {
        return (int) $this->submittedAtOrCreated()->diffInDays(now());
    }

    /** 'ok' → under 5 days, 'amber' → 5+ days, 'red' → 8+ days (same thresholds as organizations). */
    public function waitingTone(): string
    {
        $days = $this->waitingDays();

        return $days >= 8 ? 'red' : ($days >= 5 ? 'amber' : 'ok');
    }

    // ---- scopes ----------------------------------------------------------------------------

    public function scopeIndependent(Builder $query): Builder
    {
        return $query->where('verification_type', VerificationType::PlatformVerified->value);
    }

    public function scopeWithStatus(Builder $query, VerificationStatus $status): Builder
    {
        return $query->where('verification_status', $status->value);
    }

    /**
     * Counselors clients may find and book. THE one rule (the mobile search must use this scope too):
     *   approved (not pending / rejected / suspended)
     *   AND the PA is still valid (through the whole last day)
     *   AND at least one active counselor membership in an ACTIVE organization.
     * Independents work through the Platform organization, which is always active, so the same rule covers them.
     * A suspended profile or an expired PA blocks every context at once.
     */
    public function scopeListable(Builder $query): Builder
    {
        return $query
            ->where('verification_status', VerificationStatus::Approved->value)
            ->whereDate('pa_valid_until', '>=', self::today())
            ->whereHas('memberships', fn (Builder $m) => $m
                ->where('role', MembershipRole::Counselor->value)
                ->where('status', MembershipStatus::Active->value)
                ->whereHas('organization', fn (Builder $o) => $o->where('subscription_status', OrgStatus::Active->value)));
    }

    /** Has at least one blocked attempt to register this KB number again. */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereHas('kbAttempts');
    }

    /** Name, KB number or email. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // "!" is the LIKE escape character (a backslash means different things to SQLite and MySQL).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw("kb_number like ? escape '!'", [$like])
                ->orWhereHas('user', fn (Builder $u) => $u
                    ->whereRaw("name like ? escape '!'", [$like])
                    ->orWhereRaw("email like ? escape '!'", [$like]));
        });
    }

    /**
     * The LKM record does NOT back this profile up right now: missing, not active, PA expired, or the name / PA number
     * differ. Live check against the snapshot (feeds the Dashboard's "LKM number could not be verified").
     */
    public function scopeLkmUnverified(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('lkm_directory_snapshot as lkm')
                ->join('users as lkm_user', 'lkm_user.id', '=', 'counselor_profiles.user_id')
                ->whereColumn('lkm.kb_number', 'counselor_profiles.kb_number')
                ->whereColumn('lkm.pa_number', 'counselor_profiles.pa_number')
                ->where('lkm.status', 'Aktif')
                ->whereRaw('lower(trim(lkm.full_name)) = lower(trim(lkm_user.name))')
                ->whereDate('lkm.pa_valid_until', '>=', self::today());
        });
    }

    /** Today's date in the application timezone, as Y-m-d (what every PA comparison uses). */
    private static function today(): string
    {
        return Carbon::now(config('app.timezone'))->toDateString();
    }
}
