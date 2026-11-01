<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A counselor's private notes about their sessions (docs/SESSION_NOTES.md).
 *
 * - Only the counselor's OWN sessions, and only ones that have started and were not cancelled. Someone else's session and an unknown one are the
 *   same `booking_not_found`.
 * - The text is encrypted at rest (the model cast). It is read here and nowhere else: the list never loads it (only whether a note exists).
 * - One note per session. Saving again replaces it; deleting erases it for good.
 * - Audit: that a note was saved or deleted, with ids only. Never the text, never its length.
 * - Erased automatically a fixed time after the session (`session-notes:purge`).
 */
class SessionNotes
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** Sessions that can have a note, newest first, each with whether it has one. */
    public function sessions(User $counselor, int $page): LengthAwarePaginator
    {
        $paginator = $this->mine($counselor)
            ->withExists('sessionNote as has_note')
            ->orderByDesc('calendar_entries.entry_date')->orderByDesc('calendar_entries.start_time')
            ->paginate((int) config('session-notes.per_page'), ['bookings.*'], 'page', max(1, $page));

        return $paginator->through(fn (Booking $booking) => $this->row($booking) + ['has_note' => (bool) $booking->has_note]);
    }

    /**
     * One session with its note (the text, only here).
     *
     * @return array<string, mixed>
     *
     * @throws BookingRefused booking_not_found
     */
    public function find(User $counselor, string $publicId): array
    {
        $booking = $this->mine($counselor)->where('bookings.public_id', strtolower($publicId))->with('sessionNote')->first();

        if ($booking === null) {
            throw new BookingRefused('booking_not_found');
        }

        return $this->row($booking) + [
            'has_note' => $booking->sessionNote !== null,
            'body' => (string) $booking->sessionNote?->body,
            'saved_at' => $booking->sessionNote?->updated_at?->setTimezone($this->availability->timezone())->format('j M Y, g:i A'),
        ];
    }

    /**
     * Saves (or replaces) the note of one session.
     *
     * @return bool true when it is a new note
     *
     * @throws BookingRefused booking_not_found
     */
    public function save(User $counselor, string $publicId, string $body): bool
    {
        return DB::transaction(function () use ($counselor, $publicId, $body) {
            $booking = $this->mine($counselor)->where('bookings.public_id', strtolower($publicId))->lockForUpdate()->first() ?? throw new BookingRefused('booking_not_found');

            $note = SessionNote::query()->where('booking_id', $booking->getKey())->first();
            $created = $note === null;

            if ($created) {
                $note = new SessionNote(['booking_id' => $booking->getKey(), 'counselor_user_id' => $counselor->getKey()]);
            }

            $note->body = trim($body);
            $note->save();

            Audit::log('session_note.saved', $booking, ['booking_id' => $booking->getKey(), 'created' => $created], $counselor);

            return $created;
        });
    }

    /** @throws BookingRefused booking_not_found */
    public function delete(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            $booking = $this->mine($counselor)->where('bookings.public_id', strtolower($publicId))->lockForUpdate()->first() ?? throw new BookingRefused('booking_not_found');

            if (SessionNote::query()->where('booking_id', $booking->getKey())->delete() > 0) {
                Audit::log('session_note.deleted', $booking, ['booking_id' => $booking->getKey()], $counselor);
            }
        });
    }

    /** Can this booking have a note? Started, not cancelled. Used by the session page to show the link. */
    public function allowed(Booking $booking): bool
    {
        return $booking->status !== BookingStatus::Cancelled && $this->availability->startOf($booking->calendarEntry)->lte($this->availability->now());
    }

    // ---------------------------------------------------------------------------------------------------------

    private function mine(User $counselor)
    {
        $now = $this->availability->now();

        return Booking::query()
            ->select('bookings.*')
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.counselor_user_id', $counselor->getKey())
            ->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->where(fn ($q) => $q
                ->whereDate('calendar_entries.entry_date', '<', $now->format('Y-m-d'))
                ->orWhere(fn ($today) => $today->whereDate('calendar_entries.entry_date', '=', $now->format('Y-m-d'))->where('calendar_entries.start_time', '<=', $now->format('H:i:s'))))
            ->with(['user:id,name', 'calendarEntry.location:id,name,city']);
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
            'mode' => $mode->value,
        ];
    }
}
