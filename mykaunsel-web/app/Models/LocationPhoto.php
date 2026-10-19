<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'practice_location_id',
        'photo_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function practiceLocation(): BelongsTo
    {
        return $this->belongsTo(PracticeLocation::class);
    }
}
