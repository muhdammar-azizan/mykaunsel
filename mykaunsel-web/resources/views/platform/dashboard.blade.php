@php
    // Everything on this page comes from DashboardService (see docs/DASHBOARD.md); nothing is counted or hard-coded here.
    $greeting = $data['greeting'];
    $banner = $data['banner'];
    $attention = $data['attention'];
    $stats = $data['stats'];
    $approvals = $data['approvals'];
    $counts = $data['cards'];
    $complaints = $counts['active_complaints'];

    $cards = [
        ['label' => 'Pending organizations', 'count' => $counts['pending_organizations']['count'], 'url' => $counts['pending_organizations']['url'], 'color' => 'blue', 'image' => 'images/attention/orgs.jpg', 'hint' => 'Organizations waiting for your approval.'],
        ['label' => 'Pending counselors', 'count' => $counts['pending_counselors']['count'], 'url' => $counts['pending_counselors']['url'], 'color' => 'yellow', 'image' => 'images/attention/counselors.jpg', 'hint' => 'Counselor credentials to verify.'],
        ['label' => 'Active complaints', 'count' => $complaints['count'], 'url' => $complaints['url'], 'color' => 'red', 'image' => 'images/attention/complaints.jpg', 'hint' => $complaints['open'].' open, '.$complaints['under_review'].' under review.'],
        ['label' => 'Skipped DNS verification', 'count' => $counts['skipped_dns']['count'], 'url' => $counts['skipped_dns']['url'], 'color' => 'ink', 'image' => 'images/attention/dns.jpg', 'hint' => 'Domains not technically verified yet.'],
    ];

    $donut = [
        ['label' => 'Active members', 'value' => $stats['members'], 'color' => '#0A4F88'],
        ['label' => 'Sessions this month', 'value' => $stats['sessions']['count'], 'color' => '#3A92D0'],
        ['label' => 'Counselors', 'value' => $stats['counselors'], 'color' => '#E3AF39'],
        ['label' => 'Organizations', 'value' => $stats['organizations'], 'color' => '#D62839'],
    ];

    $suggestions = [
        ['label' => 'Review organizations', 'url' => route('platform.organizations', ['tab' => 'pending']), 'icon' => 'M3 4h18v17H3zM3 9h18M9 4v17'],
        ['label' => 'Review counselors', 'url' => route('platform.counselors', ['tab' => 'pending']), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ['label' => 'View complaints', 'url' => route('platform.complaints', ['tab' => 'open']), 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        ['label' => 'Audit log', 'url' => route('platform.audit-log'), 'icon' => 'M9 12h6M9 16h6M9 8h6M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'],
        ['label' => 'LKM directory', 'url' => route('platform.lkm'), 'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'],
    ];

    $toneClass = ['red' => 'font-semibold text-brand-red', 'amber' => 'font-semibold text-[#B98417]', 'ok' => 'text-brand-ink/50'];
@endphp

<x-platform-layout title="Dashboard">

    {{-- Maintenance mode has its own bar on every Platform page (the layout). This one is for sign-ups that are closed. --}}
    @if ($banner['state'] === 'registration')
        <a href="{{ $banner['url'] }}" role="status" class="mt-1 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-xl bg-brand-yellow/30 px-4 py-2 text-[13px] font-semibold text-[#4A3405] transition hover:bg-brand-yellow/45">
            <span>{{ $banner['all_closed'] ? 'All sign-ups are closed.' : 'Sign-ups closed: '.implode(', ', $banner['closed']).'.' }}</span>
            @php
                $waiting = collect($banner['waiting'])->filter(fn ($n) => $n > 0)->map(fn ($n, $role) => $n.' '.\Illuminate\Support\Str::plural($role, $n))->values();
            @endphp
            @if ($waiting->isNotEmpty())
                <span>{{ $waiting->implode(', ') }} on the waitlist.</span>
            @endif
            <span class="underline">Manage</span>
        </a>
    @endif

    <x-platform.ai-hero :title="$greeting['text'].', '.$greeting['first_name']" subtitle="Here's what's happening across the platform today" :suggestions="$suggestions" />

    <section class="mt-10" aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="flex items-center gap-2.5 text-[18px] font-bold">
            Needs your attention
            <span class="grid h-[22px] min-w-[22px] place-items-center rounded-full bg-brand-blue px-1.5 text-[11px] font-bold text-white">{{ array_sum(array_column($cards, 'count')) }}</span>
        </h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <x-platform.attention-card :label="$card['label']" :count="$card['count']" :url="$card['url']" :image="$card['image']" :hint="$card['hint']" :color="$card['color']" />
            @endforeach
        </div>
    </section>

    <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <x-platform.panel title="Waiting for review">
            <div class="mt-3 flex flex-col">
                @forelse ($attention['items'] as $item)
                    <div class="flex items-center justify-between gap-3 py-3 {{ $loop->first ? '' : 'border-t border-brand-ink/[.07]' }}">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium">{{ $item['text'] }}</p>
                            @if ($item['detail'])
                                <p class="text-[12px] {{ $toneClass[$item['tone']] }}">{{ $item['detail'] }}</p>
                            @endif
                        </div>
                        <a href="{{ $item['url'] }}" class="shrink-0 text-[12.5px] font-semibold text-brand-blue hover:underline">Review →</a>
                    </div>
                @empty
                    <p class="py-8 text-center text-[13px] text-brand-ink/50">Nothing is waiting for review. You're all caught up.</p>
                @endforelse

                @if ($attention['more'] > 0)
                    <p class="border-t border-brand-ink/[.07] pt-3 text-[12.5px] text-brand-ink/65">
                        {{ $attention['more'] }} more waiting. View all:
                        @foreach ($attention['more_by_destination'] as $destination)
                            <a href="{{ $destination['url'] }}" class="font-semibold text-brand-blue hover:underline">{{ $destination['label'] }} ({{ $destination['count'] }})</a>@if (! $loop->last)<span aria-hidden="true"> · </span>@endif
                        @endforeach
                    </p>
                @endif
            </div>
        </x-platform.panel>

        <x-platform.panel title="Platform stats">
            <p class="text-[12px] text-brand-ink/50">Overall</p>
            <div class="mt-5">
                <x-platform.stats-donut :segments="$donut" />
            </div>
            @unless ($stats['sessions']['has_data'])
                <p class="mt-3 text-center text-[12px] text-brand-ink/55">No session data yet</p>
            @endunless
        </x-platform.panel>
    </div>

    <x-platform.panel class="mt-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-[14px] font-bold">Recent approvals</h2>
            <a href="{{ $approvals['view_all_url'] }}" class="text-[13px] font-semibold text-brand-blue hover:underline">View all in Audit Log →</a>
        </div>
        <x-platform.table :headings="['Name', 'Type', 'By', 'Date']" empty="No approvals yet. They appear here as they are made.">
            @foreach ($approvals['rows'] as $row)
                <tr class="border-t border-brand-ink/[.07]">
                    <td class="px-4 py-3 text-[13px] font-medium">{{ $row['name'] }}</td>
                    <td class="px-3 py-3 text-[12.5px] text-brand-ink/60">{{ $row['type'] }}</td>
                    <td class="px-3 py-3 text-[12.5px] text-brand-ink/60">{{ $row['by'] }}</td>
                    <td class="px-3 py-3 text-[12.5px] text-brand-ink/50">{{ $row['at']->format('j M Y') }}</td>
                </tr>
            @endforeach
        </x-platform.table>
    </x-platform.panel>

</x-platform-layout>
