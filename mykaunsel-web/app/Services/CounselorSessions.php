<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A counselor's OWN sessions on the web portal (docs/AVAILABILITY.md, "The counselor's sessions").
 *
 * - Only the counselor's own bookings. Someone else's and an unknown one are the same `booking_not_found`.
 * - The client is shown by display name ONLY (B3): no e-mail, no phone.
 * - Three lists: upcoming (confirmed, not ended), to mark (confirmed, ended: the counselor says what happened) and past (completed, no-show,
 *   cancelled). A session is NEVER assumed completed because its time has passed: only the counselor marks it.
 * - The meeting link: online, confirmed, not ended yet. Clearing it is allowed. The link is what the client's app shows 15 minutes before.
 * - Only cancelling is audited (booking id only, never the client or the reason). Setting a link and marking an outcome are not.
 * - Cancelling is allowed until the session STARTS; marking and the link do not touch any time (they never change `is_available`).
 */
class CounselorSessions
{
    public const PROVIDERS = ['Google Meet', 'Zoom', 'Microsoft Teams', 'Other'];

    public const PAST_PER_PAGE = 20;

    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return array{upcoming: list<array<string, mixed>>, to_mark: list<array<string, mixed>>} */
    public function open(User $counselor): array
    {
        $now = $this->availability->now();
        $upcoming = [];
        $toMark = [];

        $bookings = $this->mine($counselor)
            ->where('bookings.status', BookingStatus::Confirmed->value)
            ->orderBy('calendar_entries.entry_date')->orderBy('calendar_entries.start_time')
            ->limit(300)->get();

        foreach ($bookings as $booking) {
            $row = $this->row($booking);
            $ended = $this->availability->startOf($booking->calendarEntry)->addMinutes((int) $booking->calendarEntry->duration_minutes)->lte($now);

            if ($ended) {
                $toMark[] = $row;
            } else {
                $upcoming[] = $row;
            }
        }

        return ['upcoming' => $upcoming, 'to_mark' => array_reverse($toMark)];
    }

    /**
     * The Bookings page: the same two lists as open(), but in pages (nothing is cut off silently).
     * "Needs marking" = confirmed and ended, newest first; "Upcoming" = confirmed and not ended, soonest first.
     *
     * @return array{upcoming: LengthAwarePaginator, to_mark: LengthAwarePaginator}
     */
    public function lists(User $counselor, int $upcomingPage = 1, int $markPage = 1): array
    {
        $now = $this->availability->now();
        $today = $now->format('Y-m-d');

        // Only today can be both "not over by date" and ended: those few are sorted out here, everything else by date in SQL.
        $endedToday = $this->mine($counselor)
            ->where('bookings.status', BookingStatus::Confirmed->value)->whereDate('calendar_entries.entry_date', $today)
            ->get()->filter(fn (Booking $b) => $this->ended($b))->pluck('id')->all();

        $toMark = $this->mine($counselor)->where('bookings.status', BookingStatus::Confirmed->value)
            ->where(fn ($q) => $q->whereDate('calendar_entries.entry_date', '<', $today)->orWhereIn('bookings.id', $endedToday))
            ->orderByDesc('calendar_entries.entry_date')->orderByDesc('calendar_entries.start_time')
            ->paginate(self::PAST_PER_PAGE, ['bookings.*'], 'mark_page', max(1, $markPage));

        $upcoming = $this->mine($counselor)->where('bookings.status', BookingStatus::Confirmed->value)
            ->whereDate('calendar_entries.entry_date', '>=', $today)->whereNotIn('bookings.id', $endedToday)
            ->orderBy('calendar_entries.entry_date')->orderBy('calendar_entries.start_time')
            ->paginate(self::PAST_PER_PAGE, ['bookings.*'], 'up_page', max(1, $upcomingPage));

        return [
            'upcoming' => $upcoming->through(fn (Booking $booking) => $this->row($booking)),
            'to_mark' => $toMark->through(fn (Booking $booking) => $this->row($booking)),
        ];
    }

    /**
     * Completed, no-show and cancelled sessions, newest first.
     *
     * @param  array{status?: ?string, from?: ?string, to?: ?string, context?: ?int}  $filters  status (one of the three), first and last day, and the
     *                                                                                          organization the session was held for
     */
    public function past(User $counselor, int $page, array $filters = []): LengthAwarePaginator
    {
        $statuses = [BookingStatus::Completed->value, BookingStatus::NoShow->value, BookingStatus::Cancelled->value];
        $status = $filters['status'] ?? null;

        $paginator = $this->mine($counselor)
            ->whereIn('bookings.status', in_array($status, $statuses, true) ? [$status] : $statuses)
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('calendar_entries.entry_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('calendar_entries.entry_date', '<=', $to))
            ->when($filters['context'] ?? null, fn ($q, $context) => $q->where('bookings.org_id', (int) $context))
            ->orderByDesc('calendar_entries.entry_date')->orderByDesc('calendar_entries.start_time')
            ->paginate(self::PAST_PER_PAGE, ['bookings.*'], 'page', max(1, $page));

        return $paginator->through(fn (Booking $booking) => $this->row($booking));
    }

    /**
     * Sets (or, with an empty url, removes) the meeting link of one online session that is still ahead.
     *
     * @throws BookingRefused booking_not_found | meeting_closed
     */
    public function setMeeting(User $counselor, string $publicId, ?string $provider, ?string $url): void
    {
        DB::transaction(function () use ($counselor, $publicId, $provider, $url) {
            $booking = $this->lockOwn($counselor, $publicId);

            if ($booking->status !== BookingStatus::Confirmed || $booking->session_mode !== SessionMode::Online || $this->ended($booking)) {
                throw new BookingRefused('meeting_closed');
            }

            $booking->forceFill($url === null || $url === ''
                ? ['meeting_provider' => null, 'meeting_url' => null]
                : ['meeting_provider' => $provider, 'meeting_url' => $url])->save();
        });
    }

    /**
     * Says what happened in a session that has ended: completed or no-show. Only from confirmed, only after the session's end. Saying the same
     * thing again changes nothing; saying something else once it is set is refused.
     *
     * @throws BookingRefused booking_not_found | session_not_ended | outcome_set
     */
    public function mark(User $counselor, string $publicId, BookingStatus $outcome): void
    {
        DB::transaction(function () use ($counselor, $publicId, $outcome) {
            $booking = $this->lockOwn($counselor, $publicId);

            if ($booking->status === $outcome) {
                return; // a repeat
            }

            if ($booking->status !== BookingStatus::Confirmed) {
                throw new BookingRefused('outcome_set');
            }

            if (! $this->ended($booking)) {
                throw new BookingRefused('session_not_ended');
            }

            $changed = Booking::query()->whereKey($booking->getKey())->where('status', BookingStatus::Confirmed->value)->update(['status' => $outcome->value]);

            if ($changed !== 1) {
                throw new BookingRefused('outcome_set');
            }
        });
    }

    /**
     * One of the counselor's own sessions with its details (the client is a display name only).
     *
     * @return array<string, mixed>
     *
     * @throws BookingRefused booking_not_found
     */
    public function find(User $counselor, string $publicId): array
    {
        $booking = $this->mine($counselor)->where('bookings.public_id', strtolower($publicId))->first();

        if ($booking === null) {
            throw new BookingRefused('booking_not_found');
        }

        $row = $this->row($booking);
        $confirmed = $booking->status === BookingStatus::Confirmed;
        $ended = $this->ended($booking);

        return $row + [
            'can_cancel' => $confirmed && ! $this->started($booking),
            'can_mark' => $confirmed && $ended,
            'can_note' => app(SessionNotes::class)->allowed($booking),
            'can_set_meeting' => $confirmed && ! $ended && ($booking->session_mode ?? SessionMode::Online) === SessionMode::Online,
            'cancelled_by' => $booking->cancelled_by,
            'cancel_reason' => $booking->cancel_reason,
        ];
    }

    /**
     * The counselor cancels one of their own sessions that has not STARTED: the status, who and why, the time given back (or, with
     * $keepClosed, covered by a personal block so nobody can book it and clients still never see it as "booked") and the audit entry, in one
     * transaction (B9). There is no deadline (a counselor may be ill the same morning); the caller tells the client after the commit.
     * Cancelling again changes nothing and returns null.
     *
     * @return array{booking: Booking, client: User, when: string}|null
     *
     * @throws BookingRefused booking_not_found | not_cancellable
     */
    public function cancel(User $counselor, string $publicId, string $reason, bool $keepClosed = false): ?array
    {
        // Read without a lock first, so the locks below can be taken in the same order as the client's own cancelling, rescheduling and booking.
        $found = $this->mine($counselor)->where('bookings.public_id', strtolower($publicId))->whereNotNull('bookings.calendar_entry_id')->first()
            ?? throw new BookingRefused('booking_not_found');

        return DB::transaction(function () use ($counselor, $found, $reason, $keepClosed) {
            // LOCK ORDER: the client, the counselor's profile, then the booking (the same as the client's own cancelling and rescheduling).
            User::query()->whereKey($found->user_id)->lockForUpdate()->first();
            $profile = \App\Models\CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
            $booking = Booking::query()->with('calendarEntry')->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            if ($booking->status === BookingStatus::Cancelled) {
                return null; // a repeat
            }

            if ($booking->status !== BookingStatus::Confirmed || $this->started($booking)) {
                throw new BookingRefused('not_cancellable');
            }

            $changed = Booking::query()->whereKey($booking->getKey())->where('status', BookingStatus::Confirmed->value)->update([
                'status' => BookingStatus::Cancelled->value,
                'cancelled_by' => 'counselor',
                'cancel_reason' => mb_substr(trim($reason), 0, 300),
                'cancelled_at' => now(),
            ]);

            if ($changed !== 1) {
                throw new BookingRefused('not_cancellable');
            }

            \App\Models\CalendarEntry::query()->whereKey($booking->calendar_entry_id)->update(['is_available' => true]);

            $start = $this->availability->startOf($booking->calendarEntry);

            // "Do not open this time again": the time is covered by an ordinary personal block. It is hidden from clients (never "booked") and
            // the counselor removes the block on the Schedule page when they want it back.
            if ($keepClosed) {
                \App\Models\CalendarEntry::create([
                    'counselor_user_id' => $counselor->getKey(),
                    'entry_type' => \App\Enums\CalendarEntryType::PersonalBlock,
                    'entry_date' => $start->format('Y-m-d'),
                    'start_time' => $start->format('H:i:s'),
                    'duration_minutes' => (int) $booking->calendarEntry->duration_minutes,
                    'is_available' => true,
                ]);
            }

            // The booking's id and whether the time was kept closed: never the client, never the reason.
            Audit::log('booking.cancelled_by_counselor', $booking, ['booking_id' => $booking->getKey(), 'kept_closed' => $keepClosed], $counselor);

            return ['booking' => $booking->fresh(), 'client' => $booking->user()->firstOrFail(), 'when' => $start->format('D, j M Y').', '.$start->format('g:i A').' (Malaysia time)'];
        });
    }

    // ---------------------------------------------------------------------------------------------------------

    private function mine(User $counselor)
    {
        return Booking::query()
            ->select('bookings.*')
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.counselor_user_id', $counselor->getKey())
            ->with(['user:id,name', 'organization:id,name', 'calendarEntry.location:id,name,city']);
    }

    /** The counselor's own booking, locked. Anyone else's is the same "not found". */
    private function lockOwn(User $counselor, string $publicId): Booking
    {
        $booking = Booking::query()
            ->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->whereNotNull('calendar_entry_id')
            ->lockForUpdate()->first();

        return $booking ?? throw new BookingRefused('booking_not_found');
    }

    private function started(Booking $booking): bool
    {
        return $this->availability->startOf($booking->calendarEntry)->lte($this->availability->now());
    }

    private function ended(Booking $booking): bool
    {
        $entry = $booking->calendarEntry;

        return $this->availability->startOf($entry)->addMinutes((int) $entry->duration_minutes)->lte($this->availability->now());
    }

    /** @return array<string, mixed> */
    private function row(Booking $booking): array
    {
        $entry = $booking->calendarEntry;
        $start = $this->availability->startOf($entry);
        $mode = $booking->session_mode ?? SessionMode::Online;

        return [
            'id' => $booking->public_id,
            'client' => (string) $booking->user?->name,
            'status' => $booking->status->value,
            'date' => $start->format('D, j M Y'),
            'starts' => $start->format('H:i'),   // sortable, for ordering one day
            'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
            'minutes' => (int) $entry->duration_minutes,
            'mode' => $mode->value,
            'place' => $mode === SessionMode::Physical && $entry->location !== null ? $entry->location->name.', '.$entry->location->city : null,
            'context' => (string) $booking->organization?->name,   // the organization (or MyKaunsel) the session was held for
            'meeting_provider' => $booking->meeting_provider,
            'meeting_url' => $booking->meeting_url,
            'link_minutes' => (int) config('booking.meeting_link_visible_minutes'),   // how long before the start the client sees the link
        ];
    }
}
