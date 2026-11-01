<x-portal-layout title="Reports: members">
    @include('organizations.reports._header')

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        @include('organizations.reports._set', ['title' => 'New members', 'about' => 'People who joined in each '.$period['by'].' (not counting people still waiting for approval).', 'set' => $report['joined']])
        @include('organizations.reports._set', ['title' => 'Members who left', 'about' => 'People who left by themselves or were removed, in each '.$period['by'].'.', 'set' => $report['left']])
        @include('organizations.reports._set', ['title' => 'How new members joined', 'about' => 'The way each new member of this period came in.', 'set' => $report['sources']])

        <section class="rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="booked-title">
            <h2 id="booked-title" class="text-[18px] font-bold tracking-tight">Members who have booked</h2>
            <p class="mt-1 text-[13px] text-brand-ink/60">The share of your active members who have booked at least one session with your counselors (a cancelled booking does not count).</p>
            <p class="mt-3 text-[30px] font-bold leading-none tabular-nums">{{ $report['share_booked'] ?? 'Not shown' }}</p>
            <p class="mt-2 text-[13px] text-brand-ink/65">Active members: {{ $report['active'] }}@unless ($report['share_booked']) · shown from {{ $minGroup }} active members @endunless</p>
        </section>
    </div>
</x-portal-layout>
