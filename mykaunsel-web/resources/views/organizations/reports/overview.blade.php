@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-5';
    $tiles = [
        ['Active members', number_format($report['members_active']), 'People who belong to your organization now.'],
        ['Waiting for approval', number_format($report['members_waiting']), 'People who used a reference code and wait for you.'],
        ['Left in this period', $report['members_left'], 'People who left or were removed.'.($previous !== null ? ' Before: '.$previous['members_left'].'.' : '')],
        ['Counselors', number_format($report['counselors']), 'Counselors who work for your organization.'],
    ];
@endphp

<x-portal-layout title="Reports">
    @include('organizations.reports._header')

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Members and counselors">
        @foreach ($tiles as [$label, $value, $hint])
            <div class="{{ $card }}">
                <p class="text-[13px] text-brand-ink/65">{{ $label }}</p>
                <p class="mt-1 text-[28px] font-bold leading-none tabular-nums">{{ $value }}</p>
                <p class="mt-2 text-[12.5px] text-brand-ink/55">{{ $hint }}</p>
            </div>
        @endforeach
    </section>

    <section class="{{ $card }} mt-5" aria-labelledby="sessions-title">
        <h2 id="sessions-title" class="text-[18px] font-bold tracking-tight">Sessions</h2>
        <p class="mt-1 text-[13px] text-brand-ink/60">Sessions with your counselors on days in this period, not counting cancelled ones, compared with the same number of days just before.</p>
        <p class="mt-3 text-[30px] font-bold leading-none tabular-nums">{{ $report['sessions'] }}</p>
        <p class="mt-2 text-[14px] text-brand-ink/70">Before: {{ $report['sessions_before'] }}@if ($report['change'] !== null) · {{ $report['change'] === 0 ? 'the same' : abs($report['change']).' '.($report['change'] > 0 ? 'more' : 'fewer') }}@endif</p>
    </section>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('org.reports.use', ['range' => $period['key']]) }}" class="{{ $card }} block transition hover:border-brand-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"><span class="text-[16px] font-bold">Use of the service</span><span class="mt-1 block text-[13px] text-brand-ink/65">Sessions by week, how they ended, online or in person, busiest days and hours, how far ahead people book, and time requests.</span></a>
        <a href="{{ route('org.reports.members', ['range' => $period['key']]) }}" class="{{ $card }} block transition hover:border-brand-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"><span class="text-[16px] font-bold">Members</span><span class="mt-1 block text-[13px] text-brand-ink/65">Who joined and who left, how people joined, and how many have booked at least once.</span></a>
    </div>
</x-portal-layout>
