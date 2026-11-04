<?php

namespace App\Services;

use App\Support\Subscription;
use App\Models\Organization;
use App\Enums\BookingMode;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\OrgStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Mode B, the counselor's side: answering the requests made to them (docs/AVAILABILITY.md, "Time requests").
 *
 * - Approving BOOKS the session: a calendar entry of type `requested_session` (never offered to anyone: it is not a slot) held by a confirmed
 *   `request_based` booking, made in ONE transaction with the request's new status. Under the same locks as booking (the client, the counselor's
 *   profile, then the request). It refuses unless the time STILL fits: free on the counselor's calendar, the client has no overlapping session
 *   and is under the upcoming-session cap, the counselor still works in that organization, and it has not started.
 * - Declining closes it with an optional reason the client is shown.
 * - Both are safe to repeat (the same answer again changes nothing, sends nothing). A request already answered the other way, withdrawn or
 *   expired is `request_not_pending`.
 * - Only the counselor's own requests; someone else's and an unknown one are the same `request_not_found`.
 */
class CounselorRequests
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
        private readonly BookingRequests $requests,
        private readonly CounselorPlanner $planner,
    ) {}

    /** How many days back the "Answered" tab looks. */
    public const ANSWERED_DAYS = 30;

    public const PER_PAGE = 20;

    /** The requests still waiting for this counselor, soonest time first (at most 200: used for counts and previews). @return list<array<string, mixed>> */
    public function pending(User $counselor): array
    {
        return $this->waiting($counselor)->limit(200)->get()->map(fn (BookingRequest $request) => $this->row($request))->all();
    }

    /** The requests waiting for this counselor, in pages (nothing is cut off). */
    public function pendingPage(User $counselor, int $page): LengthAwarePaginator
    {
        return $this->waiting($counselor)->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))->through(fn (BookingRequest $request) => $this->row($request));
    }

    /**
     * Requests already dealt with in the last ANSWERED_DAYS days (approved, declined, expired, or withdrawn by the client), newest first.
     * A request that is still marked pending but whose time to answer has passed counts as expired, even before the job has closed it.
     */
    public function answered(User $counselor, int $page): LengthAwarePaginator
    {
        $now = $this->requests->stored($this->availability->now());

        return BookingRequest::query()
            ->where('counselor_user_id', $counselor->getKey())
            ->where(fn ($q) => $q
                ->where(fn ($done) => $done->where('status', '!=', BookingRequestStatus::Pending->value)->where('updated_at', '>=', $now->copy()->subDays(self::ANSWERED_DAYS)))
                ->orWhere(fn ($late) => $late->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '<=', $now)->where('expires_at', '>=', $now->copy()->subDays(self::ANSWERED_DAYS))))
            ->with(['user:id,name', 'location:id,name,city', 'contextOrg:id,name', 'booking:id,public_id'])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->through(fn (BookingRequest $request) => $this->row($request));
    }

    public function waitingCount(User $counselor): int
    {
        return $this->waiting($counselor)->count();
    }

    private function waiting(User $counselor)
    {
        return BookingRequest::query()
            ->where('counselor_user_id', $counselor->getKey())->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '>', $this->requests->stored($this->availability->now()))
            ->with(['user:id,name', 'location:id,name,city', 'contextOrg:id,name', 'booking:id,public_id'])
            ->orderBy('requested_date')->orderBy('requested_time');
    }

    /** @return array<string, mixed> */
    private function row(BookingRequest $request): array
    {
        $start = $this->requests->startOf($request);
        $mode = $request->session_mode ?? SessionMode::Online;
        $status = $this->requests->effectiveStatus($request);

        return [
            'id' => $request->public_id,
            'client' => (string) $request->user?->name,
            'status' => $status->value,
            'date' => $start->format('D, j M Y'),
            'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
            'minutes' => (int) $request->duration_minutes,
            'mode' => $mode->value,
            'place' => $mode === SessionMode::Physical && $request->location !== null ? $request->location->name.', '.$request->location->city : null,
            'context' => (string) $request->contextOrg?->name,
            'note' => $request->message,
            'until' => $request->expires_at->setTimezone($this->availability->timezone())->format('D, j M, g:i A'),
            'reason' => $request->response_reason,
            'booking' => $status === BookingRequestStatus::Approved && $request->booking !== null ? $request->booking->public_id : null,
        ];
    }

    /**
     * @return array{request: BookingRequest, booking: Booking|null, changed: bool}
     *
     * @throws BookingRefused request_not_found | request_not_pending | request_cannot_approve
     */
    public function approve(User $counselor, string $publicId): array
    {
        $found = $this->own($counselor, $publicId);

        return DB::transaction(function () use ($counselor, $found) {
            // LOCK ORDER (the same as booking): the client, the counselor's profile, then the request.
            User::query()->whereKey($found->user_id)->lockForUpdate()->first();
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
            $request = BookingRequest::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status === BookingRequestStatus::Approved) {
                return ['request' => $request, 'booking' => $request->booking, 'changed' => false];
            }

            if ($this->requests->effectiveStatus($request) !== BookingRequestStatus::Pending) {
                throw new BookingRefused('request_not_pending');
            }

            $client = User::query()->findOrFail($request->user_id);
            $start = $this->requests->startOf($request);
            $minutes = (int) $request->duration_minutes;

            $organization = Organization::query()->find($request->context_org_id);

            // The rules of Part 2 that still matter at the moment of approving. The minimum notice is NOT applied again: a counselor may say yes
            // to a request that has become close. Everything else must still hold: the counselor is still approved and listed, the organization
            // is active and its plan covers the day, the day is inside the booking horizon and the time is still inside the weekly hours.
            $stillFits = $start->gt($this->availability->now())
                && $start->startOfDay()->lte($this->availability->lastDate())
                && $profile !== null && $profile->isListable()
                && $organization?->subscription_status === OrgStatus::Active
                && Subscription::allows($organization, $start->format('Y-m-d'))
                && $this->requests->stillInWorkingHours($request)
                && collect($this->planner->contexts($counselor))->contains('id', (int) $request->context_org_id)
                && $this->availability->isFree((int) $counselor->getKey(), $start, $minutes)
                && $this->bookings->limitsRefusalAt($client, $start, $minutes) === null;

            if (! $stillFits) {
                throw new BookingRefused('request_cannot_approve');
            }

            $mode = $request->session_mode ?? SessionMode::Online;

            $entry = CalendarEntry::create([
                'counselor_user_id' => $counselor->getKey(),
                'entry_type' => CalendarEntryType::RequestedSession,
                'context_org_id' => $request->context_org_id,
                'entry_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'duration_minutes' => $minutes,
                'session_mode' => $mode,
                'location_id' => $request->location_id,
                'is_available' => false,
            ]);

            $booking = Booking::create([
                'org_id' => $request->context_org_id,
                'user_id' => $client->getKey(),
                'counselor_user_id' => $counselor->getKey(),
                'calendar_entry_id' => $entry->id,
                'booking_mode' => BookingMode::RequestBased,
                'status' => BookingStatus::Confirmed,
                'session_mode' => $mode,
                'location_id' => $request->location_id,
                'reschedule_count' => 0,
            ]);

            $request->update(['status' => BookingRequestStatus::Approved, 'responded_at' => $this->requests->stored($this->availability->now()), 'booking_id' => $booking->id]);

            // Same transaction as the booking. Ids only: never the client, never their note.
            Audit::log('request.approved', $request, ['request_id' => $request->getKey(), 'booking_id' => $booking->getKey(), 'context_org_id' => $request->context_org_id], $counselor);

            return ['request' => $request->fresh(), 'booking' => $booking, 'changed' => true];
        });
    }

    /**
     * @return array{request: BookingRequest, changed: bool}
     *
     * @throws BookingRefused request_not_found | request_not_pending
     */
    public function decline(User $counselor, string $publicId, ?string $reason): array
    {
        $found = $this->own($counselor, $publicId);
        $reason = trim((string) $reason);

        return DB::transaction(function () use ($counselor, $found, $reason) {
            User::query()->whereKey($found->user_id)->lockForUpdate()->first();
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
            $request = BookingRequest::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status === BookingRequestStatus::Rejected) {
                return ['request' => $request, 'changed' => false];
            }

            if ($this->requests->effectiveStatus($request) !== BookingRequestStatus::Pending) {
                throw new BookingRefused('request_not_pending');
            }

            $request->update(['status' => BookingRequestStatus::Rejected, 'response_reason' => $reason === '' ? null : $reason, 'responded_at' => $this->requests->stored($this->availability->now())]);

            // Whether a reason was given, never the reason itself (the client sees it; the audit log does not keep it).
            Audit::log('request.declined', $request, ['request_id' => $request->getKey(), 'has_reason' => $reason !== '', 'context_org_id' => $request->context_org_id], $counselor);

            return ['request' => $request->fresh(), 'changed' => true];
        });
    }

    private function own(User $counselor, string $publicId): BookingRequest
    {
        return BookingRequest::query()->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->first()
            ?? throw new BookingRefused('request_not_found');
    }
}
