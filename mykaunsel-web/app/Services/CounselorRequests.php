<?php

namespace App\Services;

use App\Enums\BookingMode;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
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

    /** The requests still waiting for this counselor, soonest time first. @return list<array<string, mixed>> */
    public function pending(User $counselor): array
    {
        return BookingRequest::query()
            ->where('counselor_user_id', $counselor->getKey())->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '>', $this->requests->stored($this->availability->now()))
            ->with(['user:id,name', 'location:id,name,city'])
            ->orderBy('requested_date')->orderBy('requested_time')->limit(200)->get()
            ->map(function (BookingRequest $request) {
                $start = $this->requests->startOf($request);
                $mode = $request->session_mode ?? SessionMode::Online;

                return [
                    'id' => $request->public_id,
                    'client' => (string) $request->user?->name,
                    'date' => $start->format('D, j M Y'),
                    'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
                    'minutes' => (int) $request->duration_minutes,
                    'mode' => $mode->value,
                    'place' => $mode === SessionMode::Physical && $request->location !== null ? $request->location->name.', '.$request->location->city : null,
                    'note' => $request->message,
                    'until' => $request->expires_at->setTimezone($this->availability->timezone())->format('D, j M, g:i A'),
                ];
            })->all();
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
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
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

            $stillFits = $start->gt($this->availability->now())
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

            return ['request' => $request->fresh(), 'changed' => true];
        });
    }

    private function own(User $counselor, string $publicId): BookingRequest
    {
        return BookingRequest::query()->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->first()
            ?? throw new BookingRefused('request_not_found');
    }
}
