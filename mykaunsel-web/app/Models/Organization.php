<?php

namespace App\Models;

use App\Enums\AccessModel;
use App\Enums\JoinMethod;
use App\Enums\MembershipRole;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\InvalidOrgTransition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

    /** The reviewers' reference checklist: key => [label, hint]. Never blocks a decision. */
    public const REVIEW_CHECKLIST = [
        'name_matches' => ['Name matches', 'The organization name matches its registration or website.'],
        'domain_valid' => ['Domain valid', 'The claimed domain belongs to this organization.'],
        'ssm_valid' => ['SSM valid', 'The SSM number checks out on the SSM website.'],
        'admin_authorised' => ['Admin authorised', 'The registering admin can act for this organization.'],
    ];

    protected $fillable = [
        'name',
        'org_type',
        'ssm_number',
        'estimated_size',
        'access_model',
        'subscription_status',
        'rejection_reason',
        'submission_count',
        'submitted_at',
        'authority_confirmed_at',
        'reviewed_by',
        'reviewed_at',
        'suspension_reason',
        'suspended_at',
        'review_checklist',
        'subscription_ends_at',
        'current_assignment_id',
        'allow_counselor_freelance',
        'join_method',
        'cancellation_deadline_hours',
    ];

    protected function casts(): array
    {
        return [
            'org_type' => OrgType::class,
            'access_model' => AccessModel::class,
            'subscription_status' => OrgStatus::class,
            'subscription_ends_at' => 'datetime',
            'submission_count' => 'integer',
            'submitted_at' => 'datetime',
            'authority_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'review_checklist' => 'array',
            'allow_counselor_freelance' => 'boolean',
            'join_method' => JoinMethod::class,
            'cancellation_deadline_hours' => 'integer',
        ];
    }

    /** The plan this organization is on now (the newest assignment). Older ones stay in subscription_assignments as history. */
    public function currentAssignment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAssignment::class, 'current_assignment_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(OrgReviewNote::class, 'org_id');
    }

    /**
     * Move to a new status, enforcing the allowed transitions.
     *
     * The row is re-read under a lock first, so two admins deciding at the same moment cannot both win:
     * pass $expectedFrom (the status the admin was looking at) to reject a decision made on stale data.
     *
     * @param  array<string, mixed>  $attributes  extra columns saved together with the status (reviewer, reason…)
     *
     * @throws InvalidOrgTransition
     */
    public function transitionTo(OrgStatus $to, array $attributes = [], ?OrgStatus $expectedFrom = null): void
    {
        DB::transaction(function () use ($to, $attributes, $expectedFrom) {
            $current = static::query()->lockForUpdate()->findOrFail($this->getKey())->subscription_status;

            if ($expectedFrom !== null && $current !== $expectedFrom) {
                throw InvalidOrgTransition::alreadyChanged($expectedFrom, $current);
            }

            if (! $current->canTransitionTo($to)) {
                throw InvalidOrgTransition::between($current, $to);
            }

            $this->forceFill($attributes + ['subscription_status' => $to])->save();
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'org_id');
    }

    public function orgDomains(): HasMany
    {
        return $this->hasMany(OrgDomain::class, 'org_id');
    }

    public function orgUnits(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'org_id');
    }

    public function orgInviteCodes(): HasMany
    {
        return $this->hasMany(OrgInviteCode::class, 'org_id');
    }

    public function orgAllowedMembers(): HasMany
    {
        return $this->hasMany(OrgAllowedMember::class, 'org_id');
    }

    /** The main practice location given at signup (clinics must have one; others may). */
    public function primaryLocation(): HasOne
    {
        return $this->hasOne(PracticeLocation::class, 'owner_id')
            ->where('owner_type', 'organization')
            ->where('is_primary', true);
    }

    /** True when the SSM number is exactly 12 digits. */
    public function hasValidSsmFormat(): bool
    {
        return preg_match('/^\d{12}$/', (string) $this->ssm_number) === 1;
    }

    /** The admin who registered the organization (its first org_admin). */
    public function registrant(): HasOne
    {
        return $this->hasOne(Membership::class, 'org_id')
            ->where('role', MembershipRole::OrgAdmin->value)
            ->oldestOfMany();
    }

    /** Every real customer organization (the Platform itself is never reviewed). */
    public function scopeApplicants(Builder $query): Builder
    {
        return $query->where('org_type', '!=', OrgType::Platform->value);
    }

    /** Pending, and every domain (if any) is verified — a normal review. */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('subscription_status', OrgStatus::Pending->value)
            ->whereDoesntHave('orgDomains', fn ($q) => $q->where('dns_verified', false));
    }

    /** Pending, but at least one domain is not DNS-verified — needs a manual check. */
    public function scopeSkippedVerification(Builder $query): Builder
    {
        return $query->where('subscription_status', OrgStatus::Pending->value)
            ->whereHas('orgDomains', fn ($q) => $q->where('dns_verified', false));
    }

    /** Whole days since the application was submitted. */
    public function waitingDays(): int
    {
        return (int) ($this->submitted_at ?? $this->created_at)->diffInDays(now());
    }

    /** 'ok' → under 5 days, 'amber' → 5+ days, 'red' → 8+ days. */
    public function waitingTone(): string
    {
        return \App\Support\WaitingTime::tone($this->waitingDays());
    }

    /** 'not_applicable' (clinic / no domain), 'verified' (all domains), or 'skipped' (some not verified). Needs orgDomains. */
    public function domainCheckState(): string
    {
        if ($this->org_type === OrgType::Clinic || $this->orgDomains->isEmpty()) {
            return 'not_applicable';
        }

        return $this->orgDomains->every(fn ($d) => $d->dns_verified) ? 'verified' : 'skipped';
    }

    /** SSM is required for Corporate and Clinic, optional for University. */
    public function requiresSsm(): bool
    {
        return $this->org_type !== OrgType::University;
    }

    public function isOpen(): bool
    {
        return $this->access_model === AccessModel::Open;
    }

    public function isClosed(): bool
    {
        return $this->access_model === AccessModel::Closed;
    }

    public function isPlatform(): bool
    {
        return $this->org_type === OrgType::Platform;
    }
}
