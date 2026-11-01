<x-portal-layout title="Reports: use">
    @include('organizations.reports._header')

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        @include('organizations.reports._set', ['title' => 'Sessions over time', 'about' => 'Sessions in each '.$period['by'].' of the period, whatever happened to them.', 'set' => $report['series']])
        @include('organizations.reports._set', ['title' => 'How sessions ended', 'about' => 'Completed and no-show are marked by the counselor after the session. "Still ahead, or not marked yet" has not been decided.', 'set' => $report['status']])
        @include('organizations.reports._set', ['title' => 'Online or in person', 'about' => 'Sessions that were not cancelled.', 'set' => $report['mode']])
        @include('organizations.reports._set', ['title' => 'Busiest days', 'about' => 'The weekday of sessions that were not cancelled.', 'set' => $report['weekdays']])
        @include('organizations.reports._set', ['title' => 'Busiest hours', 'about' => 'The hour a session starts, for sessions that were not cancelled (Malaysia time).', 'set' => $report['hours']])
        @include('organizations.reports._set', ['title' => 'How far ahead people book', 'about' => 'How many days before the session it was booked, for sessions that were not cancelled.', 'set' => $report['lead']])
    </div>

    <h2 class="mt-8 text-[20px] font-bold tracking-tight">Time requests</h2>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">Members can ask a counselor for a time inside the counselor's weekly hours. These are the requests made in this period.</p>
    <div class="mt-4 grid gap-5 xl:grid-cols-2">
        @include('organizations.reports._set', ['title' => 'How requests ended', 'about' => 'A request closes by itself if the counselor does not answer in time.', 'set' => $report['requests']['ended']])
        <section class="rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="answered-title">
            <h2 id="answered-title" class="text-[18px] font-bold tracking-tight">How fast they are answered</h2>
            <p class="mt-1 text-[13px] text-brand-ink/60">Requests the counselor approved or declined, and how many of those within 24 hours.</p>
            <p class="mt-3 text-[14px]">Answered: <span class="font-semibold">{{ $report['requests']['answered'] }}</span></p>
            <p class="mt-1 text-[14px]">Answered within 24 hours: <span class="font-semibold">{{ $report['requests']['answered_fast'] ?? 'not shown (fewer than '.$minGroup.' answered)' }}</span></p>
        </section>
    </div>
</x-portal-layout>
