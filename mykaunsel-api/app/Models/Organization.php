<?php

namespace App\Models;

use App\Enums\AccessModel;
use App\Enums\JoinMethod;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'org_type',
        'access_model',
        'subscription_status',
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
            'allow_counselor_freelance' => 'boolean',
            'join_method' => JoinMethod::class,
            'cancellation_deadline_hours' => 'integer',
        ];
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
