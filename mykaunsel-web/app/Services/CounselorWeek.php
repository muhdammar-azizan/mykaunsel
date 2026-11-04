<?php

namespace App\Services;

use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The counselor's own calendar for one week, Monday to Sunday in Malaysian time (docs/AVAILABILITY.md, "The weekly view").
 *
 * - Read only: it shows what the Schedule page already holds (open times, blocks, booked sessions), nothing is changed here.
 * - A booked session is "Booked session" with a link to its page in Bookings. The client's name is NOT shown here.
 * - An open time that clients can not see carries the reason (counselor only, see AvailabilityService::hiddenReasons), never "booked".
 * - Entries of every context are shown together: a counselor is one person; the context name is only a label.
 */
class CounselorWeek
{
    /** How far from today a week can be reached, so the buttons can never walk off into nothing. */
    public const WEEKS_BACK = 26;

    public const WEEKS_AHEAD = 52;

    public function __construct(private readonly AvailabilityService $availability) {}

    /** The Monday (Y-m-d) of the week that holds the given day, or this week's Monday when the day is missing, not a date, or out of reach. */
    public function mondayOf(?string $day): CarbonImmutable
    {
        $today = $this->availability->now()->startOfDay();
        $monday = $today->startOfWeek();

        if ($day === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return $monday;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $day, $this->availability->timezone());

        if ($parsed === false || $parsed->format('Y-m-d') !== $day) {
            return $monday;
        }

        $asked = $parsed->startOfWeek();

        return $asked->lt($monday->subWeeks(self::WEEKS_BACK)) || $asked->gt($monday->addWeeks(self::WEEKS_AHEAD)) ? $monday : $asked;
    }

    /**
     * @return array{monday: string, label: string, previous: ?string, next: ?string, this_week: string, is_this_week: bool, days: list<array<string, mixed>>}
     */
    public function forWeek(User $counselor, CarbonImmutable $monday): array
    {
        $now = $this->availability->now();
        $today = $now->startOfDay();
        $sunday = $monday->addDays(6);
        $thisMonday = $today->startOfWeek();

        $entries = CalendarEntry::query()
            ->where('counselor_user_id', $counselor->getKey())
            ->whereIn('entry_type', [CalendarEntryType::AvailableSlot->value, CalendarEntryType::PersonalBlock->value, CalendarEntryType::RequestedSession->value])
            ->whereDate('entry_date', '>=', $monday->format('Y-m-d'))->whereDate('entry_date', '<=', $sunday->format('Y-m-d'))
            ->with(['location:id,name,city', 'contextOrg:id,name'])
            ->with(['bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')->select(['id', 'public_id', 'calendar_entry_id', 'status'])])
            ->withExists(['bookings as has_active' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderBy('start_time')->orderBy('id')->get();

        $free = $entries->filter(fn (CalendarEntry $e) => $e->entry_type === CalendarEntryType::AvailableSlot && $e->is_available && ! $e->has_active);
        $hidden = $this->availability->hiddenReasons($free);

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $monday->addDays($i);
            $key = $day->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'name' => $day->format('l'),
                'short' => $day->format('D'),
                'label' => $day->format('j M'),
                'is_today' => $day->equalTo($today),
                'is_past' => $day->lt($today),
                'items' => $entries->filter(fn (CalendarEntry $e) => $e->entry_date->format('Y-m-d') === $key)
                    ->map(fn (CalendarEntry $e) => $this->item($e, $hidden))->values()->all(),
            ];
        }

        return [
            'monday' => $monday->format('Y-m-d'),
            'label' => $monday->format('j M').' – '.$sunday->format('j M Y'),
            'previous' => $monday->gt($thisMonday->subWeeks(self::WEEKS_BACK)) ? $monday->subWeek()->format('Y-m-d') : null,
            'next' => $monday->lt($thisMonday->addWeeks(self::WEEKS_AHEAD)) ? $monday->addWeek()->format('Y-m-d') : null,
            'this_week' => $thisMonday->format('Y-m-d'),
            'is_this_week' => $monday->equalTo($thisMonday),
            'days' => $days,
        ];
    }

    /**
     * @param  array<int, string>  $hidden  slot id => block|session
     * @return array<string, mixed>
     */
    private function item(CalendarEntry $entry, array $hidden): array
    {
        $start = $this->availability->startOf($entry);
        $minutes = (int) $entry->duration_minutes;
        $allDay = $minutes >= 1440;
        $time = $allDay ? __('All day') : $start->format('g:i A').' – '.$start->addMinutes($minutes)->format('g:i A');

        if ($entry->entry_type === CalendarEntryType::PersonalBlock) {
            // The counselor's own note: only they ever see this page.
            return ['kind' => 'block', 'time' => $time, 'title' => (string) $entry->title, 'link' => null, 'detail' => null, 'hidden' => null];
        }

        $booking = $entry->bookings->first();

        if ($entry->entry_type === CalendarEntryType::RequestedSession || ! $entry->is_available || $booking !== null) {
            return [
                'kind' => 'session', 'time' => $time, 'title' => __('Booked session'),
                'link' => $booking === null ? null : route('counselor.appointments.show', $booking->public_id),
                'detail' => $this->detail($entry), 'hidden' => null,
            ];
        }

        return ['kind' => 'open', 'time' => $time, 'title' => __('Open'), 'link' => null, 'detail' => $this->detail($entry), 'hidden' => $hidden[$entry->id] ?? null];
    }

    private function detail(CalendarEntry $entry): string
    {
        $mode = ($entry->session_mode ?? SessionMode::Online) === SessionMode::Online
            ? __('Online')
            : __('In-person').($entry->location === null ? '' : ' · '.$entry->location->name.', '.$entry->location->city);

        return $mode.($entry->contextOrg === null ? '' : ' · '.$entry->contextOrg->name);
    }
}
