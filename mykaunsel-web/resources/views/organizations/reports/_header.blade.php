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

    <form method="GET" class="flex flex-wrap items-center gap-3 print:hidden">
        <label for="range" class="text-[13px] font-semibold text-brand-ink/70">Period</label>
        <select id="range" name="range" onchange="this.form.submit()" class="min-h-[40px] rounded-md border border-brand-ink/20 px-3 text-[14px]">
            @foreach ($period['choices'] as $key => $label)<option value="{{ $key }}" @selected($period['key'] === $key)>{{ $label }}</option>@endforeach
        </select>
        @if ($comparable)
            <label class="inline-flex min-h-[40px] items-center gap-2 text-[13px] font-semibold text-brand-ink/70"><input type="checkbox" name="compare" value="1" @checked($previous !== null) onchange="this.form.submit()" class="h-4 w-4 rounded border-brand-ink/30 text-brand-blue">Compare with the period before</label>
        @endif
        <noscript><button class="min-h-[40px] rounded-md bg-brand-blue px-4 text-[14px] font-semibold text-white">Show</button></noscript>
    </form>
</div>

<div class="mt-3 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <p class="text-[13px] text-brand-ink/65">Download this page for the period, or print it to keep it as a PDF.</p>
    <div class="flex gap-2">
        <a href="{{ route('org.reports.export', ['page' => $tab, 'range' => $period['key']]) }}" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5">Download CSV</a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5">Print or save as PDF</button>
    </div>
</div>

<p class="mt-3 text-[13px] text-brand-ink/65">{{ $period['from']->format('j M Y') }} to {{ $period['to']->format('j M Y') }} (Malaysia time)@if ($previous !== null), compared with {{ $previousPeriod['from']->format('j M Y') }} to {{ $previousPeriod['to']->format('j M Y') }}@endif. A number from 1 to {{ $minGroup - 1 }} is shown as "fewer than {{ $minGroup }}" so nobody can be recognised; when that would give a number away, one more is marked "hidden".</p>
