<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
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
 * - No audit entry (B12), no e-mail, no change to any time (these actions never touch `is_available`).
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

    /** Completed, no-show and cancelled sessions, newest first. */
    public function past(User $counselor, int $page): LengthAwarePaginator
    {
        $paginator = $this->mine($counselor)
            ->whereIn('bookings.status', [BookingStatus::Completed->value, BookingStatus::NoShow->value, BookingStatus::Cancelled->value])
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

    // ---------------------------------------------------------------------------------------------------------

    private function mine(User $counselor)
    {
        return Booking::query()
            ->select('bookings.*')
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.counselor_user_id', $counselor->getKey())
            ->with(['user:id,name', 'calendarEntry.location:id,name,city']);
    }

    /** The counselor's own booking, locked. Anyone else's is the same "not found". */
    private function lockOwn(User $counselor, string $publicId): Booking
    {
        $booking = Booking::query()
            ->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->whereNotNull('calendar_entry_id')
            ->lockForUpdate()->first();

        return $booking ?? throw new BookingRefused('booking_not_found');
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
            'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
            'minutes' => (int) $entry->duration_minutes,
            'mode' => $mode->value,
            'place' => $mode === SessionMode::Physical && $entry->location !== null ? $entry->location->name.', '.$entry->location->city : null,
            'meeting_provider' => $booking->meeting_provider,
            'meeting_url' => $booking->meeting_url,
        ];
    }
}
