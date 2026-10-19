@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');

    $cards = [
        ['label' => 'Pending organizations', 'count' => $counts['pending_orgs'], 'route' => 'platform.organizations', 'color' => 'blue', 'image' => 'images/attention/orgs.jpg', 'hint' => 'Organizations waiting for your approval.'],
        ['label' => 'Pending counselors', 'count' => $counts['pending_counselors'], 'route' => 'platform.counselors', 'color' => 'yellow', 'image' => 'images/attention/counselors.jpg', 'hint' => 'Counselor credentials to verify.'],
        ['label' => 'Active complaints', 'count' => $counts['active_complaints'], 'route' => 'platform.complaints', 'color' => 'red', 'image' => 'images/attention/complaints.jpg', 'hint' => 'Open complaints that need a response.'],
        ['label' => 'Skipped DNS verification', 'count' => $counts['skipped_dns'], 'route' => 'platform.organizations', 'color' => 'ink', 'image' => 'images/attention/dns.jpg', 'hint' => 'Domains not technically verified yet.'],
    ];

    $statTiles = [
        ['label' => 'Organizations', 'value' => $stats['organizations'], 'color' => 'blue'],
        ['label' => 'Counselors', 'value' => $stats['counselors'], 'color' => 'yellow'],
        ['label' => 'Active members', 'value' => $stats['members'], 'color' => 'red'],
        ['label' => 'Bookings this month', 'value' => $stats['bookings'], 'color' => 'ink'],
    ];

    $suggestions = [
        ['label' => 'Review organizations', 'prompt' => 'organizations pending over 5 days', 'icon' => 'M3 4h18v17H3zM3 9h18M9 4v17'],
        ['label' => 'Review counselors', 'prompt' => 'counselors with expiring PA', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ['label' => 'View complaints', 'prompt' => 'active complaints this week', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        ['label' => 'Audit log', 'prompt' => 'audit log entries from this month', 'icon' => 'M9 12h6M9 16h6M9 8h6M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'],
        ['label' => 'LKM directory', 'prompt' => 'counselors in the LKM directory', 'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'],
    ];
@endphp

<x-platform-layout title="Dashboard">

    <x-platform.ai-hero :title="$greeting.', '.$firstName" subtitle="Here's what's happening across the platform today" :suggestions="$suggestions" />

    <section class="mt-10" aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="flex items-center gap-2.5 text-[18px] font-bold">
            Needs your attention
            <span class="grid h-[22px] min-w-[22px] place-items-center rounded-full bg-brand-blue px-1.5 text-[11px] font-bold text-white">{{ array_sum($counts) }}</span>
        </h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <x-platform.attention-card :label="$card['label']" :count="$card['count']" :route="$card['route']" :image="$card['image']" :hint="$card['hint']" :color="$card['color']" />
            @endforeach
        </div>
    </section>

    <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <x-platform.panel title="Waiting for review">
            <div class="mt-3 flex flex-col">
                @forelse ($attention as $item)
                    <div class="flex items-center justify-between gap-3 py-3 {{ $loop->first ? '' : 'border-t border-brand-ink/[.07]' }}">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium">{{ $item['text'] }}</p>
                            <p class="text-[12px] {{ $item['days'] > 7 ? 'font-semibold text-brand-red' : 'text-brand-ink/50' }}">
                                Waiting {{ $item['days'] }} {{ \Illuminate\Support\Str::plural('day', $item['days']) }}
                            </p>
                        </div>
                        <a href="{{ $item['url'] }}" class="shrink-0 text-[12.5px] font-semibold text-brand-blue hover:underline">Review →</a>
                    </div>
                @empty
                    <p class="py-8 text-center text-[13px] text-brand-ink/50">Nothing is waiting for review. You're all caught up.</p>
                @endforelse
            </div>
        </x-platform.panel>

        <x-platform.panel title="Platform stats">
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ($statTiles as $tile)
                    <x-platform.stat-tile :label="$tile['label']" :value="$tile['value']" :color="$tile['color']" />
                @endforeach
            </div>
        </x-platform.panel>
    </div>

    <x-platform.panel title="Recent approvals" class="mt-6">
        <x-platform.table :headings="['Name', 'Type', 'Date']" empty="No approvals yet.">
            @foreach ($recentApprovals as $row)
                <tr class="border-t border-brand-ink/[.07]">
                    <td class="px-4 py-3 text-[13px] font-medium">{{ $row['name'] }}</td>
                    <td class="px-3 py-3 text-[12.5px] text-brand-ink/60">{{ $row['type'] }}</td>
                    <td class="px-3 py-3 text-[12.5px] text-brand-ink/50">{{ $row['date']->format('j M Y') }}</td>
                </tr>
            @endforeach
        </x-platform.table>
    </x-platform.panel>

</x-platform-layout>
