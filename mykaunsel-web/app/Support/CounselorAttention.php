<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\CounselorProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Counts and queries the Platform Admin Dashboard uses for its counselor cards and "Needs your attention" items.
 * The Dashboard is not wired to these yet — this is the one place the numbers come from, so the page and the
 * Dashboard can never disagree.
 */
class CounselorAttention
{
    /** Independent counselors waiting for approval. (Organization counselors never need it.) */
    public static function pending(): Builder
    {
        return CounselorProfile::query()->independent()->withStatus(VerificationStatus::Pending);
    }

    /** Pending applications whose LKM record no longer backs them up (missing, inactive, PA expired, name or PA number differ). */
    public static function lkmNotVerified(): Builder
    {
        return self::pending()->lkmUnverified();
    }

    /** Accounts (any status) that someone tried to register the KB number of again. */
    public static function duplicateKb(): Builder
    {
        return CounselorProfile::query()->flagged();
    }

    /** @return array{pending: int, lkm_not_verified: int, duplicate_kb: int} */
    public static function counts(): array
    {
        return [
            'pending' => self::pending()->count(),
            'lkm_not_verified' => self::lkmNotVerified()->count(),
            'duplicate_kb' => self::duplicateKb()->count(),
        ];
    }
}
