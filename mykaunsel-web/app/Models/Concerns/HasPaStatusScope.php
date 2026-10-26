<?php

namespace App\Models\Concerns;

use App\Models\CounselorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * "PA valid / expiring / expired" filter for any table with a pa_valid_until date column.
 * Whole days, in the application timezone (see App\Support\PaValidity): valid until today still counts as valid.
 */
trait HasPaStatusScope
{
    /** 'valid' (not yet expired), 'expiring' (valid but within 30 days) or 'expired'. */
    public function scopePaStatus(Builder $query, string $status): Builder
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();

        return match ($status) {
            'valid' => $query->whereDate('pa_valid_until', '>=', $today),
            'expiring' => $query->whereDate('pa_valid_until', '>=', $today)
                ->whereDate('pa_valid_until', '<=', Carbon::parse($today, config('app.timezone'))->addDays(CounselorProfile::PA_EXPIRING_DAYS)->toDateString()),
            'expired' => $query->whereDate('pa_valid_until', '<', $today),
            default => $query,
        };
    }
}
