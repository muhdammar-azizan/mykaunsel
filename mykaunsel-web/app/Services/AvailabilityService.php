<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function getAvailableSlots(User $counselor, string $date): Collection
    {
        //
    }

    public function isSlotAvailable(User $counselor, string $date, string $time): bool
    {
        //
    }

    public function blockPersonalTime(User $counselor, string $date, string $startTime, int $durationMinutes): void
    {
        //
    }
}
