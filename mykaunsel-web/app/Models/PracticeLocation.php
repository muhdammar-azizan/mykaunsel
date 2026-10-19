<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'address',
        'city',
        'state',
        'postcode',
        'latitude',
        'longitude',
        'description',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_primary' => 'boolean',
        ];
    }

    public function locationPhotos(): HasMany
    {
        return $this->hasMany(LocationPhoto::class);
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class, 'location_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'location_id');
    }
}
