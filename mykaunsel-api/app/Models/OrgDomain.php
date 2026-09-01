<?php

namespace App\Models;

use App\Enums\DomainMatchType;
use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_id',
        'domain',
        'default_role',
        'match_type',
        'verification_token',
        'dns_verified',
        'verified_at',
        'last_checked_at',
        'check_attempts',
    ];

    protected function casts(): array
    {
        return [
            'default_role' => MembershipRole::class,
            'match_type' => DomainMatchType::class,
            'dns_verified' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'check_attempts' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
