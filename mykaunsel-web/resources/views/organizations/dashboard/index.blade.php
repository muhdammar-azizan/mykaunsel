@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    $stats = [
        ['label' => 'Active members', 'value' => number_format($members), 'url' => route('org.members')],
        ['label' => 'Waiting for your approval', 'value' => number_format($waiting), 'url' => route('org.members'), 'alert' => $waiting > 0],
        ['label' => 'Counselors', 'value' => number_format($counselors), 'url' => route('org.counselors')],
        ['label' => 'Sessions in '.$month_now, 'value' => $sessions_now['shown'], 'url' => null],
    ];
    $change = null;

    if ($sessions_now['count'] >= $min_group && $sessions_before['count'] >= $min_group) {
        $diff = $sessions_now['count'] - $sessions_before['count'];
        $change = $diff === 0 ? 'The same as in '.$month_before : abs($diff).' '.($diff > 0 ? 'more' : 'fewer').' than in '.$month_before;
    }
@endphp

<x-portal-layout title="Dashboard">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ $name }}</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">A quick look at your organization on MyKaunsel.</p>

    @foreach ($notices as $notice)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl px-5 py-4 text-[14px] font-semibold {{ $notice['tone'] === 'red' ? 'bg-brand-red/[.07] text-brand-red' : 'bg-[#FDF4E3] text-[#6B4C0C]' }}" role="status">
            <span class="flex items-start gap-3">
                <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                {{ $notice['text'] }}
            </span>
            @if ($notice['url'])<a href="{{ $notice['url'] }}" class="underline">{{ $notice['link'] }}</a>@endif
        </div>
    @endforeach

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="At a glance">
        @foreach ($stats as $stat)
            @if ($stat['url'])
                <a href="{{ $stat['url'] }}" class="{{ $card }} block p-5 transition hover:border-brand-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
            @else
                <div class="{{ $card }} p-5">
            @endif
                <p class="text-[13px] text-brand-ink/65">{{ $stat['label'] }}</p>
                <p class="mt-1 flex items-center gap-2 text-[30px] font-bold leading-none tabular-nums">
                    {{ $stat['value'] }}
                    @if (! empty($stat['alert']))<span class="h-2.5 w-2.5 rounded-full bg-brand-yellow" title="Needs your attention"></span><span class="sr-only">Needs your attention</span>@endif
                </p>
            @if ($stat['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="{{ $card }} p-6" aria-labelledby="use-title">
            <h2 id="use-title" class="text-[20px] font-bold tracking-tight">Use of the service</h2>
            <p class="mt-3 text-[15px]"><span class="font-semibold">{{ $sessions_now['shown'] }}</span> {{ $sessions_now['count'] === 1 ? 'session' : 'sessions' }} in {{ $month_now }}</p>
            <p class="mt-1 text-[14px] text-brand-ink/65">{{ $sessions_before['shown'] }} {{ $sessions_before['count'] === 1 ? 'session' : 'sessions' }} in {{ $month_before }}@if ($change) · {{ $change }}@endif</p>
            <p class="mt-4 text-[12.5px] text-brand-ink/55">Only counts, never names. A number under {{ $min_group }} is shown as "fewer than {{ $min_group }}" so nobody can be recognised. Cancelled sessions are not counted.</p>
        </section>

        <section class="{{ $card }} p-6" aria-labelledby="go-title">
            <h2 id="go-title" class="text-[20px] font-bold tracking-tight">Do next</h2>
            <ul class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                <li><a href="{{ route('org.members') }}" class="flex items-center justify-between py-3 font-semibold text-brand-blue hover:underline">Approve people and manage reference codes <span aria-hidden="true">&rarr;</span></a></li>
                <li><a href="{{ route('org.counselors') }}" class="flex items-center justify-between py-3 font-semibold text-brand-blue hover:underline">See your counselors <span aria-hidden="true">&rarr;</span></a></li>
                <li><a href="{{ route('org.settings') }}" class="flex items-center justify-between py-3 font-semibold text-brand-blue hover:underline">Organization settings <span aria-hidden="true">&rarr;</span></a></li>
            </ul>
        </section>
    </div>
</x-portal-layout>
