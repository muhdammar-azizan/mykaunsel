<x-portal-layout>
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Schedule') }}</h1>

    <div class="mt-5">
        <div class="space-y-6">
            @if (session('status'))
                <div role="status" class="rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 font-semibold text-brand-red">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section aria-labelledby="open-time" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="open-time" class="text-[20px] font-bold tracking-tight text-brand-ink">{{ __('Open a time') }}</h3>
                <p class="text-sm text-brand-ink/65">{{ __('Times are Malaysia time. Clients can book a time up to the booking window ahead, and not sooner than the minimum notice.') }}</p>

                @if (count($contexts) === 0)
                    <p class="mt-3 text-brand-ink/65">{{ __('You do not work in any active organization yet, so there is nowhere to open a time.') }}</p>
                @else
                    <form method="POST" action="{{ route('counselor.planner.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 items-end">
                        @csrf
                        <div>
                            <label for="context" class="block text-sm font-medium text-brand-ink/70">{{ __('Where you work') }}</label>
                            <select id="context" name="context" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                @foreach ($contexts as $c)
                                    <option value="{{ $c['id'] }}" @selected((int) old('context', session('current_org_id')) === $c['id'])>{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-brand-ink/70">{{ __('Date') }}</label>
                            <input id="date" name="date" type="date" min="{{ $today }}" value="{{ old('date') }}" required class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                        </div>
                        <div>
                            <label for="start" class="block text-sm font-medium text-brand-ink/70">{{ __('Start time') }}</label>
                            <input id="start" name="start" type="time" step="300" value="{{ old('start') }}" required class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                        </div>
                        <div>
                            <label for="duration" class="block text-sm font-medium text-brand-ink/70">{{ __('Length') }}</label>
                            <select id="duration" name="duration" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                @foreach ($durations as $d)
                                    <option value="{{ $d }}" @selected((int) old('duration', 60) === $d)>{{ $d }} {{ __('min') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="mode" class="block text-sm font-medium text-brand-ink/70">{{ __('Type') }}</label>
                            <select id="mode" name="mode" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                <option value="online" @selected(old('mode', 'online') === 'online')>{{ __('Online') }}</option>
                                <option value="physical" @selected(old('mode') === 'physical')>{{ __('In-person') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-brand-ink/70">{{ __('Place (in-person only)') }}</label>
                            <select id="location" name="location" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($contexts as $c)
                                    @foreach ($c['locations'] as $l)
                                        <option value="{{ $l['id'] }}" @selected((int) old('location') === $l['id'])>{{ $c['name'] }}: {{ $l['name'] }}, {{ $l['city'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <button class="min-h-[44px] px-4 rounded-md bg-brand-blue text-white text-sm font-medium">{{ __('Open time') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section aria-labelledby="weekly" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="weekly" class="text-[20px] font-bold tracking-tight text-brand-ink">{{ __('Weekly hours') }}</h3>
                <p class="text-sm text-brand-ink/65">{{ __('Say once when you usually see clients, then open the coming weeks in one click. Weekly hours are only a pattern: nothing is bookable until you open the weeks.') }}</p>

                @if (count($lines))
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($lines as $l)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
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
                            <label for="weeks" class="block text-sm font-medium text-brand-ink/70">{{ __('Weeks to open') }}</label>
                            <select id="weeks" name="weeks" class="mt-1 block min-h-[44px] rounded-md border-brand-ink/20">
                                @for ($w = 1; $w <= $maxWeeks; $w++)
                                    <option value="{{ $w }}" @selected($w === 4)>{{ $w }}</option>
                                @endfor
                            </select>
                        </div>
                        <button class="min-h-[44px] px-4 rounded-md bg-brand-blue text-white text-sm font-medium">{{ __('Open the next weeks') }}</button>
                    </form>
                @else
                    <p class="mt-3 text-brand-ink/65">{{ __('No weekly hours yet.') }}</p>
                @endif

                @if (count($contexts))
                    <form method="POST" action="{{ route('counselor.planner.hours.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 items-end">
                        @csrf
                        <div>
                            <label for="h-context" class="block text-sm font-medium text-brand-ink/70">{{ __('Where you work') }}</label>
                            <select id="h-context" name="context" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                @foreach ($contexts as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-weekday" class="block text-sm font-medium text-brand-ink/70">{{ __('Day') }}</label>
                            <select id="h-weekday" name="weekday" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                @foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $n => $name)
                                    <option value="{{ $n }}">{{ __($name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-duration" class="block text-sm font-medium text-brand-ink/70">{{ __('Length of each time') }}</label>
                            <select id="h-duration" name="duration" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                @foreach ($durations as $d)
                                    <option value="{{ $d }}" @selected($d === 60)>{{ $d }} {{ __('min') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-from" class="block text-sm font-medium text-brand-ink/70">{{ __('From') }}</label>
                            <input id="h-from" name="from" type="time" step="300" required class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                        </div>
                        <div>
                            <label for="h-to" class="block text-sm font-medium text-brand-ink/70">{{ __('Until') }}</label>
                            <input id="h-to" name="to" type="time" step="300" required class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                        </div>
                        <div>
                            <label for="h-mode" class="block text-sm font-medium text-brand-ink/70">{{ __('Type') }}</label>
                            <select id="h-mode" name="mode" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                <option value="online">{{ __('Online') }}</option>
                                <option value="physical">{{ __('In-person') }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="h-location" class="block text-sm font-medium text-brand-ink/70">{{ __('Place (in-person only)') }}</label>
                            <select id="h-location" name="location" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($contexts as $c)
                                    @foreach ($c['locations'] as $loc)
                                        <option value="{{ $loc['id'] }}">{{ $c['name'] }}: {{ $loc['name'] }}, {{ $loc['city'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button class="min-h-[44px] px-4 rounded-md bg-brand-blue text-white text-sm font-medium">{{ __('Add to weekly hours') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section aria-labelledby="blocks" class="rounded-xl border border-[#E4E2DE] bg-white p-6">
                <h3 id="blocks" class="text-[20px] font-bold tracking-tight text-brand-ink">{{ __('Blocked times') }}</h3>
                <p class="text-sm text-brand-ink/65">{{ __('Leave, a holiday or a private appointment. Times open for booking inside a block can not be booked until you remove it. A session that is already booked must be cancelled first. Only you see the note.') }}</p>

                <form method="POST" action="{{ route('counselor.planner.blocks.store') }}" class="mt-4 grid items-end gap-3 sm:grid-cols-3" x-data="{ allDay: {{ (old('from') ? old('all_day') : true) ? 'true' : 'false' }} }">
                    @csrf
                    <div>
                        <label for="b-from" class="block text-sm font-medium text-brand-ink/70">{{ __('First day') }}</label>
                        <input id="b-from" name="from" type="date" min="{{ $today }}" value="{{ old('from') }}" required class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                    </div>
                    <div>
                        <label for="b-to" class="block text-sm font-medium text-brand-ink/70">{{ __('Last day (leave empty for one day)') }}</label>
                        <input id="b-to" name="to" type="date" min="{{ $today }}" value="{{ old('to') }}" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                    </div>
                    <div class="flex min-h-[44px] items-center gap-2">
                        <input id="b-all" name="all_day" type="checkbox" value="1" x-model="allDay" class="h-5 w-5 rounded border-brand-ink/30 text-brand-blue">
                        <label for="b-all" class="text-sm font-medium text-brand-ink/70">{{ __('All day') }}</label>
                    </div>
                    <div x-show="!allDay" x-cloak>
                        <label for="b-start" class="block text-sm font-medium text-brand-ink/70">{{ __('From') }}</label>
                        <input id="b-start" name="start" type="time" step="300" value="{{ old('start') }}" :required="!allDay" :disabled="allDay" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                    </div>
                    <div x-show="!allDay" x-cloak>
                        <label for="b-end" class="block text-sm font-medium text-brand-ink/70">{{ __('Until') }}</label>
                        <input id="b-end" name="end" type="time" step="300" value="{{ old('end') }}" :required="!allDay" :disabled="allDay" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="b-title" class="block text-sm font-medium text-brand-ink/70">{{ __('Note for yourself (optional)') }}</label>
                        <input id="b-title" name="title" type="text" maxlength="100" value="{{ old('title') }}" placeholder="{{ __('Annual leave') }}" class="mt-1 block w-full min-h-[44px] rounded-md border-brand-ink/20">
                    </div>
                    <div>
                        <button class="min-h-[44px] rounded-md bg-brand-blue px-5 text-sm font-semibold text-white">{{ __('Block this time') }}</button>
                    </div>
                </form>

                <ul class="mt-5 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                    @forelse ($blocks as $b)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium text-brand-ink">{{ $b['day_label'] }} · {{ $b['time'] }}</p>
                                @if ($b['title'])<p class="text-sm text-brand-ink/65">{{ $b['title'] }}</p>@endif
                            </div>
                            <form method="POST" action="{{ route('counselor.planner.blocks.destroy', $b['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button class="min-h-[44px] rounded-md border border-brand-ink/20 px-4 text-sm font-medium text-brand-ink">{{ __('Remove block') }}</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-3 text-brand-ink/65">{{ __('No blocked times.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section aria-labelledby="your-times">
                <h3 id="your-times" class="text-[20px] font-bold tracking-tight text-brand-ink">{{ __('Your times') }}</h3>

                <div class="mt-3 space-y-5">
                    @forelse (collect($times)->groupBy('day') as $day => $rows)
                        <div>
                            <h4 class="text-sm font-semibold text-brand-ink/70">{{ $rows->first()['day_label'] }}</h4>
                            <div class="mt-2 space-y-2">
                                @foreach ($rows as $t)
                                    <div class="rounded-xl border border-[#E4E2DE] bg-white p-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="font-medium text-brand-ink">{{ $t['time'] }} <span class="text-sm font-normal text-brand-ink/65">({{ $t['minutes'] }} {{ __('min') }})</span></p>
                                            <p class="text-sm text-brand-ink/65">{{ $t['mode'] === 'online' ? __('Online') : __('In-person').($t['place'] ? ' · '.$t['place'] : '') }}@if (count($contexts) > 1 && $t['context']) · {{ $t['context'] }}@endif</p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            @if (! $t['offered'])
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-brand-yellow/25 text-[#6B4C0C]" title="{{ __('After your organization\'s subscription ends. Clients can not see it until the subscription is renewed.') }}">{{ __('Hidden: plan ended') }}</span>
                                            @endif
                                            @if ($t['hidden'])
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-brand-yellow/25 text-[#6B4C0C]" title="{{ $t['hidden'] === 'block' ? __('This time overlaps a day or time you blocked. Clients can not see it. Remove the block and it shows again.') : __('This time is too close to a booked session (the buffer between sessions). Clients can not see it.') }}">{{ $t['hidden'] === 'block' ? __('Hidden from clients: blocked time') : __('Hidden from clients: too close to a session') }}</span>
                                            @endif
                                            @if ($t['booked'])
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800">{{ __('Booked') }}</span>
                                            @else
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800">{{ __('Open') }}</span>
                                            @endif
                                            @if ($t['removable'])
                                                <form method="POST" action="{{ route('counselor.planner.destroy', $t['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="min-h-[44px] px-3 rounded-md border border-brand-ink/20 text-sm text-brand-ink">{{ __('Remove') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-[#E4E2DE] bg-white p-6 text-brand-ink/65">{{ __('No times opened yet.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-portal-layout>
