@props(['profile', 'lkm'])

{{-- A pending application in the Counselors list. $lkm: the LkmComparison for this profile. --}}
@php
    $days = $profile->waitingDays();
    $tone = $profile->waitingTone();
    $waitingText = $days === 0 ? 'Submitted today' : 'Waiting '.$days.' '.\Illuminate\Support\Str::plural('day', $days);
    $toneClass = ['ok' => 'bg-brand-ink/5 text-brand-ink/65', 'amber' => 'bg-brand-yellow/20 text-[#8A6210]', 'red' => 'bg-brand-red/10 text-brand-red'][$tone];
    $documents = $profile->documents->keyBy('type');
    $lkmTone = ['good' => ['good', 'M20 6 9 17l-5-5'], 'warn' => ['warn', 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'], 'bad' => ['bad', 'M18 6 6 18M6 6l12 12']][$lkm->tone()];
    $lkmClass = ['good' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'warn' => 'bg-brand-yellow/15 text-[#8A6210] ring-brand-yellow/40', 'bad' => 'bg-brand-red/10 text-brand-red ring-brand-red/25'][$lkm->tone()];
@endphp

<article class="rounded-[20px] border border-brand-ink/10 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-16px_rgba(14,42,51,.3)]">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-[16px] font-bold leading-tight">{{ $profile->user->name }}</h3>
                <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[11.5px] font-semibold text-brand-ink/70">{{ $profile->kb_number }}</span>
                @if ($profile->submission_count >= 2)
                    <span class="inline-flex items-center rounded-full bg-brand-blue px-2.5 py-0.5 text-[11px] font-bold text-white" title="Attempt {{ $profile->submission_count }}">Resubmitted</span>
                @endif
                @if ($profile->kb_attempts_count > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-red px-2.5 py-0.5 text-[11px] font-bold text-white" title="{{ $profile->kb_attempts_count }} blocked attempt(s) to register this KB number again">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        Duplicate KB attempt
                    </span>
                @endif
            </div>
            <p class="mt-1 text-[13px] text-brand-ink/60">{{ $profile->user->email }}</p>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-semibold {{ $toneClass }}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            {{ $waitingText }}
        </span>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-semibold ring-1 ring-inset {{ $lkmClass }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $lkmTone[1] }}"/></svg>
                <span class="font-medium opacity-75">LKM:</span> {{ $lkm->label() }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-[12px] text-brand-ink/60">PA valid until <x-platform.pa-badge :date="$profile->pa_valid_until" /></span>
        </div>

        <a href="{{ route('platform.counselors.show', $profile) }}"
           class="inline-flex items-center gap-1 rounded-full border border-brand-ink/15 bg-white px-4 py-1.5 text-[13px] font-semibold shadow-sm transition hover:border-brand-ink hover:bg-brand-ink hover:text-white">
            Review <span aria-hidden="true">→</span>
        </a>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach (\App\Models\CounselorDocument::TYPES as $type => $typeLabel)
            <x-platform.doc-chip :document="$documents->get($type)" :label="$typeLabel" />
        @endforeach
    </div>
</article>
