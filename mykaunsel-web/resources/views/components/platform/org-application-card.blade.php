@props(['organization'])

{{-- A pending / skipped-verification application in the Organizations list. --}}
@php
    $org = $organization;
    $days = $org->waitingDays();
    $tone = $org->waitingTone();
    $waitingText = $days === 0 ? 'Submitted today' : 'Waiting '.$days.' '.\Illuminate\Support\Str::plural('day', $days);
    $toneClass = ['ok' => 'bg-brand-ink/5 text-brand-ink/65', 'amber' => 'bg-brand-yellow/20 text-[#8A6210]', 'red' => 'bg-brand-red/10 text-brand-red'][$tone];

    $domain = match ($org->domainCheckState()) {
        'verified' => ['good', 'Verified'],
        'skipped' => ['warn', 'Skipped'],
        default => ['muted', 'Not applicable'],
    };

    $hasSsm = filled($org->ssm_number);
    $ssm = $hasSsm ? ['good', 'Provided'] : ($org->requiresSsm() ? ['bad', 'Missing'] : ['muted', 'Optional']);
    $authority = $org->authority_confirmed_at ? ['good', 'Confirmed'] : ['bad', 'Not confirmed'];
    $registrant = $org->registrant?->user;
@endphp

<article class="group rounded-[20px] border border-brand-ink/10 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-16px_rgba(14,42,51,.3)]">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-[16px] font-bold leading-tight">{{ $org->name }}</h3>
                <x-platform.org-type-badge :type="$org->org_type" />
                @if ($org->submission_count >= 2)
                    <span class="inline-flex items-center rounded-full bg-brand-blue px-2.5 py-0.5 text-[11px] font-bold text-white" title="Attempt {{ $org->submission_count }}">Resubmitted</span>
                @endif
            </div>
            @if ($registrant)
                <p class="mt-1 text-[13px] text-brand-ink/60">{{ $registrant->name }} · {{ $registrant->email }}</p>
            @endif
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-semibold {{ $toneClass }}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            {{ $waitingText }}
        </span>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <x-platform.org-check label="Domain" :state="$domain[0]" :text="$domain[1]" />
            <x-platform.org-check label="SSM" :state="$ssm[0]" :text="$ssm[1]" />
            <x-platform.org-check label="Admin authority" :state="$authority[0]" :text="$authority[1]" />
        </div>

        <a href="{{ route('platform.organizations.show', $org) }}"
           class="inline-flex items-center gap-1 rounded-full border border-brand-ink/15 bg-white px-4 py-1.5 text-[13px] font-semibold shadow-sm transition hover:border-brand-ink hover:bg-brand-ink hover:text-white">
            Review <span aria-hidden="true">→</span>
        </a>
    </div>
</article>
