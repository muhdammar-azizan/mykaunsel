<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Perancang') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div role="status" class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-md bg-red-50 p-4 text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section aria-labelledby="open-time" class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 id="open-time" class="text-lg font-semibold text-gray-900">{{ __('Open a time') }}</h3>
                <p class="text-sm text-gray-600">{{ __('Times are Malaysia time. Clients can book a time up to the booking window ahead, and not sooner than the minimum notice.') }}</p>

                @if (count($contexts) === 0)
                    <p class="mt-3 text-gray-600">{{ __('You do not work in any active organization yet, so there is nowhere to open a time.') }}</p>
                @else
                    <form method="POST" action="{{ route('counselor.planner.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 items-end">
                        @csrf
                        <div>
                            <label for="context" class="block text-sm font-medium text-gray-700">{{ __('Where you work') }}</label>
                            <select id="context" name="context" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                @foreach ($contexts as $c)
                                    <option value="{{ $c['id'] }}" @selected((int) old('context', session('current_org_id')) === $c['id'])>{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">{{ __('Date') }}</label>
                            <input id="date" name="date" type="date" min="{{ $today }}" value="{{ old('date') }}" required class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="start" class="block text-sm font-medium text-gray-700">{{ __('Start time') }}</label>
                            <input id="start" name="start" type="time" step="300" value="{{ old('start') }}" required class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="duration" class="block text-sm font-medium text-gray-700">{{ __('Length') }}</label>
                            <select id="duration" name="duration" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                @foreach ($durations as $d)
                                    <option value="{{ $d }}" @selected((int) old('duration', 60) === $d)>{{ $d }} {{ __('min') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="mode" class="block text-sm font-medium text-gray-700">{{ __('Type') }}</label>
                            <select id="mode" name="mode" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                <option value="online" @selected(old('mode', 'online') === 'online')>{{ __('Online') }}</option>
                                <option value="physical" @selected(old('mode') === 'physical')>{{ __('In-person') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">{{ __('Place (in-person only)') }}</label>
                            <select id="location" name="location" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($contexts as $c)
                                    @foreach ($c['locations'] as $l)
                                        <option value="{{ $l['id'] }}" @selected((int) old('location') === $l['id'])>{{ $c['name'] }}: {{ $l['name'] }}, {{ $l['city'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <button class="min-h-[44px] px-4 rounded-md bg-indigo-600 text-white text-sm font-medium">{{ __('Open time') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section aria-labelledby="weekly" class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 id="weekly" class="text-lg font-semibold text-gray-900">{{ __('Weekly hours') }}</h3>
                <p class="text-sm text-gray-600">{{ __('Say once when you usually see clients, then open the coming weeks in one click. Weekly hours are only a pattern: nothing is bookable until you open the weeks.') }}</p>

                @if (count($lines))
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($lines as $l)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-gray-900">{{ $l['weekday_label'] }} {{ $l['window'] }} <span class="text-sm text-gray-600">({{ $l['minutes'] }} {{ __('min') }} · {{ $l['mode'] === 'online' ? __('Online') : __('In-person').($l['place'] ? ' · '.$l['place'] : '') }}@if (count($contexts) > 1 && $l['context']) · {{ $l['context'] }}@endif)</span></span>
                                <form method="POST" action="{{ route('counselor.planner.hours.destroy', $l['id']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="min-h-[44px] px-3 rounded-md border border-gray-300 text-sm text-gray-800">{{ __('Remove') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('counselor.planner.hours.fill') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label for="weeks" class="block text-sm font-medium text-gray-700">{{ __('Weeks to open') }}</label>
                            <select id="weeks" name="weeks" class="mt-1 block min-h-[44px] rounded-md border-gray-300">
                                @for ($w = 1; $w <= $maxWeeks; $w++)
                                    <option value="{{ $w }}" @selected($w === 4)>{{ $w }}</option>
                                @endfor
                            </select>
                        </div>
                        <button class="min-h-[44px] px-4 rounded-md bg-indigo-600 text-white text-sm font-medium">{{ __('Open the next weeks') }}</button>
                    </form>
                @else
                    <p class="mt-3 text-gray-600">{{ __('No weekly hours yet.') }}</p>
                @endif

                @if (count($contexts))
                    <form method="POST" action="{{ route('counselor.planner.hours.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 items-end">
                        @csrf
                        <div>
                            <label for="h-context" class="block text-sm font-medium text-gray-700">{{ __('Where you work') }}</label>
                            <select id="h-context" name="context" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                @foreach ($contexts as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-weekday" class="block text-sm font-medium text-gray-700">{{ __('Day') }}</label>
                            <select id="h-weekday" name="weekday" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                @foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $n => $name)
                                    <option value="{{ $n }}">{{ __($name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-duration" class="block text-sm font-medium text-gray-700">{{ __('Length of each time') }}</label>
                            <select id="h-duration" name="duration" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                @foreach ($durations as $d)
                                    <option value="{{ $d }}" @selected($d === 60)>{{ $d }} {{ __('min') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="h-from" class="block text-sm font-medium text-gray-700">{{ __('From') }}</label>
                            <input id="h-from" name="from" type="time" step="300" required class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="h-to" class="block text-sm font-medium text-gray-700">{{ __('Until') }}</label>
                            <input id="h-to" name="to" type="time" step="300" required class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="h-mode" class="block text-sm font-medium text-gray-700">{{ __('Type') }}</label>
                            <select id="h-mode" name="mode" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                <option value="online">{{ __('Online') }}</option>
                                <option value="physical">{{ __('In-person') }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="h-location" class="block text-sm font-medium text-gray-700">{{ __('Place (in-person only)') }}</label>
                            <select id="h-location" name="location" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($contexts as $c)
                                    @foreach ($c['locations'] as $loc)
                                        <option value="{{ $loc['id'] }}">{{ $c['name'] }}: {{ $loc['name'] }}, {{ $loc['city'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button class="min-h-[44px] px-4 rounded-md bg-indigo-600 text-white text-sm font-medium">{{ __('Add to weekly hours') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            <section aria-labelledby="your-times">
                <h3 id="your-times" class="text-lg font-semibold text-gray-900">{{ __('Your times') }}</h3>

                <div class="mt-3 space-y-5">
                    @forelse (collect($times)->groupBy('day') as $day => $rows)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700">{{ $rows->first()['day_label'] }}</h4>
                            <div class="mt-2 space-y-2">
                                @foreach ($rows as $t)
                                    <div class="bg-white shadow-sm sm:rounded-lg p-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $t['time'] }} <span class="text-sm font-normal text-gray-600">({{ $t['minutes'] }} {{ __('min') }})</span></p>
                                            <p class="text-sm text-gray-600">{{ $t['mode'] === 'online' ? __('Online') : __('In-person').($t['place'] ? ' · '.$t['place'] : '') }}@if (count($contexts) > 1 && $t['context']) · {{ $t['context'] }}@endif</p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            @if ($t['booked'])
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800">{{ __('Booked') }}</span>
                                            @else
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800">{{ __('Open') }}</span>
                                            @endif
                                            @if ($t['removable'])
                                                <form method="POST" action="{{ route('counselor.planner.destroy', $t['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="min-h-[44px] px-3 rounded-md border border-gray-300 text-sm text-gray-800">{{ __('Remove') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-600">{{ __('No times opened yet.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
