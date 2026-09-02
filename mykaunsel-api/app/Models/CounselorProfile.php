<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounselorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kb_number',
        'pa_number',
        'pa_valid_until',
        'verification_type',
        'verification_status',
        'cert_document_path',
        'pa_document_path',
        'ic_document_path',
        'rejection_reason',
        'meeting_provider',
        'display_name_org',
        'display_name_independent',
        'accepts_requests',
        'buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'pa_valid_until' => 'date',
            'verification_type' => VerificationType::class,
            'verification_status' => VerificationStatus::class,
            'accepts_requests' => 'boolean',
            'buffer_minutes' => 'integer',
        ];
    }

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

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    public function isPaValid(): bool
    {
        return $this->pa_valid_until?->isFuture() ?? false;
    }

    public function isExpired(): bool
    {
        return ! $this->isPaValid();
    }
}
