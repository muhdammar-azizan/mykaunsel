@php
    $statusClass = ['Aktif' => 'bg-emerald-50 text-emerald-700', 'Tidak Aktif' => 'bg-brand-ink/10 text-brand-ink/70', 'Digantung' => 'bg-brand-red/10 text-brand-red'];
    $toneClass = ['good' => 'bg-emerald-50 text-emerald-700', 'warn' => 'bg-brand-yellow/25 text-[#7A5410]', 'bad' => 'bg-brand-red/10 text-brand-red'];
@endphp

<x-platform-layout :title="$record->full_name">
    <a href="{{ route('platform.lkm') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        LKM Directory
    </a>

    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-[24px] font-extrabold leading-tight tracking-tightest">{{ $record->full_name }}</h1>
            <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[12px] font-semibold text-brand-ink/70">{{ $record->kb_number }}</span>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $statusClass[$record->status] ?? 'bg-brand-ink/10' }}">{{ $record->status }}</span>
            <x-platform.pa-badge :date="$record->pa_valid_until" :long="true" />
        </div>
        <p class="mt-1.5 text-[13.5px] text-brand-ink/60">Last changed {{ $record->updated_at->copy()->timezone($timezone)->format('j M Y, g:i A') }}</p>
    </header>

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <x-platform.panel title="Record">
            <form method="POST" action="{{ route('platform.lkm.update', $record) }}" class="mt-3" x-data="{ busy: false }" @submit="busy = true">
                @csrf @method('PUT')
                @include('platform.lkm._fields')
                <div class="mt-5 flex justify-end">
                    <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">Save changes</button>
                </div>
            </form>
        </x-platform.panel>

        <div class="space-y-5">
            <x-platform.panel title="On MyKaunsel">
                @if ($profiles->isEmpty())
                    <p class="mt-2 text-[13.5px] text-brand-ink/60">No counselor has registered with this KB number yet.</p>
                @else
                    <p class="mt-1 text-[12.5px] text-brand-ink/55">Changes here are not copied to these accounts. They show in the next LKM check (application review, approval, reactivation).</p>
                    <ul class="mt-3 divide-y divide-brand-ink/[.07] rounded-xl border border-brand-ink/10">
                        @foreach ($profiles as $profile)
                            @php $cmp = $comparisons[$profile->id]; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('platform.counselors.show', $profile) }}" class="text-[14px] font-semibold text-brand-blue hover:underline">{{ $profile->user->name }}</a>
                                    <p class="text-[12px] text-brand-ink/55">{{ ucfirst($profile->verification_status->value) }} · {{ $profile->isIndependent() ? 'Independent' : 'Organization' }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $toneClass[$cmp->tone()] }}">{{ $cmp->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-platform.panel>

            <x-platform.panel title="History">
                @if ($history->isEmpty())
                    <p class="mt-2 text-[13.5px] text-brand-ink/60">No changes recorded yet.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($history as $row)
                            <li class="text-[13px]">
                                <p class="font-semibold">{{ $row['action_label'] }}</p>
                                @if ($row['summary'] !== '')<p class="text-[12.5px] text-brand-ink/65">{{ $row['summary'] }}</p>@endif
                                <p class="text-[11.5px] text-brand-ink/50" title="{{ $row['time_utc'] }}">{{ $row['actor']['name'] }} · {{ $row['time'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('platform.audit-log', ['target_type' => 'lkm_record', 'q' => $record->full_name]) }}" class="mt-3 inline-block text-[13px] font-semibold text-brand-blue hover:underline">View full history →</a>
                @endif
            </x-platform.panel>
        </div>
    </div>

    <x-platform.toast />
</x-platform-layout>
