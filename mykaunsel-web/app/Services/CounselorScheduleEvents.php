<?php

namespace App\Services;

use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use App\Support\Subscription;
use Carbon\CarbonImmutable;

/**
 * What the counselor's Schedule calendar shows for a stretch of days, as the list of events the calendar asks for
 * (docs/AVAILABILITY.md, "The Schedule calendar"). READ ONLY: nothing here changes a time.
 *
 * - Open times (with the reason when clients can not see one: a blocked time, too close to a booked session, or the organization's plan ended),
 *   booked sessions and blocked times, of every context together: a counselor is one person.
 * - The weekly hours as a light background, so the pattern can be seen behind the real times.
 * - A booked session is only "Booked session" with a link to its page in Bookings. The client's name is never in an event.
 * - Every time is Malaysian time and is sent WITH its offset, so the calendar draws it in Malaysia time whatever the browser's zone is.
 * - Each event carries the actions that already exist (remove a time that never had a booking, remove a block, open a booking), as plain
 *   links and forms for the page to show when it is clicked. Moving by dragging is not part of this step.
 * - Only the counselor's own entries.
 */
class CounselorScheduleEvents
{
    /** The longest stretch one request may ask for (a month view plus a little). */
    public const MAX_DAYS = 45;

    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return list<array<string, mixed>> */
    public function between(User $counselor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = $this->availability->timezone();
        $first = $from->setTimezone($tz)->startOfDay();
        $last = $to->setTimezone($tz)->startOfDay();

        $entries = CalendarEntry::query()
            ->where('counselor_user_id', $counselor->getKey())
            ->whereIn('entry_type', [CalendarEntryType::AvailableSlot->value, CalendarEntryType::PersonalBlock->value, CalendarEntryType::RequestedSession->value])
            ->whereDate('entry_date', '>=', $first->format('Y-m-d'))->whereDate('entry_date', '<=', $last->format('Y-m-d'))
            ->with(['location:id,name,city', 'contextOrg:id,name,org_type,subscription_ends_at'])
            ->with(['bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')->select(['id', 'public_id', 'calendar_entry_id', 'status'])])
            ->withExists('bookings as has_history')
            ->withExists(['bookings as has_active' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderBy('entry_date')->orderBy('start_time')->orderBy('id')->get();

        $free = $entries->filter(fn (CalendarEntry $e) => $e->entry_type === CalendarEntryType::AvailableSlot && $e->is_available && ! $e->has_active);
        $hidden = $this->availability->hiddenReasons($free);

        $events = $entries->map(fn (CalendarEntry $e) => $this->event($e, $hidden))->values()->all();

        return [...$this->workingHours($counselor, $first, $last), ...$events];
    }

    /**
     * @param  array<int, string>  $hidden  slot id => block|session
     * @return array<string, mixed>
     */
    private function event(CalendarEntry $entry, array $hidden): array
    {
        $start = $this->availability->startOf($entry);
        $minutes = (int) $entry->duration_minutes;
        $end = $start->addMinutes($minutes);
        $allDay = $minutes >= 1440;
        $when = $allDay ? __('All day') : $start->format('g:i A').' – '.$end->format('g:i A');

        $base = [
            'start' => $allDay ? $start->format('Y-m-d') : $start->toIso8601String(),
            'end' => $allDay ? $start->addDay()->format('Y-m-d') : $end->toIso8601String(),
            'allDay' => $allDay,
            'editable' => false,
        ];
        $props = ['when' => $start->format('l, j F Y').', '.$when, 'minutes' => $minutes, 'actions' => []];

        if ($entry->entry_type === CalendarEntryType::PersonalBlock) {
            return $base + [
                'id' => 'block:'.$entry->public_id,
                'title' => (string) $entry->title !== '' ? (string) $entry->title : __('Blocked'),
                'classNames' => ['sc-block'],
                'extendedProps' => $props + [
                    'kind' => 'block', 'kindLabel' => __('Blocked time'), 'detail' => (string) $entry->title,
                    'hidden' => null, 'hiddenLabel' => null, 'hiddenExplain' => null,
                    'actions' => [['label' => __('Remove this block'), 'method' => 'DELETE', 'url' => route('counselor.planner.blocks.destroy', $entry->public_id)]],
                ],
            ];
        }

        $booking = $entry->bookings->first();

        if ($entry->entry_type === CalendarEntryType::RequestedSession || ! $entry->is_available || $booking !== null) {
            return $base + [
                'id' => 'session:'.$entry->public_id,
                'title' => __('Booked session'),
                'classNames' => ['sc-session'],
                'extendedProps' => $props + [
                    'kind' => 'session', 'kindLabel' => __('Booked session'), 'detail' => $this->detail($entry),
                    'hidden' => null, 'hiddenLabel' => null, 'hiddenExplain' => null,
                    // A booked session is not moved or removed here: the counselor cancels it, with a reason, on its own page.
                    'actions' => $booking === null ? [] : [['label' => __('Open this booking'), 'method' => 'GET', 'url' => route('counselor.appointments.show', $booking->public_id)]],
                ],
            ];
        }

        $reason = $hidden[$entry->id] ?? null;
        $offered = Subscription::allows($entry->contextOrg, $start->format('Y-m-d'));
        $label = ! $offered ? __('Hidden: plan ended') : match ($reason) {
            AvailabilityService::HIDDEN_BLOCK => __('Hidden from clients: blocked time'),
            AvailabilityService::HIDDEN_SESSION => __('Hidden from clients: too close to a session'),
            default => null,
        };
        $explain = ! $offered
            ? __('After your organization\'s subscription ends. Clients can not see it until the subscription is renewed.')
            : match ($reason) {
                AvailabilityService::HIDDEN_BLOCK => __('This time overlaps a day or time you blocked. Clients can not see it. Remove the block and it shows again.'),
                AvailabilityService::HIDDEN_SESSION => __('This time is too close to a booked session (the buffer between sessions). Clients can not see it.'),
                default => null,
            };

        return $base + [
            'id' => 'slot:'.$entry->public_id,
            'title' => __('Open'),
            'classNames' => $label === null ? ['sc-open'] : ['sc-open', 'sc-hidden'],
            'extendedProps' => $props + [
                'kind' => 'open', 'kindLabel' => __('Open for booking'), 'detail' => $this->detail($entry),
                'hidden' => $label === null ? null : ($offered ? $reason : 'plan'), 'hiddenLabel' => $label, 'hiddenExplain' => $explain,
                'actions' => $entry->has_history ? [] : [['label' => __('Remove this time'), 'method' => 'DELETE', 'url' => route('counselor.planner.destroy', $entry->public_id)]],
            ],
        ];
    }

    /**
     * The weekly hours as a light background behind the real times. Not clickable.
     *
     * @return list<array<string, mixed>>
     */
    private function workingHours(User $counselor, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $lines = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->get();
        $events = [];

        if ($lines->isEmpty()) {
            return $events;
        }

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            foreach ($lines->where('weekday', $day->isoWeekday()) as $line) {
                $from = $this->minutes((string) $line->start_time);
                $to = $this->minutes((string) $line->end_time);
                $start = $day->setTime(intdiv($from, 60), $from % 60);
                $end = $day->setTime(intdiv($to, 60), $to % 60);

                $events[] = [
                    'id' => 'hours:'.$line->public_id.':'.$day->format('Y-m-d'),
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'display' => 'background',
                    'classNames' => ['sc-hours'],
                    'extendedProps' => ['kind' => 'hours'],
                ];
            }
        }

        return $events;
    }

    private function detail(CalendarEntry $entry): string
    {
        $mode = ($entry->session_mode ?? SessionMode::Online) === SessionMode::Online
            ? __('Online')
            : __('In-person').($entry->location === null ? '' : ' · '.$entry->location->name.', '.$entry->location->city);

        return $mode.($entry->contextOrg === null ? '' : ' · '.$entry->contextOrg->name);
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
