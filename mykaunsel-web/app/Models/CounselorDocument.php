<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A counselor's KB certificate, PA certificate or ID/passport. The file is on the private disk (never a public URL);
 * platform admins open it through a short-lived signed route, and each view is audited.
 */
class CounselorDocument extends Model
{
    public const TYPES = [
        'cert' => 'KB certificate',
        'pa' => 'PA certificate',
        'ic' => 'ID / passport',
    ];

    public const STATUS_UNREVIEWED = 'unreviewed';

    public const STATUS_OK = 'ok';

    public const STATUS_NEEDS_REPLACEMENT = 'needs_replacement';

    protected $fillable = [
        'counselor_profile_id', 'type', 'path', 'original_name', 'size_bytes', 'status', 'note', 'uploaded_at', 'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isPurged(): bool
    {
        return $this->purged_at !== null;
    }

    /** There is a file to show. */
    public function hasFile(): bool
    {
        return filled($this->path) && ! $this->isPurged();
    }

    public function needsReplacement(): bool
    {
        return $this->status === self::STATUS_NEEDS_REPLACEMENT;
    }
}
