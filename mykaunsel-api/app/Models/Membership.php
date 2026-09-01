<?php

namespace App\Models;

use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'unit_id',
        'role',
        'status',
        'join_source',
        'expected_graduation_date',
        'last_verified_at',
        'notice_started_at',
        'offboarded_at',
        'offboard_reason',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
            'join_source' => JoinSource::class,
            'expected_graduation_date' => 'date',
            'last_verified_at' => 'datetime',
            'notice_started_at' => 'datetime',
            'offboarded_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }
}
