@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');

    $cards = [
        ['label' => 'Pending organizations', 'count' => $counts['pending_orgs'], 'route' => 'platform.organizations', 'bg' => 'bg-brand-blue', 'text' => 'text-white', 'icon' => 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18zM6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M10 6h4M10 10h4M10 14h4'],
        ['label' => 'Pending counselors', 'count' => $counts['pending_counselors'], 'route' => 'platform.counselors', 'bg' => 'bg-brand-yellow', 'text' => 'text-brand-ink', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ['label' => 'Active complaints', 'count' => $counts['active_complaints'], 'route' => 'platform.complaints', 'bg' => 'bg-brand-red', 'text' => 'text-white', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        ['label' => 'Skipped DNS verification', 'count' => $counts['skipped_dns'], 'route' => 'platform.organizations', 'bg' => 'bg-brand-ink', 'text' => 'text-white', 'icon' => 'M20 13c0 5-3.5 7.5-7.5 8.5a1.2 1.2 0 0 1-1 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.79 17 5 19 5a1 1 0 0 1 1 1zM9 12l2 2 4-4'],
    ];

    $statTiles = [
        ['label' => 'Organizations', 'value' => $stats['organizations'], 'dot' => 'bg-brand-blue'],
        ['label' => 'Counselors', 'value' => $stats['counselors'], 'dot' => 'bg-brand-yellow'],
        ['label' => 'Active members', 'value' => $stats['members'], 'dot' => 'bg-brand-red'],
        ['label' => 'Bookings this month', 'value' => $stats['bookings'], 'dot' => 'bg-brand-ink'],
    ];
@endphp

<x-platform-layout title="Dashboard">

    {{-- HERO --}}
    <div class="text-center">
        <h1 class="text-[28px] font-extrabold leading-tight tracking-tightest">{{ $greeting }}, {{ $firstName }}</h1>
        <p class="mt-1 text-[14px] text-brand-ink/55">Here's what's happening across the platform today</p>

        <div class="mt-6 flex flex-wrap justify-center gap-2.5" role="group" aria-label="Quick actions">
            <a href="{{ route('platform.organizations') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-brand-ink/15 bg-cream px-5 text-[13px] font-semibold shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">Review organizations</a>
            <a href="{{ route('platform.counselors') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-brand-ink/15 bg-cream px-5 text-[13px] font-semibold shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">Review counselors</a>
            <a href="{{ route('platform.complaints') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-brand-ink/15 bg-cream px-5 text-[13px] font-semibold shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">View complaints</a>
        </div>
    </div>

    {{-- NEEDS YOUR ATTENTION (cards) --}}
    <section class="mt-10" aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="text-[16px] font-bold">Needs your attention</h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <a href="{{ route($card['route']) }}" class="group flex h-[190px] flex-col rounded-[22px] p-5 {{ $card['bg'] }} {{ $card['text'] }} shadow-[0_10px_24px_-14px_rgba(7,58,102,.5)] transition hover:-translate-y-1 hover:shadow-[0_18px_30px_-14px_rgba(7,58,102,.55)]">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/90 text-brand-ink">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}"/></svg>
                    </span>
                    <span class="mt-3 text-[36px] font-extrabold leading-none">{{ $card['count'] }}</span>
                    <span class="mt-auto flex items-center justify-between text-[14px] font-bold leading-tight">
                        {{ $card['label'] }}
                        <span class="translate-x-[-4px] opacity-0 transition group-hover:translate-x-0 group-hover:opacity-100" aria-hidden="true">→</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- LIST + STATS --}}
    <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <section class="rounded-[20px] border border-brand-ink/10 p-6" aria-labelledby="queue-heading">
            <h2 id="queue-heading" class="text-[14px] font-bold">Waiting for review</h2>
            <div class="mt-3 flex flex-col">
                @forelse ($attention as $item)
                    <div class="flex items-center justify-between gap-3 py-3 {{ $loop->first ? '' : 'border-t border-brand-ink/[.07]' }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $item['kind'] === 'organization' ? 'bg-brand-blue/12 text-brand-blue' : 'bg-brand-yellow/20 text-brand-ink' }}">
                                @if ($item['kind'] === 'organization')
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h6v11H4zM14 6h6v15h-6zM2 21h20"/></svg>
                                @else
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-medium">{{ $item['text'] }}</span>
                                <span class="block text-[12px] {{ $item['days'] > 7 ? 'font-semibold text-brand-red' : 'text-brand-ink/50' }}">
                                    Waiting {{ $item['days'] }} {{ \Illuminate\Support\Str::plural('day', $item['days']) }}
                                </span>
                            </span>
                        </div>
                        <a href="{{ $item['url'] }}" class="shrink-0 text-[12.5px] font-semibold text-brand-blue hover:underline">Review →</a>
                    </div>
                @empty
                    <p class="py-8 text-center text-[13px] text-brand-ink/50">Nothing is waiting for review. You're all caught up.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-[20px] border border-brand-ink/10 p-6" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="text-[14px] font-bold">Platform stats</h2>
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ($statTiles as $tile)
                    <div class="rounded-2xl bg-cream p-4">
                        <p class="flex items-center gap-2 text-[12px] text-brand-ink/60">
                            <span class="h-2 w-2 rounded-full {{ $tile['dot'] }}"></span>{{ $tile['label'] }}
                        </p>
                        <p class="mt-1.5 text-[26px] font-extrabold leading-none">{{ number_format($tile['value']) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- RECENT APPROVALS --}}
    <section class="mt-6 rounded-[20px] border border-brand-ink/10 p-6" aria-labelledby="approvals-heading">
        <h2 id="approvals-heading" class="text-[14px] font-bold">Recent approvals</h2>
        <div class="mt-3 overflow-x-auto rounded-xl border border-brand-ink/10">
            <table class="w-full min-w-[420px] text-left">
                <thead class="bg-cream">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">Name</th>
                        <th scope="col" class="px-3 py-3 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">Type</th>
                        <th scope="col" class="px-3 py-3 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentApprovals as $row)
                        <tr class="border-t border-brand-ink/[.07]">
                            <td class="px-4 py-3 text-[13px] font-medium">{{ $row['name'] }}</td>
                            <td class="px-3 py-3 text-[12.5px] text-brand-ink/60">{{ $row['type'] }}</td>
                            <td class="px-3 py-3 text-[12.5px] text-brand-ink/50">{{ $row['date']->format('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-[13px] text-brand-ink/50">No approvals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</x-platform-layout>
