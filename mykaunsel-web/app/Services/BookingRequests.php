<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use App\Support\CounselorPublicPresenter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Mode B, the client's side: "ask this counselor for a time" (docs/AVAILABILITY.md, "Time requests").
 *
 * - A request names a date and a start time that fall inside one of the counselor's WEEKLY HOURS lines that this person may see. That line
 *   decides the length, online or in person, the place and the organization, so the client picks only the time.
 * - It waits for the counselor for `booking.request_expiry_hours` (48), or until the time itself if that comes first. Nobody answering closes it
 *   (`requests:expire`). Reading it also never shows a request as pending once its time to answer has gone by.
 * - At most `booking.max_pending_requests` wait at once per person; one may not overlap another waiting request or an upcoming session of
 *   the person; the time must be free on the counselor's calendar now (buffer and blocks included).
 * - Safe to repeat: the same request again (same counselor, date, time) while it waits answers the one that exists.
 * - Withdrawing is allowed while it waits; withdrawing one already withdrawn answers the same.
 * - Nothing is booked, claimed or held by a request. The counselor's approval books it (CounselorRequests).
 */
class BookingRequests
{
    public function __construct(
        private readonly CounselorDirectory $directory,
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
    ) {}

    // =============================================================================================================
    //  The counselor's hours, for the screen that picks the time
    // =============================================================================================================

    /**
     * @return array<string, mixed>
     *
     * @throws BookingRefused counselor_unavailable
     */
    public function workingHours(User $viewer, string $counselorPublicId): array
    {
        $found = $this->directory->resolveBookable($viewer, $counselorPublicId) ?? throw new BookingRefused('counselor_unavailable');
        $now = $this->availability->now();

        $lines = CounselorWeeklyHour::query()
            ->where('counselor_user_id', $found['profile']->user_id)->whereIn('context_org_id', $found['orgIds'])
            ->with('location:id,name,city')->orderBy('weekday')->orderBy('start_time')->get();

        return [
            'counselor' => ['id' => $found['profile']->public_id, 'display_name' => CounselorPublicPresenter::displayName($found['profile'], $found['orgIds'])],
            'accepts_requests' => (bool) $found['profile']->accepts_requests,
            'hours' => $lines->map(fn (CounselorWeeklyHour $line) => [
                'weekday' => $line->weekday,
                'from' => substr((string) $line->start_time, 0, 5),
                'to' => substr((string) $line->end_time, 0, 5),
                'duration_minutes' => $line->slot_minutes,
                'mode' => $line->session_mode->value,
                'location' => $line->location === null ? null : ['name' => (string) $line->location->name, 'city' => (string) $line->location->city],
            ])->values()->all(),
            'booking_window' => [
                'earliest_start' => $this->availability->earliestStart($now)->toIso8601String(),
                'last_date' => $this->availability->lastDate($now)->format('Y-m-d'),
                'min_lead_hours' => (int) config('booking.min_lead_hours'),
            ],
            'answer_within_hours' => (int) config('booking.request_expiry_hours'),
        ];
    }

    // =============================================================================================================
    //  Asking
    // =============================================================================================================

    /**
     * @param  array{date: string, time: string, message?: string|null}  $input
     * @return array{request: BookingRequest, created: bool, profile: CounselorProfile, orgIds: list<int>}
     *
     * @throws BookingRefused
     */
    public function create(User $user, string $counselorPublicId, array $input): array
    {
        $found = $this->directory->resolveBookable($user, $counselorPublicId) ?? throw new BookingRefused('counselor_unavailable');

        if (! $found['profile']->accepts_requests) {
            throw new BookingRefused('requests_closed');
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $input['date'].' '.$input['time'], $this->availability->timezone());
        $now = $this->availability->now();

        if ((int) $start->format('i') % 5 !== 0 || $start->lt($this->availability->earliestStart($now)) || $start->startOfDay()->gt($this->availability->lastDate($now))) {
            throw new BookingRefused('request_time_invalid');
        }

        $line = $this->lineFor($found['profile']->user_id, $found['orgIds'], $start);

        if ($line === null) {
            throw new BookingRefused('request_time_invalid');
        }

        $made = DB::transaction(function () use ($user, $input, $start, $line, $found, $now) {
            // The same lock order as booking: the person, then the counselor's profile.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            CounselorProfile::query()->where('user_id', $found['profile']->user_id)->lockForUpdate()->first();

            $minutes = (int) $line->slot_minutes;

            // A repeat of the same request while it waits: answer the one that exists.
            $same = BookingRequest::query()
                ->where('user_id', $user->getKey())->where('counselor_user_id', $found['profile']->user_id)
                ->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '>', $this->stored($now))
                ->whereDate('requested_date', $start->format('Y-m-d'))->where('requested_time', $start->format('H:i:s'))->first();

            if ($same !== null) {
                return [$same, false];
            }

            $waiting = BookingRequest::query()->where('user_id', $user->getKey())->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '>', $this->stored($now))->get();

            if ($waiting->count() >= max(1, (int) config('booking.max_pending_requests'))) {
                throw new BookingRefused('request_limit_reached', ['max_pending_requests' => (int) config('booking.max_pending_requests')]);
            }

            $end = $start->addMinutes($minutes);

            foreach ($waiting as $other) {
                $otherStart = $this->startOf($other);

                if ($start->lt($otherStart->addMinutes((int) $other->duration_minutes)) && $end->gt($otherStart)) {
                    throw new BookingRefused('time_conflict');
                }
            }

            if ($this->bookings->overlapsUpcoming($user, $start, $minutes)) {
                throw new BookingRefused('time_conflict');
            }

            if (! $this->availability->isFree((int) $found['profile']->user_id, $start, $minutes)) {
                throw new BookingRefused('slot_taken');
            }

            $expires = $now->addHours((int) config('booking.request_expiry_hours'));

            $request = BookingRequest::create([
                'user_id' => $user->getKey(),
                'counselor_user_id' => $found['profile']->user_id,
                'context_org_id' => $line->context_org_id,
                'requested_date' => $start->format('Y-m-d'),
                'requested_time' => $start->format('H:i:s'),
                'duration_minutes' => $minutes,
                'session_mode' => $line->session_mode,
                'location_id' => $line->session_mode === SessionMode::Physical ? $line->location_id : null,
                'message' => $this->note($input['message'] ?? null),
                'status' => BookingRequestStatus::Pending,
                'expires_at' => $this->stored($expires->gt($start) ? $start : $expires),
            ]);

            return [$request, true];
        });

        return ['request' => $made[0], 'created' => $made[1], 'profile' => $found['profile'], 'orgIds' => $found['orgIds']];
    }

    /** Withdraws a request that still waits. Withdrawing one already withdrawn answers the same and changes nothing. */
    public function withdraw(User $user, string $publicId): BookingRequest
    {
        $found = $this->own($user, $publicId);

        return DB::transaction(function () use ($user, $found) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            $request = BookingRequest::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status === BookingRequestStatus::Withdrawn) {
                return $request;
            }

            if ($this->effectiveStatus($request) !== BookingRequestStatus::Pending) {
                throw new BookingRefused('request_not_pending');
            }

            $request->update(['status' => BookingRequestStatus::Withdrawn, 'responded_at' => $this->stored($this->availability->now())]);

            return $request;
        });
    }

    // =============================================================================================================
    //  Reading
    // =============================================================================================================

    /** The person's own requests, newest first. */
    public function list(User $user, int $page): array
    {
        $paginator = BookingRequest::query()->where('user_id', $user->getKey())->with(['counselor.counselorProfile', 'location:id,name,city', 'booking:id,public_id'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['*'], 'page', max(1, $page));

        return [
            'data' => $paginator->getCollection()->map(fn (BookingRequest $request) => $this->present($request))->values()->all(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function find(User $user, string $publicId): ?array
    {
        $request = BookingRequest::query()->where('user_id', $user->getKey())->where('public_id', strtolower($publicId))
            ->with(['counselor.counselorProfile', 'location:id,name,city', 'booking:id,public_id'])->first();

        return $request === null ? null : $this->present($request);
    }

    /** What the person is told. A request still "pending" after its time to answer is shown as expired. */
    public function present(BookingRequest $request): array
    {
        $start = $this->startOf($request);
        $profile = $request->counselor?->counselorProfile;
        $orgIds = $request->context_org_id === null ? [] : [(int) $request->context_org_id];
        $mode = $request->session_mode ?? SessionMode::Online;

        return [
            'id' => $request->public_id,
            'status' => $this->effectiveStatus($request)->value,
            'counselor' => [
                'id' => $profile?->public_id,
                'display_name' => $profile === null ? '' : CounselorPublicPresenter::displayName($profile, $orgIds),
            ],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->addMinutes((int) $request->duration_minutes)->toIso8601String(),
            'duration_minutes' => (int) $request->duration_minutes,
            'mode' => $mode->value,
            'location' => $mode === SessionMode::Physical && $request->location !== null ? ['name' => (string) $request->location->name, 'city' => (string) $request->location->city] : null,
            'message' => $request->message,
            'response_reason' => $request->response_reason,
            'expires_at' => $request->expires_at->toIso8601String(),
            'responded_at' => $request->responded_at?->toIso8601String(),
            'booking_id' => $request->booking?->public_id,
        ];
    }

    public function effectiveStatus(BookingRequest $request): BookingRequestStatus
    {
        return $request->status === BookingRequestStatus::Pending && $request->expires_at->lte($this->availability->now()) ? BookingRequestStatus::Expired : $request->status;
    }

    /** A moment as it is stored: in the application's timezone, so it is read back as the same instant. */
    public function stored(CarbonImmutable $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone((string) config('app.timezone'));
    }

    public function startOf(BookingRequest $request): CarbonImmutable
    {
        return CarbonImmutable::parse($request->requested_date->format('Y-m-d').' '.$request->requested_time, $this->availability->timezone());
    }

    // =============================================================================================================
    //  Closing the unanswered
    // =============================================================================================================

    /**
     * Closes every request nobody answered in time. Safe to run again and again.
     *
     * @return list<BookingRequest> the ones closed now (so the people can be told)
     */
    public function expireDue(): array
    {
        $closed = [];
        $now = $this->availability->now();

        BookingRequest::query()->where('status', BookingRequestStatus::Pending->value)->where('expires_at', '<=', $this->stored($now))->orderBy('id')
            ->chunkById(200, function ($requests) use (&$closed, $now) {
                foreach ($requests as $request) {
                    $changed = BookingRequest::query()->whereKey($request->getKey())->where('status', BookingRequestStatus::Pending->value)
                        ->update(['status' => BookingRequestStatus::Expired->value, 'responded_at' => $this->stored($now)]);

                    if ($changed === 1) {
                        $closed[] = $request->fresh();
                    }
                }
            });

        return $closed;
    }

    // ---------------------------------------------------------------------------------------------------------

    private function own(User $user, string $publicId): BookingRequest
    {
        return BookingRequest::query()->where('user_id', $user->getKey())->where('public_id', strtolower($publicId))->first()
            ?? throw new BookingRefused('request_not_found');
    }

    /** The weekly-hours line the time falls in (the whole length inside its window), among the contexts this person may see. */
    private function lineFor(int $counselorUserId, array $orgIds, CarbonImmutable $start): ?CounselorWeeklyHour
    {
        $from = (int) $start->format('H') * 60 + (int) $start->format('i');

        return CounselorWeeklyHour::query()
            ->where('counselor_user_id', $counselorUserId)->whereIn('context_org_id', $orgIds)->where('weekday', $start->isoWeekday())
            ->get()->first(fn (CounselorWeeklyHour $line) => $from >= $this->minutes((string) $line->start_time) && $from + $line->slot_minutes <= $this->minutes((string) $line->end_time));
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    private function note(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : $message;
    }
}
