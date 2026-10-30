<?php

namespace App\Models;

use App\Enums\SessionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a counselor's weekly pattern (see CounselorWeeklyHours). Not bookable by itself. */
class CounselorWeeklyHour extends Model
{
    protected $guarded = ['id', 'public_id'];

    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'slot_minutes' => 'integer',
            'session_mode' => SessionMode::class,
        ];
    }

    public function contextOrg(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'context_org_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(PracticeLocation::class, 'location_id');
    }
}
