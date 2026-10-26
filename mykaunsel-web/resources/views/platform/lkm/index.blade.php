@php
    $selectClass = 'rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-9 text-[14px] focus:border-brand-blue focus:ring-brand-blue';
    $statusClass = ['Aktif' => 'bg-emerald-50 text-emerald-700', 'Tidak Aktif' => 'bg-brand-ink/10 text-brand-ink/70', 'Digantung' => 'bg-brand-red/10 text-brand-red'];
@endphp

<x-platform-layout title="LKM Directory">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <x-platform.page-header title="LKM Directory" subtitle="Simulated dataset used for credential verification. Counselors must match a record here to register." />
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('platform.lkm.import') }}" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Import CSV</a>
            <a href="{{ route('platform.lkm.export', $filters->toQuery()) }}" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5"
               title="{{ $filters->isFiltering() ? 'The records this search and these filters show' : 'Every record' }}">Export CSV</a>
            <a href="{{ route('platform.lkm.create') }}" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Add record</a>
        </div>
    </div>

    <x-platform.lkm-simulated-note class="mt-4" />

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/30 bg-brand-red/10 px-5 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <x-platform.lkm-tabs active="directory" />

    <dl class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Directory summary">
        @foreach ([
            ['Records', $summary['total'], route('platform.lkm'), 'text-brand-ink'],
            ['Active', $summary['active'], route('platform.lkm', ['status' => 'Aktif']), 'text-emerald-700'],
            ['PA expired', $summary['expired'], route('platform.lkm', ['pa' => 'expired']), $summary['expired'] > 0 ? 'text-brand-red' : 'text-brand-ink'],
            ['On MyKaunsel', $summary['linked'], route('platform.lkm', ['registered' => 'yes']), 'text-brand-blue'],
        ] as [$label, $count, $link, $tone])
            <a href="{{ $link }}" class="rounded-2xl bg-white px-4 py-3 ring-1 ring-brand-ink/10 transition hover:ring-brand-blue/40">
                <dt class="text-[12px] text-brand-ink/60">{{ $label }}</dt>
                <dd class="mt-0.5 text-[24px] font-extrabold leading-none tracking-tightest {{ $tone }}">{{ number_format($count) }}</dd>
            </a>
        @endforeach
    </dl>

    @if ($filters->errors()->any())
        <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/15 px-5 py-3 text-[13px] text-[#6B4C0C]" role="alert">
            <p class="font-bold">Some filters in the link were not valid and were ignored:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($filters->errors()->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" action="{{ route('platform.lkm') }}" role="search" aria-label="Filter the LKM directory" class="mt-5 flex flex-wrap items-center gap-3">
        <label class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <span class="sr-only">Search the directory</span>
            <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-brand-ink/40" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $filters->get('q') }}" maxlength="100" placeholder="Search name, KB number or email"
                   class="w-full rounded-full border border-brand-ink/10 bg-white py-2.5 pl-10 pr-4 text-[14px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
        </label>

        <label>
            <span class="sr-only">Status</span>
            <select name="status" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">Any status</option>
                @foreach (\App\Models\LkmDirectorySnapshot::STATUSES as $key => $text)
                    <option value="{{ $key }}" @selected($filters->get('status') === $key)>{{ $text }}</option>
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

        <label>
            <span class="sr-only">State</span>
            <select name="state" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">Any state</option>
                @foreach ($states as $state)
                    <option value="{{ $state }}" @selected($filters->get('state') === $state)>{{ $state }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="sr-only">On MyKaunsel</span>
            <select name="registered" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                <option value="">On MyKaunsel or not</option>
                <option value="yes" @selected($filters->get('registered') === 'yes')>Registered on MyKaunsel</option>
                <option value="no" @selected($filters->get('registered') === 'no')>Not registered</option>
            </select>
        </label>

        <label>
            <span class="sr-only">Sort by</span>
            <select name="sort" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                @foreach (\App\Support\LkmDirectoryFilters::SORTS as $key => $text)
                    <option value="{{ $key }}" @selected($filters->sort() === $key)>Sort: {{ $text }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="rounded-full bg-brand-blue px-5 py-2.5 text-[14px] font-semibold text-white transition hover:bg-brand-deep">Search</button>

        @if ($filters->isFiltering())
            <a href="{{ route('platform.lkm') }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Clear filters</a>
        @endif
    </form>

    <p class="mt-4 text-[13px] text-brand-ink/60" aria-live="polite">
        @if ($filters->isFiltering())
            <span class="font-semibold text-brand-ink">{{ number_format($records->total()) }}</span> of {{ number_format($total) }} records match.
        @else
            <span class="font-semibold text-brand-ink">{{ number_format($total) }}</span> records.
        @endif
    </p>

    <div class="mt-2">
        @if ($records->isEmpty())
            <x-platform.panel class="py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                @if ($filters->isFiltering())
                    <p class="mt-4 text-[15px] font-bold">No records match your search</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Try a different name or KB number, or clear the filters.</p>
                @else
                    <p class="mt-4 text-[15px] font-bold">The directory is empty</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Add the first record. Nobody can register as a counselor until their details are here.</p>
                @endif
            </x-platform.panel>
        @else
            <x-platform.table :headings="['Name', 'KB number', 'PA number', 'Status', 'PA valid until', 'State', 'On MyKaunsel', '']">
                @foreach ($records as $record)
                    @php $expired = \App\Support\PaValidity::isExpired($record->pa_valid_until); @endphp
                    <tr class="border-t border-brand-ink/[.07] transition hover:bg-brand-blue/[.03]">
                        {{-- A red edge on expired rows, so they stand out in a long list (the PA badge says it in words too). --}}
                        <td class="border-l-[3px] px-4 py-3.5 {{ $expired ? 'border-l-brand-red' : 'border-l-transparent' }}">
                            <p class="text-[14px] font-semibold">{{ $record->full_name }}</p>
                            @if ($record->email)<p class="text-[12px] text-brand-ink/50">{{ $record->email }}</p>@endif
                        </td>
                        <td class="px-4 py-3.5 font-mono text-[12.5px]">{{ $record->kb_number }}</td>
                        <td class="px-4 py-3.5 font-mono text-[12.5px]">{{ $record->pa_number }}</td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $statusClass[$record->status] ?? 'bg-brand-ink/10 text-brand-ink/70' }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    @if ($record->status === 'Aktif')<path d="m5 12 5 5 9-10"/>@elseif ($record->status === 'Digantung')<path d="M9 6v12M15 6v12"/>@else<path d="M6 12h12"/>@endif
                                </svg>
                                {{ $record->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5"><x-platform.pa-badge :date="$record->pa_valid_until" /></td>
                        <td class="px-4 py-3.5 text-[13px] text-brand-ink/70">{{ $record->state ?? '—' }}</td>
                        <td class="px-4 py-3.5 text-[13px]">
                            @forelse ($record->counselorProfiles as $profile)
                                <a href="{{ route('platform.counselors.show', $profile) }}" class="block font-semibold text-brand-blue hover:underline">{{ $profile->user->name ?? 'Counselor' }}</a>
                            @empty
                                <span class="text-brand-ink/45">Not linked</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3.5 text-right"><a href="{{ route('platform.lkm.show', $record) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">View / edit</a></td>
                    </tr>
                @endforeach
            </x-platform.table>
        @endif

        @if ($records->hasPages())
            <div class="mt-5">{{ $records->links() }}</div>
        @endif
    </div>

    <x-platform.toast />
</x-platform-layout>
