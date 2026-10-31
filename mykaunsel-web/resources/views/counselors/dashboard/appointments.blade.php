<x-portal-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Temujanji') }}</h2>
    </x-slot>

    @php
        $badge = [
            'confirmed' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'no_show' => 'bg-amber-100 text-amber-800',
            'cancelled' => 'bg-gray-100 text-gray-700',
        ];
        $label = ['confirmed' => __('Confirmed'), 'completed' => __('Completed'), 'no_show' => __('No-show'), 'cancelled' => __('Cancelled')];
    @endphp

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

            {{-- Mode B: people who asked for a time inside the weekly hours. Approving books the session. --}}
            <section aria-labelledby="requests">
                <h3 id="requests" class="text-lg font-semibold text-gray-900">{{ __('Time requests') }}</h3>
                <p class="text-sm text-gray-600">{{ __('A request closes by itself if you do not answer in time. Approving books the session.') }}</p>

                <div class="mt-3 space-y-3">
                    @forelse ($requests as $r)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <p class="font-medium text-gray-900">{{ $r['client'] }}</p>
                            <p class="text-sm text-gray-600">{{ $r['date'] }}, {{ $r['time'] }} ({{ __('Malaysia time') }}) · {{ $r['minutes'] }} {{ __('min') }} · {{ $r['mode'] === 'online' ? __('Online') : __('In-person').($r['place'] ? ' · '.$r['place'] : '') }}</p>
                            @if ($r['note'])
                                <p class="mt-2 text-sm text-gray-800"><span class="font-medium">{{ __('Their note') }}:</span> {{ $r['note'] }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">{{ __('Answer by') }} {{ $r['until'] }}</p>

                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <form method="POST" action="{{ route('counselor.requests.approve', $r['id']) }}">
                                    @csrf
                                    <button class="min-h-[44px] px-4 rounded-md bg-green-600 text-white text-sm font-medium">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('counselor.requests.decline', $r['id']) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label for="reason-{{ $r['id'] }}" class="block text-xs font-medium text-gray-700">{{ __('Reason (optional, they will see it)') }}</label>
                                        <input id="reason-{{ $r['id'] }}" name="reason" type="text" maxlength="300" class="mt-1 block min-h-[44px] rounded-md border-gray-300">
                                    </div>
                                    <button class="min-h-[44px] px-4 rounded-md border border-gray-300 text-gray-800 text-sm font-medium">{{ __('Decline') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-600">{{ __('No requests waiting.') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Ended sessions that still need an outcome: only the counselor says what happened. --}}
            <section aria-labelledby="to-mark">
                <h3 id="to-mark" class="text-lg font-semibold text-gray-900">{{ __('Needs marking') }}</h3>
                <p class="text-sm text-gray-600">{{ __('These sessions have ended. Say what happened: nothing is marked automatically.') }}</p>

                <div class="mt-3 space-y-3">
                    @forelse ($to_mark as $s)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ $s['client'] }}</p>
                                <p class="text-sm text-gray-600">{{ $s['date'] }}, {{ $s['time'] }} ({{ __('Malaysia time') }}) · {{ $s['mode'] === 'online' ? __('Online') : __('In-person') }}</p>
                            </div>
                            <form method="POST" action="{{ route('counselor.appointments.outcome', $s['id']) }}" class="flex gap-2">
                                @csrf
                                <button name="outcome" value="completed" class="min-h-[44px] px-4 rounded-md bg-green-600 text-white text-sm font-medium">{{ __('Mark completed') }}</button>
                                <button name="outcome" value="no_show" class="min-h-[44px] px-4 rounded-md border border-gray-300 text-gray-800 text-sm font-medium">{{ __('Mark no-show') }}</button>
                            </form>
                        </div>
                    @empty
                        <p class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-600">{{ __('Nothing to mark.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="upcoming">
                <h3 id="upcoming" class="text-lg font-semibold text-gray-900">{{ __('Upcoming') }}</h3>

                <div class="mt-3 space-y-3">
                    @forelse ($upcoming as $s)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $s['client'] }}</p>
                                    <p class="text-sm text-gray-600">{{ $s['date'] }}, {{ $s['time'] }} ({{ __('Malaysia time') }}) · {{ $s['minutes'] }} {{ __('min') }}</p>
                                    <p class="text-sm text-gray-600">{{ $s['mode'] === 'online' ? __('Online') : __('In-person').($s['place'] ? ' · '.$s['place'] : '') }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge['confirmed'] }}">{{ $label['confirmed'] }}</span>
                            </div>

                            @if ($s['mode'] === 'online')
                                <form method="POST" action="{{ route('counselor.appointments.meeting', $s['id']) }}" class="mt-4 grid gap-3 sm:grid-cols-[10rem_1fr_auto] items-end">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label for="provider-{{ $s['id'] }}" class="block text-sm font-medium text-gray-700">{{ __('Meeting app') }}</label>
                                        <select id="provider-{{ $s['id'] }}" name="meeting_provider" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                            @foreach ($providers as $p)
                                                <option value="{{ $p }}" @selected(($s['meeting_provider'] ?? 'Google Meet') === $p)>{{ $p }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="url-{{ $s['id'] }}" class="block text-sm font-medium text-gray-700">{{ __('Meeting link (https)') }}</label>
                                        <input id="url-{{ $s['id'] }}" name="meeting_url" type="url" maxlength="255" value="{{ $s['meeting_url'] }}" placeholder="https://" class="mt-1 block w-full min-h-[44px] rounded-md border-gray-300">
                                    </div>
                                    <button class="min-h-[44px] px-4 rounded-md bg-indigo-600 text-white text-sm font-medium">{{ __('Save link') }}</button>
                                    <p class="sm:col-span-3 text-xs text-gray-500">{{ $s['meeting_url'] ? __('The client sees this link 15 minutes before the session. Leave it empty and save to remove it.') : __('No link added yet. The client sees "link not added yet" until you add one.') }}</p>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-600">{{ __('No upcoming sessions.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="past">
                <h3 id="past" class="text-lg font-semibold text-gray-900">{{ __('Past') }}</h3>

                <div class="mt-3 space-y-3">
                    @forelse ($past as $s)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900">{{ $s['client'] }}</p>
                                <p class="text-sm text-gray-600">{{ $s['date'] }}, {{ $s['time'] }} ({{ __('Malaysia time') }}) · {{ $s['mode'] === 'online' ? __('Online') : __('In-person') }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[$s['status']] ?? $badge['cancelled'] }}">{{ $label[$s['status']] ?? $s['status'] }}</span>
                        </div>
                    @empty
                        <p class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-600">{{ __('No past sessions yet.') }}</p>
                    @endforelse
                </div>

                <div class="mt-4">{{ $past->links() }}</div>
            </section>
        </div>
    </div>
</x-portal-layout>
