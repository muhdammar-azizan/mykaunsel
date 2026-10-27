<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidCounselorTransition;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorPaRenewalReminder;
use App\Notifications\CounselorReactivated;
use App\Notifications\CounselorSuspended;
use App\Notifications\CounselorSuspendedForOrganization;
use App\Support\PaValidity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Platform Admin looking after an APPROVED counselor: suspend, reactivate, remind about the practising certificate.
 *
 * Same rules as the application review: the row is locked and its status re-checked, the change and its audit entry
 * share one transaction (all or nothing), and emails go out only after the commit. Everything about clients here is
 * a count or an id — the audit log never holds a client's name or anything about a session's content.
 */
class CounselorStatusService
{
    public function __construct(private LkmVerificationService $lkm) {}

    /**
     * Suspend an approved counselor. They disappear from search and cannot open new slots (see CounselorProfile::scopeListable).
     *
     * Pending session REQUESTS are always closed (status Expired, clients told). Sessions already booked are either kept
     * (default) or, with $cancelUpcoming, cancelled with the clients told. Calendar slots are never touched.
     * Cancelling is safe to repeat: only still-confirmed upcoming sessions are changed.
     * $source records where the suspension came from: 'platform' (the Counselors page), 'complaint', or 'system'
     * (the daily practising-certificate check, with no admin: $admin is null, the actor is "system" and the audit
     * action is counselor.pa_expired_suspended).
     *
     * @throws InvalidCounselorTransition when they are no longer approved
     */
    public function suspend(CounselorProfile $profile, ?User $admin, string $reason, bool $cancelUpcoming = false, string $source = 'platform', ?int $complaintId = null): void
    {
        $clientNotices = [];
        $cancelledIds = [];
        $expiredIds = [];

        DB::transaction(function () use ($profile, $admin, $reason, $cancelUpcoming, $source, $complaintId, &$clientNotices, &$cancelledIds, &$expiredIds) {
            $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);

            if ($locked->verification_status !== VerificationStatus::Approved) {
                throw InvalidCounselorTransition::alreadyChanged(VerificationStatus::Approved, $locked->verification_status);
            }

            $locked->transitionTo(VerificationStatus::Suspended, [
                'suspension_reason' => $reason,
                'suspended_at' => now(),
                'suspended_by' => $admin?->id,
                'suspension_source' => $source,
                // From a complaint: which one, so the reason can be kept only here and erased with that complaint's text.
                'suspension_complaint_id' => $source === 'complaint' ? $complaintId : null,
            ], VerificationStatus::Approved);

            $name = $locked->user->name;

            // Requests nobody has answered yet can never be answered now.
            $requests = BookingRequest::query()
                ->where('counselor_user_id', $locked->user_id)
                ->where('status', BookingRequestStatus::Pending->value)
                ->get();
            foreach ($requests as $request) {
                $request->update(['status' => BookingRequestStatus::Expired]);
                $expiredIds[] = $request->id;
                $clientNotices[] = [$request->user_id, $name, $request->requested_date->format('j M Y').', '.Carbon::parse($request->requested_time)->format('g:i A'), 'request'];
            }

            if ($cancelUpcoming) {
                $bookings = $locked->bookings()
                    ->where('status', BookingStatus::Confirmed->value)
                    ->whereHas('calendarEntry', fn ($q) => $q->whereDate('entry_date', '>=', Carbon::now(config('app.timezone'))->toDateString()))
                    ->with('calendarEntry')
                    ->get();

                foreach ($bookings as $booking) {
                    $booking->update(['status' => BookingStatus::Cancelled]);
                    $cancelledIds[] = $booking->id;
                    $entry = $booking->calendarEntry;
                    $clientNotices[] = [$booking->user_id, $name, $entry->entry_date->format('j M Y').', '.Carbon::parse($entry->start_time)->format('g:i A'), 'session'];
                }
            }

            $entry = [
                'source' => $source,
                'sessions' => $cancelUpcoming ? 'cancel_upcoming' : 'keep',
                'cancelled_booking_ids' => $cancelledIds,
                'expired_request_ids' => $expiredIds,
                'changes' => ['status' => ['old' => 'approved', 'new' => 'suspended']],
            ];

            // A suspension that comes from a complaint keeps its reason ONLY on the profile (where it goes with the complaint's
            // retention): the audit entry points to the complaint instead of repeating the words. Every other suspension is unchanged.
            $entry = $source === 'complaint' && $complaintId !== null
                ? ['complaint_id' => $complaintId, 'has_reason' => trim($reason) !== '', 'summary' => "Suspended after complaint #{$complaintId} (the reason is kept with the complaint)"] + $entry
                : ['reason' => $reason] + $entry;

            Audit::log($source === 'system' ? 'counselor.pa_expired_suspended' : 'counselor.suspended', $locked, $entry, $admin ?? 'system');
        });

        DB::afterCommit(function () use ($profile, $reason, $cancelUpcoming, $clientNotices) {
            $this->tell($profile->user, new CounselorSuspended($reason, $cancelUpcoming));

            foreach ($this->organizationAdmins($profile) as [$orgAdmin, $organizationName]) {
                $this->tell($orgAdmin, new CounselorSuspendedForOrganization($profile->user->name, $organizationName, $cancelUpcoming));
            }

            foreach ($clientNotices as [$userId, $counselorName, $when, $kind]) {
                $this->tell(User::find($userId), new ClientSessionNotice($counselorName, $when, $kind));
            }
        });
    }

    /**
     * Lift a suspension. Blocked while the PA is expired. The PA date is first refreshed from the LKM record (only if it
     * exists, has this KB number and is Active); if the record is missing, not active or the PA is still expired the
     * counselor stays suspended and the admin is told why — nothing is overwritten.
     *
     * @throws InvalidCounselorTransition when they are no longer suspended
     * @throws CounselorDecisionBlocked
     */
    public function reactivate(CounselorProfile $profile, User $admin): void
    {
        DB::transaction(function () use ($profile, $admin) {
            $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id)->load('user');

            if ($locked->verification_status !== VerificationStatus::Suspended) {
                throw InvalidCounselorTransition::alreadyChanged(VerificationStatus::Suspended, $locked->verification_status);
            }

            $changes = $this->lkm->refreshPaDate($locked);
            $lkm = $this->lkm->compare($locked);
            $blockers = $lkm->blocksApproval() ? $lkm->problems : [];

            if (PaValidity::isExpired($locked->pa_valid_until) && $lkm->outcome !== \App\Support\LkmComparison::EXPIRED) {
                $blockers[] = 'The practising certificate expired on '.$locked->pa_valid_until->format('j M Y').'.';
            }

            if ($blockers !== []) {
                throw new CounselorDecisionBlocked($blockers); // rolls the date refresh back too
            }

            $source = $locked->suspension_source;
            $locked->transitionTo(VerificationStatus::Approved, [
                'suspension_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
                'suspension_source' => null,
                'suspension_complaint_id' => null,
            ], VerificationStatus::Suspended);

            Audit::log('counselor.reactivated', $locked, [
                'previous_suspension_source' => $source,
                'changes' => ['status' => ['old' => 'suspended', 'new' => 'approved']] + $changes,
            ], $admin);
        });

        DB::afterCommit(fn () => $this->tell($profile->user, new CounselorReactivated));
    }

    /**
     * Email the counselor about their practising certificate. Only while it is running out (30 days) or expired, and at
     * most once per cool-down. The email goes first: the audit entry says "sent" only if it really was.
     *
     * @throws CounselorDecisionBlocked
     */
    public function sendRenewalReminder(CounselorProfile $profile, User $admin): void
    {
        if (! in_array($profile->verification_status, [VerificationStatus::Approved, VerificationStatus::Suspended], true)) {
            throw new CounselorDecisionBlocked(['Only approved or suspended counselors can be reminded.']);
        }

        $expired = PaValidity::isExpired($profile->pa_valid_until);

        if (! $expired && ! PaValidity::expiresWithin($profile->pa_valid_until, CounselorProfile::PA_EXPIRING_DAYS)) {
            throw new CounselorDecisionBlocked(['The practising certificate is not close to expiring.']);
        }

        $hours = (int) config('counselors.renewal_reminder_cooldown_hours', 24);
        $last = AuditLog::where('action', 'counselor.renewal_reminder_sent')->where('target_type', 'counselor')->where('target_id', $profile->id)->latest('created_at')->value('created_at');

        if ($last && now()->diffInHours($last, true) < $hours) {
            throw new CounselorDecisionBlocked(['A reminder was already sent recently. Please wait before sending another.']);
        }

        try {
            $profile->user->notify(new CounselorPaRenewalReminder($profile->pa_valid_until->copy(), $expired));
        } catch (Throwable $e) {
            report($e);

            throw new CounselorDecisionBlocked(['The reminder could not be sent. Please try again.']);
        }

        DB::transaction(fn () => Audit::log('counselor.renewal_reminder_sent', $profile, [
            'summary' => ($expired ? 'PA expired on ' : 'PA valid until ').$profile->pa_valid_until->format('j M Y'),
        ], $admin));
    }

    // ---- internals -------------------------------------------------------------------------

    /** @return list<array{0: User, 1: string}> each active org admin with the organization they speak for */
    private function organizationAdmins(CounselorProfile $profile): array
    {
        $orgIds = Membership::query()
            ->where('user_id', $profile->user_id)
            ->where('role', MembershipRole::Counselor->value)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('organization', fn ($q) => $q->where('org_type', '!=', OrgType::Platform->value))
            ->pluck('org_id');

        $out = [];
        foreach (Membership::with(['user', 'organization'])->whereIn('org_id', $orgIds)->where('role', MembershipRole::OrgAdmin->value)->where('status', MembershipStatus::Active->value)->get() as $m) {
            if ($m->user) {
                $out[] = [$m->user, $m->organization->name];
            }
        }

        return $out;
    }

    private function tell(?User $user, object $notification): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Could not send a counselor status email.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
