<?php

namespace App\Support\Inbox;

use App\Enums\ComplaintStatus;
use App\Enums\OrgStatus;
use App\Enums\VerificationStatus;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\LkmReviewRequest;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The notification types of the Platform Admin area. Each one says what it is about and which page it opens; the page is worked out from
 * the type and the target id every time (no URL is ever stored). A type marked "hook" has no code that produces it yet: its definition and
 * its tests exist so the producer only has to call NotificationCenter::publish() when the source is built.
 */
final class CoreNotificationTypes
{
    public static function register(NotificationRegistry $registry): void
    {
        // ---- Applications ------------------------------------------------------------------------------------------------------
        $registry->register(new NotificationType(
            'organization.application_submitted', 'applications', 'New organization application',
            'organization', fn (?int $id) => $id ? route('platform.organizations.show', $id) : null,
            self::stateOf(Organization::class, 'subscription_status', fn ($status) => $status === OrgStatus::Pending),
        ));

        // hook: nothing lets a rejected organization apply again yet
        $registry->register(new NotificationType(
            'organization.application_resubmitted', 'applications', 'Organization application resubmitted',
            'organization', fn (?int $id) => $id ? route('platform.organizations.show', $id) : null,
            self::stateOf(Organization::class, 'subscription_status', fn ($status) => $status === OrgStatus::Pending),
        ));

        $registry->register(new NotificationType(
            'counselor.application_submitted', 'applications', 'New counselor application',
            'counselor_profile', fn (?int $id) => $id ? route('platform.counselors.show', $id) : null,
            self::stateOf(CounselorProfile::class, 'verification_status', fn ($status) => $status === VerificationStatus::Pending),
        ));

        $registry->register(new NotificationType(
            'counselor.application_resubmitted', 'applications', 'Counselor application resubmitted',
            'counselor_profile', fn (?int $id) => $id ? route('platform.counselors.show', $id) : null,
            self::stateOf(CounselorProfile::class, 'verification_status', fn ($status) => $status === VerificationStatus::Pending),
        ));

        // Repeated attempts to register one KB number become ONE notification with a count ("3 attempts"). It is only a heads-up: no waiting state.
        $registry->register(new NotificationType(
            'counselor.kb_duplicate_flagged', 'applications', 'Duplicate KB registration attempt',
            'counselor_profile', fn (?int $id) => $id ? route('platform.counselors.show', $id) : null,
            self::existsOnly(CounselorProfile::class), 'attempt',
        ));

        // ---- LKM ---------------------------------------------------------------------------------------------------------------
        $registry->register(new NotificationType(
            'lkm.review_requested', 'lkm', 'LKM re-check requested',
            'lkm_review_request', fn (?int $id) => route('platform.lkm.reviews'),
            self::stateOf(LkmReviewRequest::class, 'status', fn ($status) => $status === LkmReviewRequest::PENDING),
        ));

        // ---- Complaints (hooks: no code creates or escalates a complaint yet) --------------------------------------------------
        $openComplaint = self::stateOf(Complaint::class, 'status', fn ($status) => in_array($status, [ComplaintStatus::Open, ComplaintStatus::UnderReview], true));

        $registry->register(new NotificationType(
            'complaint.received', 'complaints', 'New complaint',
            'complaint', fn (?int $id) => $id ? route('platform.complaints.show', $id) : null, $openComplaint,
        ));

        $registry->register(new NotificationType(
            'complaint.escalated', 'complaints', 'Complaint escalated',
            'complaint', fn (?int $id) => $id ? route('platform.complaints.show', $id) : null, $openComplaint,
        ));

        // ---- Admins ------------------------------------------------------------------------------------------------------------
        $registry->register(new NotificationType(
            'admin.invitation_accepted', 'admins', 'New Platform Admin joined',
            'user', fn (?int $id) => route('platform.admins'), self::existsOnly(User::class),
        ));

        // ---- System ------------------------------------------------------------------------------------------------------------
        // Waiting means "still suspended": once someone reactivates the counselor it is shown as already reviewed.
        $registry->register(new NotificationType(
            'counselor.pa_expired_suspended', 'system', 'Counselor suspended: PA expired',
            'counselor_profile', fn (?int $id) => $id ? route('platform.counselors.show', $id) : null,
            self::stateOf(CounselorProfile::class, 'verification_status', fn ($status) => $status === VerificationStatus::Suspended),
        ));

        $registry->register(new NotificationType(
            'system.maintenance_auto_reopened', 'system', 'Maintenance mode switched off by itself',
            null, fn (?int $id) => route('platform.registration'),
        ));
    }

    /**
     * "Waiting" is decided by $waiting (given the target's status value); every other status counts as handled; a deleted target is gone.
     * One query for all the ids; only the id and the status column are read.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(mixed): bool  $waiting
     */
    private static function stateOf(string $model, string $column, Closure $waiting): Closure
    {
        return function (array $ids) use ($model, $column, $waiting): array {
            return $model::query()->whereKey($ids)->get(['id', $column])
                ->mapWithKeys(fn (Model $row) => [(int) $row->getKey() => $waiting($row->{$column}) ? NotificationType::OPEN : NotificationType::HANDLED])
                ->all();
        };
    }

    /**
     * For a heads-up that is never "handled": open while the target exists, gone once it is deleted.
     *
     * @param  class-string<Model>  $model
     */
    private static function existsOnly(string $model): Closure
    {
        return fn (array $ids): array => $model::query()->whereKey($ids)->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => NotificationType::OPEN])->all();
    }
}
