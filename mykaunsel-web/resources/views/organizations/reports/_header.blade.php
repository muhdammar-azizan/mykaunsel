{{-- The top of every report page: the title, the three pages, the period, and the one rule about small numbers. --}}
@php
    $tabs = ['overview' => ['Overview', route('org.reports')], 'use' => ['Use', route('org.reports.use')], 'members' => ['Members', route('org.reports.members')], 'counselors' => ['Counselors', route('org.reports.counselors')], 'quality' => ['Quality', route('org.reports.quality')]];
@endphp

<h1 class="text-[24px] font-bold leading-tight tracking-tight">Reports</h1>
<p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">How {{ $organization->name }} uses MyKaunsel, as counts only. No name of a member or a counselor ever appears here, and nothing comes from the chat or from a counselor's notes.</p>

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
    <nav class="flex flex-wrap gap-2" aria-label="Report pages">
        @foreach ($tabs as $key => [$label, $url])
            <a href="{{ $url }}?range={{ $period['key'] }}" @if ($tab === $key) aria-current="page" @endif
               class="inline-flex min-h-[36px] items-center rounded-full border px-4 text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue {{ $tab === $key ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 hover:bg-brand-ink/5' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" class="flex items-center gap-2">
        <label for="range" class="text-[13px] font-semibold text-brand-ink/70">Period</label>
        <select id="range" name="range" onchange="this.form.submit()" class="min-h-[40px] rounded-md border border-brand-ink/20 px-3 text-[14px]">
            @foreach ($period['choices'] as $key => $label)<option value="{{ $key }}" @selected($period['key'] === $key)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="min-h-[40px] rounded-md bg-brand-blue px-4 text-[14px] font-semibold text-white">Show</button></noscript>
    </form>
</div>

<p class="mt-3 text-[13px] text-brand-ink/65">{{ $period['from']->format('j M Y') }} to {{ $period['to']->format('j M Y') }} (Malaysia time). A number from 1 to {{ $minGroup - 1 }} is shown as "fewer than {{ $minGroup }}" so nobody can be recognised; when that would give a number away, one more is marked "hidden".</p>
