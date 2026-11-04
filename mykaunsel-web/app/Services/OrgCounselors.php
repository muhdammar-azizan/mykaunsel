<?php

namespace App\Services;

use App\Enums\AccessModel;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Membership;
use App\Models\OrgCounselorInvitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorInvitation;
use App\Notifications\CounselorRemovedFromOrganization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * The counselors of an organization (docs/COUNSELORS.md, "Counselors of an organization").
 *
 * - The organization ASSIGNS a counselor by inviting an e-mail address; the counselor accepts on the portal. The counselor must already be a
 *   counselor that MyKaunsel approved (verification is always done by MyKaunsel, never by an organization). One counselor may work for several organizations.
 * - The organization's setting "may our counselors also take independent clients": with "no", a counselor who practises independently can not accept
 *   until that ends (ending an independent practice is not built yet, so they are told to contact MyKaunsel).
 * - Removing a counselor ends the assignment: the later sessions with that organization are cancelled and the time given back, the clients are told
 *   (e-mail and in the app), requests waiting for an answer are closed, times opened for that organization are removed, and the counselor is told. There is no
 *   grace period (the counselor is no longer there). Sessions the counselor has in other organizations or independently are untouched.
 * - Audit: ids and counts only, never an address.
 */
class OrgCounselors
{
    public const MAX_OPEN_INVITATIONS = 50;

    public function __construct(private readonly AvailabilityService $availability) {}

    // =============================================================================================================
    //  The organization
    // =============================================================================================================

    /** @return Collection<int, Membership> */
    public function counselors(Organization $org): Collection
    {
        return Membership::query()->where('org_id', $org->getKey())->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)
            ->with(['user:id,name,email', 'user.counselorProfile'])->orderBy('joined_at')->get();
    }

    /** @return Collection<int, OrgCounselorInvitation> */
    public function invitations(Organization $org): Collection
    {
        return OrgCounselorInvitation::query()->where('org_id', $org->getKey())->orderByDesc('id')->limit(50)->get();
    }

    /** @throws RuntimeException in plain words */
    public function invite(Organization $org, User $admin, string $email): OrgCounselorInvitation
    {
        $email = mb_strtolower(trim($email));

        $invitation = DB::transaction(function () use ($org, $admin, $email) {
            Organization::query()->whereKey($org->getKey())->lockForUpdate()->first();

            $already = Membership::query()->where('org_id', $org->getKey())->where('role', MembershipRole::Counselor->value)
                ->where('status', MembershipStatus::Active->value)->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [$email]))->exists();

            if ($already) {
                throw new RuntimeException('This counselor already works for your organization.');
            }

            $open = OrgCounselorInvitation::query()->where('org_id', $org->getKey())->get()->filter->isOpen();

            if ($open->contains(fn (OrgCounselorInvitation $i) => $i->email === $email)) {
                throw new RuntimeException('This address has already been invited. Cancel that invitation first if you want to send a new one.');
            }

            if ($open->count() >= self::MAX_OPEN_INVITATIONS) {
                throw new RuntimeException('You have '.self::MAX_OPEN_INVITATIONS.' invitations waiting. Cancel some first.');
            }

            $invitation = OrgCounselorInvitation::create([
                'org_id' => $org->getKey(), 'email' => $email, 'invited_by_user_id' => $admin->getKey(), 'expires_at' => now()->addDays(OrgCounselorInvitation::VALID_DAYS),
            ]);

            Audit::log('counselor.invited', $org, ['invitation_id' => $invitation->getKey()], $admin);

            return $invitation;
        });

        try {
            Notification::route('mail', $email)->notify(new CounselorInvitation($org->name));
        } catch (Throwable $e) {
            report($e);
        }

        return $invitation;
    }

    /** Cancels an invitation still waiting. Safe to repeat. A wrong organization is a 404. */
    public function revokeInvitation(Organization $org, int $invitationId, User $admin): void
    {
        DB::transaction(function () use ($org, $invitationId, $admin) {
            $invitation = OrgCounselorInvitation::query()->where('org_id', $org->getKey())->lockForUpdate()->find($invitationId) ?? throw new ModelNotFoundException;

            if (! $invitation->isOpen()) {
                return;
            }

            $invitation->update(['revoked_at' => now()]);
            Audit::log('counselor.invitation_revoked', $org, ['invitation_id' => $invitation->getKey()], $admin);
        });
    }

    /**
     * Ends the assignment of one counselor. The organization's written reason is kept on the membership and sent only to that counselor: a client is only told
     * the time was cancelled, and the audit log (ids and numbers only) never holds it.
     *
     * @return array{counselor: string, cancelled: int}
     */
    public function remove(Organization $org, int $membershipId, User $admin, string $reason): array
    {
        $notices = [];
        $cancelledIds = [];
        $closedRequestIds = [];

        $counselor = DB::transaction(function () use ($org, $membershipId, $admin, $reason, &$notices, &$cancelledIds, &$closedRequestIds) {
            $membership = Membership::query()->where('org_id', $org->getKey())->where('role', MembershipRole::Counselor->value)->lockForUpdate()->find($membershipId)
                ?? throw new ModelNotFoundException;

            if ($membership->status !== MembershipStatus::Active) {
                throw new RuntimeException('This counselor no longer works for your organization.');
            }

            $counselor = $membership->user()->firstOrFail();
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $membership->update(['status' => MembershipStatus::Offboarded, 'offboarded_at' => now(), 'offboard_reason' => $reason]);

            // Sessions still ahead with THIS organization are cancelled and the time given back (B9). Others are not touched.
            $today = $this->availability->now()->format('Y-m-d');
            $bookings = Booking::query()->with('calendarEntry')->where('org_id', $org->getKey())->where('counselor_user_id', $counselor->getKey())
                ->where('status', BookingStatus::Confirmed->value)
                ->whereHas('calendarEntry', fn ($q) => $q->whereDate('entry_date', '>=', $today))->lockForUpdate()->get()
                ->filter(fn (Booking $b) => $this->availability->startOf($b->calendarEntry)->gt($this->availability->now()));

            foreach ($bookings as $booking) {
                Booking::query()->whereKey($booking->getKey())->update([
                    'status' => BookingStatus::Cancelled->value, 'cancelled_by' => 'system', 'cancelled_at' => now(),
                    'cancel_reason' => 'The counselor no longer works with this organization.',
                ]);
                CalendarEntry::query()->whereKey($booking->calendar_entry_id)->update(['is_available' => true]);

                $start = $this->availability->startOf($booking->calendarEntry);
                $notices[] = [$booking->user_id, $start->format('j M Y').', '.$start->format('g:i A')];
                $cancelledIds[] = $booking->getKey();
            }

            // Requests nobody answered yet for this organization can not be answered any more.
            // The people who asked are told after the commit (below), like the clients of a cancelled session.
            $closedRequestIds = BookingRequest::query()->where('counselor_user_id', $counselor->getKey())->where('context_org_id', $org->getKey())
                ->where('status', BookingRequestStatus::Pending->value)->lockForUpdate()->pluck('id')->all();

            BookingRequest::query()->whereIn('id', $closedRequestIds)->where('status', BookingRequestStatus::Pending->value)
                ->update(['status' => BookingRequestStatus::Expired, 'responded_at' => now()]);

            // Times opened for this organization and never booked, and the weekly lines that would open more.
            CalendarEntry::query()->where('counselor_user_id', $counselor->getKey())->where('context_org_id', $org->getKey())
                ->where('entry_type', CalendarEntryType::AvailableSlot->value)->whereDate('entry_date', '>=', $today)
                ->whereDoesntHave('bookings')->delete();
            CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->where('context_org_id', $org->getKey())->delete();

            Audit::log('counselor.removed_from_org', $org, ['membership_id' => $membership->getKey(), 'counselor_id' => $profile?->getKey(), 'cancelled_booking_ids' => $cancelledIds, 'closed_request_ids' => $closedRequestIds], $admin);

            return $counselor;
        });

        // Told after the change is saved; a failing e-mail never undoes it.
        (function () use ($org, $counselor, $notices, $cancelledIds, $closedRequestIds, $reason) {
            $center = app(NotificationCenter::class);

            // A request that can no longer be answered is closed as "expired" for the person, the same words as when nobody answered in time.
            $asked = app(BookingRequests::class);

            foreach (BookingRequest::query()->with('user')->whereIn('id', $closedRequestIds)->get() as $request) {
                try {
                    $start = $asked->startOf($request);
                    $center->notify($request->user, 'request.expired', $request, $counselor->name);
                    $this->tell($request->user, new \App\Notifications\TimeRequestAnsweredClient([
                        'outcome' => 'expired',
                        'counselor' => $counselor->name,
                        'date' => $start->format('l, j F Y'),
                        'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
                        'minutes' => (int) $request->duration_minutes,
                        'mode' => ($request->session_mode ?? \App\Enums\SessionMode::Online)->value,
                        'reason' => null,
                    ]));
                } catch (Throwable $e) {
                    report($e);
                }
            }


            foreach ($notices as [$userId, $when]) {
                $this->tell(User::find($userId), new ClientSessionNotice($counselor->name, $when, 'session'));
            }

            foreach (Booking::query()->whereIn('id', $cancelledIds)->get() as $booking) {
                $center->notify($booking->user, 'booking.cancelled', $booking, $counselor->name);
            }

            $this->tell($counselor, new CounselorRemovedFromOrganization($org->name, count($cancelledIds), $reason));
        })();

        return ['counselor' => $counselor->name, 'cancelled' => count($cancelledIds)];
    }

    /**
     * The two settings about who may be booked. `access_model`: only members (closed) or anyone (open). `$mayPractiseIndependently`: whether the
     * organization's counselors may also take independent clients on MyKaunsel.
     */
    public function updateSettings(Organization $org, User $admin, AccessModel $access, bool $mayPractiseIndependently): void
    {
        DB::transaction(function () use ($org, $admin, $access, $mayPractiseIndependently) {
            $locked = Organization::query()->lockForUpdate()->findOrFail($org->getKey());
            $changes = [];

            if ($locked->access_model !== $access) {
                $changes['access_model'] = ['old' => $locked->access_model->value, 'new' => $access->value];
            }

            if ((bool) $locked->allow_counselor_freelance !== $mayPractiseIndependently) {
                $changes['allow_counselor_freelance'] = ['old' => (bool) $locked->allow_counselor_freelance, 'new' => $mayPractiseIndependently];
            }

            if ($changes === []) {
                return;
            }

            $locked->forceFill(['access_model' => $access, 'allow_counselor_freelance' => $mayPractiseIndependently])->save();
            Audit::log('organization.counselor_settings_updated', $locked, ['changes' => $changes], $admin);
        });
    }

    // =============================================================================================================
    //  The counselor
    // =============================================================================================================

    /** The invitations waiting for this counselor: sent to their CONFIRMED address, still open, from an active organization. @return Collection<int, OrgCounselorInvitation> */
    public function invitationsFor(User $counselor): Collection
    {
        if ($counselor->email_verified_at === null) {
            return collect();
        }

        return OrgCounselorInvitation::query()->where('email', mb_strtolower(trim($counselor->email)))
            ->whereNull('accepted_at')->whereNull('declined_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->whereHas('organization', fn ($q) => $q->where('subscription_status', OrgStatus::Active->value))
            ->with('organization:id,name')->orderBy('id')->get();
    }

    /** @throws RuntimeException in plain words */
    public function accept(User $counselor, string $publicId): Membership
    {
        return DB::transaction(function () use ($counselor, $publicId) {
            $invitation = $this->ownInvitation($counselor, $publicId);
            $org = $invitation->organization;
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            if ($profile === null || $profile->verification_status !== VerificationStatus::Approved) {
                throw new RuntimeException('MyKaunsel has to approve your counselor account before you can work for an organization.');
            }

            if (! $org->allow_counselor_freelance && $this->practisesIndependently($counselor)) {
                throw new RuntimeException("{$org->name} does not allow its counselors to take independent clients on MyKaunsel, and you do. Ending an independent practice is not available here yet: please contact MyKaunsel.");
            }

            $existing = Membership::query()->where('user_id', $counselor->getKey())->where('org_id', $org->getKey())->lockForUpdate()->first();

            if ($existing !== null && $existing->role !== MembershipRole::Counselor) {
                throw new RuntimeException('You already have another role in this organization.');
            }

            if ($existing !== null && $existing->status === MembershipStatus::Active) {
                throw new RuntimeException('You already work for this organization.');
            }

            $fields = ['role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'join_source' => JoinSource::Invitation, 'joined_at' => now(), 'offboarded_at' => null, 'offboard_reason' => null];
            $membership = $existing !== null ? tap($existing)->update($fields) : Membership::create(['user_id' => $counselor->getKey(), 'org_id' => $org->getKey()] + $fields);

            $invitation->update(['accepted_at' => now()]);
            Audit::log('counselor.invitation_accepted', $org, ['invitation_id' => $invitation->getKey(), 'membership_id' => $membership->getKey()], $counselor);

            return $membership;
        });
    }

    /** @throws RuntimeException */
    public function decline(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            $invitation = $this->ownInvitation($counselor, $publicId);

            $invitation->update(['declined_at' => now()]);
            Audit::log('counselor.invitation_declined', $invitation->organization, ['invitation_id' => $invitation->getKey()], $counselor);
        });
    }

    // ---------------------------------------------------------------------------------------------------------

    /** Someone else's invitation, a closed one and an unknown one all look the same. */
    private function ownInvitation(User $counselor, string $publicId): OrgCounselorInvitation
    {
        $invitation = OrgCounselorInvitation::query()->where('public_id', strtolower($publicId))->lockForUpdate()->first();

        if (
            $counselor->email_verified_at === null || $invitation === null || ! $invitation->isOpen()
            || $invitation->email !== mb_strtolower(trim($counselor->email))
            || $invitation->organization?->subscription_status !== OrgStatus::Active
        ) {
            throw new ModelNotFoundException;
        }

        return $invitation;
    }

    private function practisesIndependently(User $counselor): bool
    {
        return Membership::query()->where('user_id', $counselor->getKey())->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)
            ->whereHas('organization', fn ($q) => $q->where('org_type', OrgType::Platform->value))->exists();
    }

    private function tell(?User $user, object $notification): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
