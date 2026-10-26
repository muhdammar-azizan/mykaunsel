@php
    $tabUrl = fn (string $key) => route('platform.complaints', $filters->toQuery(['tab', 'page'], $key === 'open' ? [] : ['tab' => $key]));

    $emptyText = [
        'all' => ['No complaints yet', 'Complaints from clients will show up here.'],
        'open' => ['No open complaints', 'New complaints from clients will show up here.'],
        'under_review' => ['Nothing under review', 'Complaints you are looking into will be listed here.'],
        'resolved' => ['No resolved complaints', 'Complaints that have been dealt with are listed here.'],
        'dismissed' => ['No dismissed complaints', 'Complaints that were set aside are listed here.'],
    ][$tab];

    $date = fn ($value) => $value->copy()->timezone($timezone)->format('j M Y');
    $toneClass = ['ok' => 'bg-brand-ink/5 text-brand-ink/65', 'amber' => 'bg-brand-yellow/20 text-[#8A6210]', 'red' => 'bg-brand-red/10 text-brand-red'];
    $selectClass = 'rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-9 text-[14px] focus:border-brand-blue focus:ring-brand-blue';
@endphp

<x-platform-layout title="Complaints">
    <x-platform.page-header title="Complaints" subtitle="Complaints clients made about counselors. Only Platform Admins can read them." />

    <x-platform.status-tabs
        :tabs="collect($tabs)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $counts[$key], 'url' => $tabUrl($key)])->values()->all()"
        :active="$tab"
        label="Complaint status" />

    @if ($filters->errors()->any())
        <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/15 px-5 py-3 text-[13px] text-[#6B4C0C]" role="alert">
            <p class="font-bold">Some filters in the link were not valid and were ignored:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($filters->errors()->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" action="{{ route('platform.complaints') }}" role="search" aria-label="Filter complaints" class="mt-5 flex flex-wrap items-center gap-3">
        @if ($tab !== 'open')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        @if ($filters->get('counselor'))<input type="hidden" name="counselor" value="{{ $filters->get('counselor') }}">@endif

        <label class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <span class="sr-only">Search complaints</span>
            <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-brand-ink/40" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $filters->get('q') }}" maxlength="100" placeholder="Counselor, KB number, reporter or reference (#12)"
                   class="w-full rounded-full border border-brand-ink/10 bg-white py-2.5 pl-10 pr-4 text-[14px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
        </label>

        <label>
            <span class="sr-only">Organization</span>
            <select name="organization" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">Any organization</option>
                @foreach ($organizations as $id => $name)
                    <option value="{{ $id }}" @selected((int) $filters->get('organization') === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-[13px] text-brand-ink/65">
            <span>Filed from</span>
            <input type="date" name="from" value="{{ $filters->get('from') }}" class="rounded-full border border-brand-ink/10 bg-white px-3.5 py-2 text-[13.5px] focus:border-brand-blue focus:ring-brand-blue">
        </label>
        <label class="flex items-center gap-2 text-[13px] text-brand-ink/65">
            <span>to</span>
            <input type="date" name="to" value="{{ $filters->get('to') }}" class="rounded-full border border-brand-ink/10 bg-white px-3.5 py-2 text-[13.5px] focus:border-brand-blue focus:ring-brand-blue">
        </label>

        <label>
            <span class="sr-only">Order</span>
            <select name="sort" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                @foreach ($sorts as $key => $text)
                    <option value="{{ $key }}" @selected($filters->sort() === $key)>Order: {{ $text }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="rounded-full bg-brand-blue px-5 py-2.5 text-[14px] font-semibold text-white transition hover:bg-brand-deep">Search</button>

        @if ($filters->isFiltering())
            <a href="{{ route('platform.complaints', array_filter(['tab' => $tab === 'open' ? null : $tab, 'sort' => $filters->sort() === 'urgent' ? null : $filters->sort()])) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Clear filters</a>
        @endif
    </form>

    @if ($filters->get('counselor'))
        <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-brand-blue/10 px-3.5 py-1.5 text-[12.5px] font-semibold text-brand-blue">
            Showing complaints about one counselor
            <a href="{{ route('platform.counselors.show', (int) $filters->get('counselor')) }}" class="underline">Open their page</a>
        </p>
    @endif

    <div class="mt-5">
        @if ($complaints->isEmpty())
            <x-platform.panel class="py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                </span>
                @if ($filters->isFiltering())
                    <p class="mt-4 text-[15px] font-bold">No complaints match your search</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Try a different name or organization — or check the other tabs.</p>
                @else
                    <p class="mt-4 text-[15px] font-bold">{{ $emptyText[0] }}</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">{{ $emptyText[1] }}</p>
                @endif
            </x-platform.panel>
        @else
            <x-platform.table :headings="['Complaint', 'Counselor', 'Reporter', 'Session', 'Filed', 'Status', '']">
                @foreach ($complaints as $complaint)
                    @php
                        $open = $complaint->isOpenForReview();
                        $days = $complaint->waitingDays();
                        $org = $complaint->booking?->organization;
                    @endphp
                    <tr class="border-t border-brand-ink/[.07] transition hover:bg-brand-blue/[.03]">
                        <td class="px-4 py-3.5 text-[14px] font-semibold">#{{ $complaint->id }}</td>
                        <td class="px-4 py-3.5">
                            <p class="text-[14px] font-semibold">{{ $complaint->counselor->name }}</p>
                            @if ($complaint->counselorProfile)<p class="font-mono text-[11.5px] text-brand-ink/50">{{ $complaint->counselorProfile->kb_number }}</p>@endif
                        </td>
                        <td class="px-4 py-3.5 text-[13.5px]">{{ $complaint->reporterName() }}</td>
                        <td class="px-4 py-3.5 text-[13px] text-brand-ink/70">
                            @if ($org){{ $org->org_type === \App\Enums\OrgType::Platform ? 'Independent practice' : $org->name }}@else<span class="text-brand-ink/40">No session linked</span>@endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-[13px]">
                            {{ $date($complaint->created_at) }}
                            @if ($open)<span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $toneClass[$complaint->waitingTone()] }}">{{ $days === 0 ? 'today' : $days.' '.\Illuminate\Support\Str::plural('day', $days) }}</span>@endif
                        </td>
                        <td class="px-4 py-3.5"><x-platform.complaint-status-pill :status="$complaint->status" /></td>
                        <td class="px-4 py-3.5 text-right"><a href="{{ route('platform.complaints.show', $complaint) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">{{ $open ? 'Review' : 'View' }} →</a></td>
                    </tr>
                @endforeach
            </x-platform.table>
        @endif

        @if ($complaints->hasPages())
            <div class="mt-5">{{ $complaints->links() }}</div>
        @endif
    </div>
</x-platform-layout>
