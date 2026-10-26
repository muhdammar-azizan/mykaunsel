@php
    $tabUrl = fn (string $key) => route('platform.counselors', $filters->toQuery(['tab', 'page'], $key === 'pending' ? [] : ['tab' => $key]));

    $emptyText = [
        'pending' => ['No applications waiting', 'Independent counselors who register and upload their documents will show up here.'],
        'active' => ['No active counselors yet', 'Approved counselors, and counselors added by their organization, appear here.'],
        'suspended' => ['No suspended counselors', 'Counselors who are suspended — by you, after a complaint, or automatically when their PA expires — are listed here.'],
        'rejected' => ['No rejected applications', 'Rejected applications are listed here with the reason.'],
    ][$tab];

    $selectClass = 'rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-9 text-[14px] focus:border-brand-blue focus:ring-brand-blue';
    $date = fn ($value) => $value ? $value->copy()->timezone($timezone)->format('j M Y') : '—';

    // "Independent" or the organization name(s) the counselor works in.
    $typeLabel = function ($profile) {
        if ($profile->isIndependent()) {
            return 'Independent';
        }

        $names = $profile->memberships
            ->filter(fn ($m) => $m->organization && $m->organization->org_type !== \App\Enums\OrgType::Platform && $m->role === \App\Enums\MembershipRole::Counselor)
            ->map(fn ($m) => $m->organization->name)->values();

        return $names->isEmpty() ? 'Organization' : $names->first().($names->count() > 1 ? ' +'.($names->count() - 1) : '');
    };
@endphp

<x-platform-layout title="Counselors">
    <x-platform.page-header title="Counselors" subtitle="Review independent counselors and look after the counselors on MyKaunsel." />

    <x-platform.status-tabs
        :tabs="collect($tabs)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $counts[$key], 'url' => $tabUrl($key)])->values()->all()"
        :active="$tab"
        label="Counselor status" />

    @if ($filters->errors()->any())
        <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/15 px-5 py-3 text-[13px] text-[#6B4C0C]" role="alert">
            <p class="font-bold">Some filters in the link were not valid and were ignored:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($filters->errors()->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Search + filters --}}
    <form method="GET" action="{{ route('platform.counselors') }}" role="search" aria-label="Filter counselors" class="mt-5 flex flex-wrap items-center gap-3">
        @if ($tab !== 'pending')
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif

        <label class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <span class="sr-only">Search counselors</span>
            <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-brand-ink/40" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $filters->get('q') }}" maxlength="100" placeholder="Search name, KB number or email"
                   class="w-full rounded-full border border-brand-ink/10 bg-white py-2.5 pl-10 pr-4 text-[14px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
        </label>

        <label>
            <span class="sr-only">Counselor type</span>
            <select name="type" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">All types</option>
                <option value="independent" @selected($filters->get('type') === 'independent')>Independent</option>
                <option value="organization" @selected($filters->get('type') === 'organization')>Organization (any)</option>
                @foreach ($organizations as $id => $name)
                    <option value="organization:{{ $id }}" @selected($filters->get('type') === 'organization:'.$id)>Organization: {{ $name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="sr-only">PA status</span>
            <select name="pa" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">Any PA status</option>
                <option value="valid" @selected($filters->get('pa') === 'valid')>PA valid</option>
                <option value="expiring" @selected($filters->get('pa') === 'expiring')>Expiring within 30 days</option>
                <option value="expired" @selected($filters->get('pa') === 'expired')>PA expired</option>
            </select>
        </label>

        <label class="inline-flex cursor-pointer items-center gap-2 text-[13.5px]">
            <input type="checkbox" name="flagged" value="1" @checked($filters->get('flagged')) onchange="this.form.requestSubmit()"
                   class="h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-blue focus:ring-brand-blue">
            Flagged (duplicate KB attempt)
        </label>

        <button type="submit" class="rounded-full bg-brand-blue px-5 py-2.5 text-[14px] font-semibold text-white transition hover:bg-brand-deep">Search</button>

        @if ($filters->isFiltering())
            <a href="{{ route('platform.counselors', array_filter(['tab' => $tab === 'pending' ? null : $tab])) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Clear filters</a>
        @endif
    </form>

    {{-- List --}}
    <div class="mt-5">
        @if ($profiles->isEmpty())
            <x-platform.panel class="py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
                </span>
                @if ($filters->isFiltering())
                    <p class="mt-4 text-[15px] font-bold">No counselors match your search</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Try a different name, KB number or filter — or check the other tabs.</p>
                @else
                    <p class="mt-4 text-[15px] font-bold">{{ $emptyText[0] }}</p>
                    <p class="mx-auto mt-1 max-w-[52ch] text-[13px] text-brand-ink/55">{{ $emptyText[1] }}</p>
                @endif
            </x-platform.panel>

        @elseif ($tab === 'pending')
            <div class="space-y-3">
                @foreach ($profiles as $profile)
                    <x-platform.counselor-application-card :profile="$profile" :lkm="$lkm[$profile->id]" />
                @endforeach
            </div>

        @elseif ($tab === 'active')
            <x-platform.table :headings="['Counselor', 'Type', 'KB number', 'PA valid until', 'Open complaints', 'Status', 'Approved', '']">
                @foreach ($profiles as $profile)
                    <tr class="border-t border-brand-ink/[.07] transition hover:bg-brand-blue/[.03]">
                        <td class="px-4 py-3.5">
                            <p class="text-[14px] font-semibold">{{ $profile->user->name }}</p>
                            <p class="text-[12px] text-brand-ink/50">{{ $profile->user->email }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-[13.5px]">{{ $typeLabel($profile) }}</td>
                        <td class="px-4 py-3.5 font-mono text-[12.5px]">{{ $profile->kb_number }}</td>
                        <td class="px-4 py-3.5"><x-platform.pa-badge :date="$profile->pa_valid_until" /></td>
                        <td class="px-4 py-3.5 text-[14px] tabular-nums">
                            <span class="{{ $profile->open_complaints_count > 0 ? 'font-bold text-brand-red' : 'text-brand-ink/60' }}">{{ $profile->open_complaints_count }}</span>
                        </td>
                        <td class="px-4 py-3.5"><x-platform.org-status-pill status="active" /></td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-[13px] text-brand-ink/65">{{ $date($profile->reviewed_at ?? $profile->created_at) }}</td>
                        <td class="px-4 py-3.5 text-right"><a href="{{ route('platform.counselors.show', $profile) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">View details</a></td>
                    </tr>
                @endforeach
            </x-platform.table>

        @elseif ($tab === 'suspended')
            <div class="space-y-3">
                @foreach ($profiles as $profile)
                    @php $source = ['platform' => 'Platform', 'complaint' => 'Complaint', 'system' => 'System (PA expired)'][$profile->suspension_source] ?? 'Platform'; @endphp
                    <article class="rounded-[20px] border border-brand-ink/10 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-[16px] font-bold leading-tight">{{ $profile->user->name }}</h3>
                                    <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[11.5px] font-semibold text-brand-ink/70">{{ $profile->kb_number }}</span>
                                    <x-platform.org-status-pill status="suspended" />
                                </div>
                                <p class="mt-1 text-[13px] text-brand-ink/60">{{ $typeLabel($profile) }} · PA valid until <x-platform.pa-badge :date="$profile->pa_valid_until" /></p>
                            </div>
                            <p class="text-[12.5px] text-brand-ink/55">
                                {{ $date($profile->suspended_at) }} · by {{ $profile->suspension_source === 'system' ? 'System' : ($profile->suspender?->name ?? 'a platform admin') }}
                                · <span class="font-semibold">{{ $source }}</span>
                                · <a href="{{ route('platform.counselors.show', $profile) }}#manage" class="font-semibold text-brand-blue hover:underline">Reactivate…</a> · <a href="{{ route('platform.counselors.show', $profile) }}" class="font-semibold text-brand-blue hover:underline">View details</a>
                            </p>
                        </div>
                        <p class="mt-3 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13.5px] leading-relaxed text-brand-ink/80">
                            <span class="font-semibold text-brand-red">Reason:</span> {{ $profile->suspension_reason ?? 'No reason recorded.' }}
                        </p>
                    </article>
                @endforeach
            </div>

        @else
            <div class="space-y-3">
                @foreach ($profiles as $profile)
                    @php $flagged = $profile->documents->filter(fn ($d) => $d->needsReplacement()); @endphp
                    <article class="rounded-[20px] border border-brand-ink/10 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-[16px] font-bold leading-tight">{{ $profile->user->name }}</h3>
                                <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[11.5px] font-semibold text-brand-ink/70">{{ $profile->kb_number }}</span>
                                <span class="rounded-full bg-brand-ink/10 px-2.5 py-0.5 text-[11px] font-bold text-brand-ink/70">Attempt {{ $profile->submission_count }}</span>
                            </div>
                            <p class="text-[12.5px] text-brand-ink/55">
                                {{ $date($profile->reviewed_at ?? $profile->updated_at) }} · by {{ $profile->reviewer?->name ?? 'a platform admin' }}
                                · <a href="{{ route('platform.counselors.show', $profile) }}" class="font-semibold text-brand-blue hover:underline">View details</a>
                            </p>
                        </div>
                        <p class="mt-3 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13.5px] leading-relaxed text-brand-ink/80">
                            <span class="font-semibold text-brand-red">Reason:</span> {{ $profile->rejection_reason ?? 'No reason recorded.' }}
                        </p>
                        @if ($flagged->isNotEmpty())
                            <p class="mt-2 text-[12.5px] text-brand-ink/60">Documents to replace:
                                @foreach ($flagged as $doc)<span class="ml-1 rounded-full bg-brand-red/10 px-2 py-0.5 font-semibold text-brand-red">{{ $doc->label() }}</span>@endforeach
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($profiles->hasPages())
            <div class="mt-5">{{ $profiles->links() }}</div>
        @endif
    </div>
</x-platform-layout>
