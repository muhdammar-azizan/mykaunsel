@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    $outline = 'inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $stats = [
        ['label' => 'Sessions today', 'value' => count($today), 'url' => route('counselor.appointments')],
        ['label' => 'Upcoming sessions', 'value' => $upcoming_count, 'url' => route('counselor.appointments')],
        ['label' => 'Waiting to be marked', 'value' => $to_mark, 'url' => route('counselor.appointments'), 'alert' => $to_mark > 0],
        ['label' => 'Time requests', 'value' => $requests_count, 'url' => route('counselor.requests'), 'alert' => $requests_count > 0],
    ];
@endphp

<x-portal-layout title="Dashboard">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Welcome, {{ $name }}</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">{{ $today_label }} (Malaysia time)</p>

    @foreach ($invitations as $invitation)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-blue/30 bg-brand-blue/[.06] px-5 py-4 text-[14px]" role="status">
            <span><span class="font-semibold">{{ $invitation['organization'] }}</span> invited you to be one of its counselors. MyKaunsel has already approved you; the organization will be able to see that you work for it.</span>
            <span class="flex gap-2">
                <form method="POST" action="{{ route('counselor.invitations.accept', $invitation['id']) }}">@csrf<button class="inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-4 text-[14px] font-semibold text-white">Accept</button></form>
                <form method="POST" action="{{ route('counselor.invitations.decline', $invitation['id']) }}">@csrf<button class="{{ $outline }}">Decline</button></form>
            </span>
        </div>
    @endforeach
    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->has('invitation'))
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">{{ $errors->first('invitation') }}</div>
    @endif

    @foreach ($notices as $notice)
        <div class="mt-4 flex items-start gap-3 rounded-xl px-5 py-4 text-[14px] font-semibold {{ $notice['tone'] === 'red' ? 'bg-brand-red/[.07] text-brand-red' : 'bg-[#FDF4E3] text-[#6B4C0C]' }}" role="status">
            <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            <span>{{ $notice['text'] }}@isset($notice['url']) <a href="{{ $notice['url'] }}" class="ml-1 underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">{{ $notice['label'] }}</a>@endisset</span>
        </div>
    @endforeach

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="At a glance">
        @foreach ($stats as $stat)
            <a href="{{ $stat['url'] }}" class="{{ $card }} block p-5 transition hover:border-brand-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
                <p class="text-[13px] text-brand-ink/65">{{ $stat['label'] }}</p>
                <p class="mt-1 flex items-center gap-2 text-[30px] font-bold leading-none tabular-nums">
                    {{ $stat['value'] }}
                    @if (! empty($stat['alert']))<span class="h-2.5 w-2.5 rounded-full bg-brand-yellow" title="Needs your attention"></span><span class="sr-only">Needs your attention</span>@endif
                </p>
            </a>
        @endforeach
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section class="{{ $card }}" aria-labelledby="today-title">
            <div class="flex items-center justify-between px-6 pt-5">
                <h2 id="today-title" class="text-[20px] font-bold tracking-tight">Today's sessions</h2>
                <a href="{{ route('counselor.appointments') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">View all bookings</a>
            </div>

            @if (count($today) > 0)
                <ul class="mt-3 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                    @foreach ($today as $row)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold">{{ $row['client'] }}</p>
                                <p class="text-[13px] text-brand-ink/65">{{ $row['time'] }} · {{ $row['minutes'] }} min · {{ $row['mode'] === 'online' ? 'Online' : 'In person'.($row['place'] ? ' · '.$row['place'] : '') }}</p>
                            </div>
                            @if ($row['ended'])
                                <a href="{{ route('counselor.appointments') }}" class="inline-flex rounded-full bg-brand-yellow/25 px-2.5 py-1 text-[12px] font-bold text-[#6B4C0C] hover:underline">Ended: needs marking</a>
                            @elseif ($row['mode'] === 'online' && $row['meeting_url'])
                                <a href="{{ $row['meeting_url'] }}" rel="noopener noreferrer" target="_blank" class="{{ $outline }}">Open meeting ({{ $row['meeting_provider'] }})</a>
                            @elseif ($row['mode'] === 'online')
                                <a href="{{ route('counselor.appointments') }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Add a meeting link</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state-today title="No sessions today.">
                    @if ($next)
                        <p class="mt-2 text-[14px]"><span class="font-semibold">Next session:</span> {{ $next['client'] }}, {{ $next['date'] }}, {{ $next['time'] }}</p>
                    @endif
                </x-empty-state-today>
            @endif
        </section>

        <div class="space-y-5">
            <section class="{{ $card }} p-6" aria-labelledby="requests-title">
                <div class="flex items-center justify-between">
                    <h2 id="requests-title" class="text-[20px] font-bold tracking-tight">Time requests</h2>
                    @if ($requests_count > 0)<span class="rounded-full bg-brand-yellow/25 px-2.5 py-0.5 text-[12px] font-bold text-[#6B4C0C]">{{ $requests_count }} waiting</span>@endif
                </div>

                @forelse ($requests as $r)
                    <div class="mt-4 border-t border-[#EEEDEA] pt-3 text-[14px]">
                        <p class="font-semibold">{{ $r['client'] }}</p>
                        <p class="text-[13px] text-brand-ink/65">{{ $r['date'] }}, {{ $r['time'] }}</p>
                        <p class="text-[12.5px] text-brand-ink/55">Answer by {{ $r['until'] }}</p>
                    </div>
                @empty
                    <p class="mt-3 text-[14px] text-brand-ink/65">No requests waiting.</p>
                @endforelse

                @if ($requests_count > 0)
                    <a href="{{ route('counselor.requests') }}" class="{{ $outline }} mt-4">Answer requests</a>
                @endif
            </section>

            <section class="{{ $card }} p-6" aria-labelledby="free-title">
                <h2 id="free-title" class="text-[20px] font-bold tracking-tight">Next free time</h2>
                @if ($next_free)
                    <p class="mt-3 text-[15px] font-semibold">{{ $next_free['day'] }}</p>
                    <p class="text-[14px] text-brand-ink/65">{{ $next_free['time'] }}</p>
                @else
                    <p class="mt-3 text-[14px] text-brand-ink/65">You have no free time open for booking.</p>
                @endif
                <a href="{{ route('counselor.planner') }}" class="{{ $outline }} mt-4">Open schedule</a>
            </section>

            <nav class="{{ $card }} p-6" aria-labelledby="links-title">
                <h2 id="links-title" class="text-[20px] font-bold tracking-tight">Quick links</h2>
                <ul class="mt-3 space-y-2 text-[14px] font-semibold">
                    <li><a href="{{ route('counselor.week') }}" class="text-brand-blue hover:underline">Calendar: this week</a></li>
                    <li><a href="{{ route('counselor.appointments') }}" class="text-brand-blue hover:underline">Bookings</a></li>
                    <li><a href="{{ route('counselor.requests') }}" class="text-brand-blue hover:underline">Time requests</a></li>
                    <li><a href="{{ route('counselor.organizations') }}" class="text-brand-blue hover:underline">Organizations</a></li>
                </ul>
            </nav>
        </div>
    </div>
</x-portal-layout>
