<?php

namespace App\Models;

use App\Enums\AccessModel;
use App\Enums\JoinMethod;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\InvalidOrgTransition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

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
        'subscription_tier',
        'subscription_ends_at',
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
