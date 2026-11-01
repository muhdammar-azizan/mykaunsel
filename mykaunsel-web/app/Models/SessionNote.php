<?php

namespace App\Models;

use App\Enums\Attendance;
use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A counselor's private note about one session (docs/SESSION_NOTES.md): one per booking. The text is stored ENCRYPTED. Only the counselor who
 * wrote it ever reads it (SessionNotes); nothing else loads `body`: not reports, not the Platform Admin, not the organization, not the client.
 */
class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'counselor_user_id',
        'body',
        'attendance',
        'follow_up_status',
    ];

    /** The text is never written to a log, an audit entry or a JSON answer by accident. */
    protected $hidden = ['body'];

    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'attendance' => Attendance::class,
            'follow_up_status' => FollowUpStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
