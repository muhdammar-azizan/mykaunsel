@php
    $tabs = [
        'pending' => 'Pending',
        'skipped' => 'Skipped Verification',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $isWaitingTab = in_array($tab, ['pending', 'skipped'], true);

    // Keeps search / type / sort when switching tabs.
    $tabUrl = fn (string $key) => route('platform.organizations', array_filter([
        'tab' => $key === 'pending' ? null : $key,
        'q' => $search,
        'type' => $type,
        'sort' => request('sort'),
    ]));

    $emptyText = [
        'pending' => ['No applications waiting', 'New organization applications will show up here.'],
        'skipped' => ['Nothing needs a manual check', 'Applications whose domain is not DNS-verified will show up here.'],
        'approved' => ['No approved organizations yet', 'Organizations appear here once you approve them.'],
        'rejected' => ['No rejected applications', 'Rejected applications will be listed here with the reason.'],
    ][$tab];
@endphp

<x-platform-layout title="Organizations">
    <x-platform.page-header title="Organizations" subtitle="Review applications and manage the organizations on MyKaunsel." />

    {{-- Tabs with counts --}}
    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-brand-ink/10" aria-label="Organization status">
        @foreach ($tabs as $key => $label)
            <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif
               class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-[14px] font-semibold transition {{ $tab === $key ? 'border-brand-blue text-brand-blue' : 'border-transparent text-brand-ink/60 hover:text-brand-ink' }}">
                {{ $label }}
                <span class="min-w-[22px] rounded-full px-1.5 py-0.5 text-center text-[11px] font-bold {{ $tab === $key ? 'bg-brand-blue text-white' : 'bg-brand-ink/10 text-brand-ink/70' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Search + filters --}}
    <form method="GET" action="{{ route('platform.organizations') }}" class="mt-5 flex flex-wrap items-center gap-3">
        @if ($tab !== 'pending')
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif

        <label class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <span class="sr-only">Search organizations</span>
            <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-brand-ink/40" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name or admin email"
                   class="w-full rounded-full border border-brand-ink/10 bg-white py-2.5 pl-10 pr-4 text-[14px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
        </label>

        <label>
            <span class="sr-only">Organization type</span>
            <select name="type" onchange="this.form.requestSubmit()" class="rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-9 text-[14px] focus:border-brand-blue focus:ring-brand-blue">
                <option value="">All types</option>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}" @selected($type === $t->value)>{{ ucfirst($t->value) }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="sr-only">Sort by</span>
            <select name="sort" onchange="this.form.requestSubmit()" class="rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-9 text-[14px] focus:border-brand-blue focus:ring-brand-blue">
                <option value="oldest" @selected($sort === 'oldest')>{{ $isWaitingTab ? 'Oldest waiting first' : 'Oldest first' }}</option>
                <option value="newest" @selected($sort === 'newest')>Newest first</option>
                <option value="name" @selected($sort === 'name')>Name A–Z</option>
            </select>
        </label>

        <button type="submit" class="rounded-full bg-brand-blue px-5 py-2.5 text-[14px] font-semibold text-white transition hover:bg-brand-deep">Search</button>

        @if ($filtering)
            <a href="{{ route('platform.organizations', array_filter(['tab' => $tab === 'pending' ? null : $tab])) }}"
               class="text-[13px] font-semibold text-brand-blue hover:underline">Clear filters</a>
        @endif
    </form>

    {{-- List --}}
    <div class="mt-5">
        @if ($organizations->isEmpty())
            <x-platform.panel class="py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                </span>
                @if ($filtering)
                    <p class="mt-4 text-[15px] font-bold">No organizations match your search</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Try a different name, email or type — or check the other tabs.</p>
                @else
                    <p class="mt-4 text-[15px] font-bold">{{ $emptyText[0] }}</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">{{ $emptyText[1] }}</p>
                @endif
            </x-platform.panel>
        @elseif ($isWaitingTab)
            <div class="space-y-3">
                @foreach ($organizations as $organization)
                    <x-platform.org-application-card :organization="$organization" />
                @endforeach
            </div>
        @elseif ($tab === 'approved')
            <x-platform.table :headings="['Organization', 'Type', 'Members', 'Status', 'Approved', '']">
                @foreach ($organizations as $organization)
                    <tr class="border-t border-brand-ink/[.07] transition hover:bg-brand-blue/[.03]">
                        <td class="px-4 py-3.5 text-[14px] font-semibold">{{ $organization->name }}</td>
                        <td class="px-4 py-3.5"><x-platform.org-type-badge :type="$organization->org_type" /></td>
                        <td class="px-4 py-3.5 text-[14px] tabular-nums">{{ number_format($organization->members_count) }}</td>
                        <td class="px-4 py-3.5">
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold',
                                'bg-emerald-50 text-emerald-700' => $organization->subscription_status === \App\Enums\OrgStatus::Active,
                                'bg-brand-red/10 text-brand-red' => $organization->subscription_status === \App\Enums\OrgStatus::Suspended,
                            ])>
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst($organization->subscription_status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-[13px] text-brand-ink/65">{{ ($organization->reviewed_at ?? $organization->created_at)->format('j M Y') }}</td>
                        <td class="px-4 py-3.5 text-right">
                            <span class="inline-flex cursor-not-allowed items-center gap-1.5 text-[12px] font-semibold text-brand-ink/40" title="Coming soon">
                                Manage <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide">Soon</span>
                            </span>
                        </td>
                    </tr>
                @endforeach
            </x-platform.table>
        @else
            <div class="space-y-3">
                @foreach ($organizations as $organization)
                    <article class="rounded-[20px] border border-brand-ink/10 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-[16px] font-bold leading-tight">{{ $organization->name }}</h3>
                                <x-platform.org-type-badge :type="$organization->org_type" />
                            </div>
                            <p class="text-[12.5px] text-brand-ink/55">
                                {{ ($organization->reviewed_at ?? $organization->updated_at)->format('j M Y') }}
                                · by {{ $organization->reviewer?->name ?? 'Unknown' }}
                            </p>
                        </div>
                        <p class="mt-3 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13.5px] leading-relaxed text-brand-ink/80">
                            <span class="font-semibold text-brand-red">Reason:</span> {{ $organization->rejection_reason ?? 'No reason recorded.' }}
                        </p>
                    </article>
                @endforeach
            </div>
        @endif

        @if ($organizations->hasPages())
            <div class="mt-5">{{ $organizations->links() }}</div>
        @endif
    </div>
</x-platform-layout>
