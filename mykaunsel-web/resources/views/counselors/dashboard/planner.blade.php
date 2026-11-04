@php
    $field = 'mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20';
    $label = 'block text-sm font-medium text-brand-ink/70';
    $primary = 'min-h-[44px] px-4 rounded-md bg-brand-blue text-white text-sm font-medium';
    $summary = 'flex min-h-[52px] cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-[17px] font-bold tracking-tight text-brand-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue [&::-webkit-details-marker]:hidden';
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    // Which tool opens: the one whose form was just sent (and failed), otherwise "Open a time".
    $openWeekly = (bool) old('weekday');
    $openBlock = (bool) old('from') && ! $openWeekly;
    $openTime = ! $openWeekly && ! $openBlock;
@endphp

<x-portal-layout title="Schedule">
    <style>
        /* The calendar: the same hues as the rest of the portal. Every state is also written in words inside the item, never colour alone. */
        #schedule-calendar { --fc-border-color: #E4E2DE; --fc-today-bg-color: #F3F8FD; --fc-now-indicator-color: #D62839; --fc-event-border-color: transparent; font-size: 13px; }
        #schedule-calendar .fc-toolbar { flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        #schedule-calendar .fc-toolbar-title { font-size: 17px; font-weight: 700; color: #1B2A4E; }
        #schedule-calendar .fc-button { min-height: 40px; padding: 0 14px; text-transform: none; font-weight: 600; background: #fff; border: 1px solid rgb(27 42 78 / .25); color: #1B2A4E; }
        #schedule-calendar .fc-button:hover { background: rgb(27 42 78 / .06); }
        #schedule-calendar .fc-button:focus-visible { outline: 2px solid #0A4F88; outline-offset: 2px; box-shadow: none; }
        #schedule-calendar .fc-button-primary:not(:disabled).fc-button-active { background: #0A4F88; border-color: #0A4F88; color: #fff; }
        #schedule-calendar .fc-button:disabled { opacity: .5; }
        #schedule-calendar .fc-col-header-cell-cushion, #schedule-calendar .fc-timegrid-slot-label-cushion { color: #1B2A4E; font-weight: 600; }
        #schedule-calendar .fc-timegrid-slot { height: 28px; }
        #schedule-calendar .fc-event { border-radius: 6px; cursor: pointer; overflow: hidden; }
        #schedule-calendar .fc-event:focus-visible { outline: 2px solid #0A4F88; outline-offset: 1px; }
        #schedule-calendar .sc-body { padding: 2px 5px; line-height: 1.25; }
        #schedule-calendar .sc-time { font-size: 11px; font-weight: 600; }
        #schedule-calendar .sc-title { font-weight: 700; }
        #schedule-calendar .sc-flag { margin-top: 2px; font-size: 11px; font-weight: 600; }
        #schedule-calendar .sc-open { background: #DCFCE7; color: #14532D; border-left: 4px solid #16A34A; }
        #schedule-calendar .sc-open.sc-hidden { background: #FEF3C7; color: #6B4C0C; border-left: 4px dashed #B45309; }
        #schedule-calendar .sc-session { background: #DBEAFE; color: #1E3A8A; border-left: 4px solid #1D4ED8; }
        #schedule-calendar .sc-block { background: repeating-linear-gradient(135deg, #EDEBE7, #EDEBE7 6px, #F6F5F2 6px, #F6F5F2 12px); color: #3F3F46; border-left: 4px solid #71717A; }
        #schedule-calendar .sc-hours.fc-bg-event { background: #EEF5FB; opacity: 1; }
        .sc-swatch { display: inline-block; height: 12px; width: 18px; border-radius: 3px; vertical-align: -1px; margin-right: 6px; }
        @media (prefers-reduced-motion: reduce) { #schedule-calendar * { transition: none !important; } }
        dialog#schedule-event::backdrop { background: rgb(27 42 78 / .45); }
    </style>

    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Schedule') }}</h1>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 font-semibold text-brand-red">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mt-5 grid gap-5 xl:grid-cols-[400px_minmax(0,1fr)] xl:items-start">
        {{-- ---- The tools (left on a wide screen; under the calendar on a narrow one) ------------------------------------------------------- --}}
        <div class="order-2 space-y-3 xl:order-1">
            <details class="{{ $card }}" @if ($openTime) open @endif>
                <summary class="{{ $summary }}">{{ __('Open a time') }}</summary>
                <div class="border-t border-[#EEEDEA] p-5">
                    <p class="text-sm text-brand-ink/65">{{ __('Times are Malaysia time. Clients can book a time up to the booking window ahead, and not sooner than the minimum notice.') }}</p>

                    @if (count($contexts) === 0)
                        <p class="mt-3 text-brand-ink/65">{{ __('You do not work in any active organization yet, so there is nowhere to open a time.') }}</p>
                    @else
                        <form method="POST" action="{{ route('counselor.planner.store') }}" class="mt-4 grid items-end gap-3 sm:grid-cols-3 xl:grid-cols-1">
                            @csrf
                            <div>
                                <label for="context" class="{{ $label }}">{{ __('Where you work') }}</label>
                                <select id="context" name="context" class="{{ $field }}">
                                    @foreach ($contexts as $c)
                                        <option value="{{ $c['id'] }}" @selected((int) old('context', session('current_org_id')) === $c['id'])>{{ $c['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="date" class="{{ $label }}">{{ __('Date') }}</label>
                                <input id="date" name="date" type="date" min="{{ $today }}" value="{{ old('date') }}" required class="{{ $field }}">
                            </div>
                            <div>
                                <label for="start" class="{{ $label }}">{{ __('Start time') }}</label>
                                <input id="start" name="start" type="time" step="300" value="{{ old('start') }}" required class="{{ $field }}">
                            </div>
                            <div>
                                <label for="duration" class="{{ $label }}">{{ __('Length') }}</label>
                                <select id="duration" name="duration" class="{{ $field }}">
                                    @foreach ($durations as $d)
                                        <option value="{{ $d }}" @selected((int) old('duration', 60) === $d)>{{ $d }} {{ __('min') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="mode" class="{{ $label }}">{{ __('Type') }}</label>
                                <select id="mode" name="mode" class="{{ $field }}">
                                    <option value="online" @selected(old('mode', 'online') === 'online')>{{ __('Online') }}</option>
                                    <option value="physical" @selected(old('mode') === 'physical')>{{ __('In-person') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="location" class="{{ $label }}">{{ __('Place (in-person only)') }}</label>
                                <select id="location" name="location" class="{{ $field }}">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($contexts as $c)
                                        @foreach ($c['locations'] as $l)
                                            <option value="{{ $l['id'] }}" @selected((int) old('location') === $l['id'])>{{ $c['name'] }}: {{ $l['name'] }}, {{ $l['city'] }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3 xl:col-span-1">
                                <button class="{{ $primary }}">{{ __('Open time') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </details>

            <details class="{{ $card }}" @if ($openWeekly) open @endif>
                <summary class="{{ $summary }}">{{ __('Weekly hours') }}</summary>
                <div class="border-t border-[#EEEDEA] p-5">
                    <p class="text-sm text-brand-ink/65">{{ __('Say once when you usually see clients, then open the coming weeks in one click. Weekly hours are only a pattern: nothing is bookable until you open the weeks. They show as a light background in the calendar.') }}</p>

                    @if (count($lines))
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($lines as $l)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span class="text-brand-ink">{{ $l['weekday_label'] }} {{ $l['window'] }} <span class="text-sm text-brand-ink/65">({{ $l['minutes'] }} {{ __('min') }} · {{ $l['mode'] === 'online' ? __('Online') : __('In-person').($l['place'] ? ' · '.$l['place'] : '') }}@if (count($contexts) > 1 && $l['context']) · {{ $l['context'] }}@endif)</span></span>
                                    <form method="POST" action="{{ route('counselor.planner.hours.destroy', $l['id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="min-h-[44px] px-3 rounded-md border border-brand-ink/20 text-sm text-brand-ink">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('counselor.planner.hours.fill') }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="weeks" class="{{ $label }}">{{ __('Weeks to open') }}</label>
                                <select id="weeks" name="weeks" class="mt-1 block min-h-[44px] rounded-md border-brand-ink/20">
                                    @for ($w = 1; $w <= $maxWeeks; $w++)
                                        <option value="{{ $w }}" @selected($w === 4)>{{ $w }}</option>
                                    @endfor
                                </select>
                            </div>
                            <button class="{{ $primary }}">{{ __('Open the next weeks') }}</button>
                        </form>
                    @else
                        <p class="mt-3 text-brand-ink/65">{{ __('No weekly hours yet.') }}</p>
                    @endif

                    @if (count($contexts))
                        <form method="POST" action="{{ route('counselor.planner.hours.store') }}" class="mt-4 grid items-end gap-3 sm:grid-cols-3 xl:grid-cols-1">
                            @csrf
                            <div>
                                <label for="h-context" class="{{ $label }}">{{ __('Where you work') }}</label>
                                <select id="h-context" name="context" class="{{ $field }}">
                                    @foreach ($contexts as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="h-weekday" class="{{ $label }}">{{ __('Day') }}</label>
                                <select id="h-weekday" name="weekday" class="{{ $field }}">
                                    @foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $n => $name)
                                        <option value="{{ $n }}">{{ __($name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="h-duration" class="{{ $label }}">{{ __('Length of each time') }}</label>
                                <select id="h-duration" name="duration" class="{{ $field }}">
                                    @foreach ($durations as $d)
                                        <option value="{{ $d }}" @selected($d === 60)>{{ $d }} {{ __('min') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="h-from" class="{{ $label }}">{{ __('From') }}</label>
                                <input id="h-from" name="from" type="time" step="300" required class="{{ $field }}">
                            </div>
                            <div>
                                <label for="h-to" class="{{ $label }}">{{ __('Until') }}</label>
                                <input id="h-to" name="to" type="time" step="300" required class="{{ $field }}">
                            </div>
                            <div>
                                <label for="h-mode" class="{{ $label }}">{{ __('Type') }}</label>
                                <select id="h-mode" name="mode" class="{{ $field }}">
                                    <option value="online">{{ __('Online') }}</option>
                                    <option value="physical">{{ __('In-person') }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2 xl:col-span-1">
                                <label for="h-location" class="{{ $label }}">{{ __('Place (in-person only)') }}</label>
                                <select id="h-location" name="location" class="{{ $field }}">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($contexts as $c)
                                        @foreach ($c['locations'] as $loc)
                                            <option value="{{ $loc['id'] }}">{{ $c['name'] }}: {{ $loc['name'] }}, {{ $loc['city'] }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button class="{{ $primary }}">{{ __('Add to weekly hours') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </details>

            <details class="{{ $card }}" @if ($openBlock) open @endif>
                <summary class="{{ $summary }}">{{ __('Block a time') }}</summary>
                <div class="border-t border-[#EEEDEA] p-5">
                    <p class="text-sm text-brand-ink/65">{{ __('Leave, a holiday or a private appointment. Times open for booking inside a block can not be booked until you remove it (click the block in the calendar). A session that is already booked must be cancelled first. Only you see the note.') }}</p>

                    <form method="POST" action="{{ route('counselor.planner.blocks.store') }}" class="mt-4 grid items-end gap-3 sm:grid-cols-3 xl:grid-cols-1" x-data="{ allDay: {{ (old('from') ? old('all_day') : true) ? 'true' : 'false' }} }">
                        @csrf
                        <div>
                            <label for="b-from" class="{{ $label }}">{{ __('First day') }}</label>
                            <input id="b-from" name="from" type="date" min="{{ $today }}" value="{{ old('from') }}" required class="{{ $field }}">
                        </div>
                        <div>
                            <label for="b-to" class="{{ $label }}">{{ __('Last day (leave empty for one day)') }}</label>
                            <input id="b-to" name="to" type="date" min="{{ $today }}" value="{{ old('to') }}" class="{{ $field }}">
                        </div>
                        <div class="flex min-h-[44px] items-center gap-2">
                            <input id="b-all" name="all_day" type="checkbox" value="1" x-model="allDay" class="h-5 w-5 rounded border-brand-ink/30 text-brand-blue">
                            <label for="b-all" class="{{ $label }}">{{ __('All day') }}</label>
                        </div>
                        <div x-show="!allDay" x-cloak>
                            <label for="b-start" class="{{ $label }}">{{ __('From') }}</label>
                            <input id="b-start" name="start" type="time" step="300" value="{{ old('start') }}" :required="!allDay" :disabled="allDay" class="{{ $field }}">
                        </div>
                        <div x-show="!allDay" x-cloak>
                            <label for="b-end" class="{{ $label }}">{{ __('Until') }}</label>
                            <input id="b-end" name="end" type="time" step="300" value="{{ old('end') }}" :required="!allDay" :disabled="allDay" class="{{ $field }}">
                        </div>
                        <div class="sm:col-span-2 xl:col-span-1">
                            <label for="b-title" class="{{ $label }}">{{ __('Note for yourself (optional)') }}</label>
                            <input id="b-title" name="title" type="text" maxlength="100" value="{{ old('title') }}" placeholder="{{ __('Annual leave') }}" class="{{ $field }}">
                        </div>
                        <div>
                            <button class="min-h-[44px] rounded-md bg-brand-blue px-5 text-sm font-semibold text-white">{{ __('Block this time') }}</button>
                        </div>
                    </form>
                </div>
            </details>
        </div>

        {{-- ---- The calendar ------------------------------------------------------------------------------------------------------------------- --}}
        <section class="order-1 xl:order-2 xl:sticky xl:top-0" aria-labelledby="calendar-title">
            <div class="{{ $card }} p-4">
                <h2 id="calendar-title" class="sr-only">{{ __('Your calendar') }}</h2>

                <ul class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-brand-ink/80" aria-label="{{ __('What the colours mean') }}">
                    <li><span class="sc-swatch" style="background:#DCFCE7;border-left:4px solid #16A34A" aria-hidden="true"></span>{{ __('Open for booking') }}</li>
                    <li><span class="sc-swatch" style="background:#DBEAFE;border-left:4px solid #1D4ED8" aria-hidden="true"></span>{{ __('Booked session') }}</li>
                    <li><span class="sc-swatch" style="background:#EDEBE7;border-left:4px solid #71717A" aria-hidden="true"></span>{{ __('Blocked time') }}</li>
                    <li><span class="sc-swatch" style="background:#FEF3C7;border-left:4px dashed #B45309" aria-hidden="true"></span>{{ __('Hidden from clients (the reason is written on it)') }}</li>
                    <li><span class="sc-swatch" style="background:#EEF5FB" aria-hidden="true"></span>{{ __('Your weekly hours') }}</li>
                </ul>

                <p id="schedule-message" role="alert" hidden class="mb-3 rounded-lg border border-brand-red/25 bg-brand-red/[.06] px-4 py-2 text-[13.5px] font-semibold text-brand-red"></p>

                <div id="schedule-calendar" class="h-[70vh] min-h-[520px] xl:h-[calc(100vh-19rem)]"
                     data-events-url="{{ route('counselor.schedule.events') }}"
                     data-timezone="{{ $timezone }}"
                     @if ($initialDate) data-initial-date="{{ $initialDate }}" @endif
                     data-error-text="{{ __('The calendar could not be loaded. Reload the page and try again.') }}"></div>

                <noscript><p class="mt-3 text-sm text-brand-ink/70">{{ __('The calendar needs JavaScript. You can still open, block and remove times with the tools next to it.') }}</p></noscript>
                <p class="mt-3 text-[12.5px] text-brand-ink/60">{{ __('Click a time to see its details and what you can do with it. Times are Malaysia time.') }}</p>
            </div>
        </section>
    </div>

    {{-- What is shown when an item of the calendar is clicked. A native dialog: Escape closes it, focus stays inside while it is open. --}}
    <dialog id="schedule-event" aria-labelledby="schedule-event-title"
            data-session-note="{{ __('A booked session is looked after on its own page: open it to cancel (with a reason for the client), add the meeting link or mark it.') }}"
            data-nothing-to-do="{{ __('There is nothing to change here: a time that has had a booking stays, because its history points to it.') }}"
            class="w-[min(92vw,440px)] rounded-2xl border border-[#E4E2DE] bg-white p-0 text-brand-ink shadow-2xl">
        <div class="p-6">
            <p class="text-[12.5px] font-semibold uppercase tracking-wide text-brand-ink/55" data-field="kind"></p>
            <h2 id="schedule-event-title" class="mt-1 text-[20px] font-bold tracking-tight" data-field="title"></h2>

            <dl class="mt-4 space-y-3 text-[14px]">
                <div data-row><dt class="text-brand-ink/60">{{ __('When') }}</dt><dd class="font-semibold" data-field="when"></dd></div>
                <div data-row><dt class="text-brand-ink/60">{{ __('Details') }}</dt><dd class="font-semibold" data-field="detail"></dd></div>
                <div data-row hidden><dt class="text-brand-ink/60">{{ __('Why clients can not see it') }}</dt><dd class="font-semibold text-[#6B4C0C]" data-field="hidden"></dd></div>
            </dl>

            <div class="mt-5 flex flex-wrap items-center gap-3" data-actions></div>

            <div class="mt-5 border-t border-[#EEEDEA] pt-4 text-right">
                <form method="dialog"><button class="min-h-[44px] rounded-md border border-brand-ink/20 px-5 text-sm font-semibold text-brand-ink">{{ __('Close') }}</button></form>
            </div>
        </div>

        <template data-form>
            <form method="POST" action="">
                <input type="hidden" name="_token" value="">
                <input type="hidden" name="_method" value="DELETE">
                <button class="min-h-[44px] rounded-md border border-brand-red/50 px-5 text-sm font-semibold text-brand-red hover:bg-brand-red/[.06]"></button>
            </form>
        </template>
        <template data-link>
            <a href="" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-sm font-semibold text-white"></a>
        </template>
    </dialog>

    @vite('resources/js/schedule-calendar.js')
</x-portal-layout>
