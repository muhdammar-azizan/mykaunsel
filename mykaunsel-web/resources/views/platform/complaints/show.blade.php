@php
    $local = fn ($value, $format = 'j M Y, g:i A') => $value ? $value->copy()->timezone($timezone)->format($format) : '—';
    $org = $booking?->organization;
    $entry = $booking?->calendarEntry;
    $handling = $complaint->isOpenForReview();
    $canSuspend = $handling && $profile && $profile->verification_status === \App\Enums\VerificationStatus::Approved;
    $seen = $complaint->status->value;
@endphp

<x-platform-layout :title="'Complaint #'.$complaint->id">
    <a href="{{ route('platform.complaints', ['tab' => $complaint->status->value === 'open' ? null : $complaint->status->value]) }}"
       class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Complaints
    </a>

    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-[24px] font-extrabold leading-tight tracking-tightest">Complaint #{{ $complaint->id }}</h1>
            <x-platform.complaint-status-pill :status="$complaint->status" />
        </div>
        <p class="mt-1.5 text-[13.5px] text-brand-ink/60">Filed {{ $local($complaint->created_at) }} ({{ $complaint->created_at->diffForHumans() }})</p>
    </header>

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    {{-- What can be done about it --}}
    <x-platform.panel class="mt-5">
        @if ($handling)
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-[14px] font-bold">Handle this complaint</h2>
                    <p class="mt-0.5 text-[13px] text-brand-ink/60">Move it along, warn the counselor, or suspend them. Every action is saved in the audit log under your name.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($complaint->status === \App\Enums\ComplaintStatus::Open)
                        <form method="POST" action="{{ route('platform.complaints.status', $complaint) }}" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <input type="hidden" name="to" value="under_review">
                            <input type="hidden" name="from" value="{{ $seen }}">
                            <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60">Start review</button>
                        </form>
                    @endif
                    <button type="button" x-data @click="$dispatch('open-modal', 'warn-counselor')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Send warning</button>
                    @if ($canSuspend)
                        <button type="button" x-data @click="$dispatch('open-modal', 'suspend-from-complaint')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Suspend counselor</button>
                    @endif
                    <button type="button" x-data @click="$dispatch('open-modal', 'dismiss-complaint')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Dismiss</button>
                    <button type="button" x-data @click="$dispatch('open-modal', 'resolve-complaint')" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Resolve</button>
                </div>
            </div>
            @if (! $canSuspend && $profile && $profile->verification_status !== \App\Enums\VerificationStatus::Approved)
                <p class="mt-3 rounded-xl bg-brand-ink/[.05] px-4 py-2.5 text-[13px] text-brand-ink/65">This counselor is {{ $profile->verification_status->value }}, so there is nothing to suspend.</p>
            @endif
        @else
            <h2 class="text-[14px] font-bold">Handle this complaint</h2>
            <p class="mt-0.5 text-[13px] text-brand-ink/60">
                {{ ucfirst($complaint->status->value) }}
                @if ($closing) on {{ $local($closing['at'], 'j M Y, g:i A') }} by {{ $closing['by'] }} @endif
                — this complaint is closed.
            </p>
            @if ($closing && $closing['outcome'])
                <p class="mt-2 rounded-xl bg-cream px-4 py-3 text-[13.5px] leading-relaxed"><span class="font-semibold">Outcome:</span> {{ $closing['outcome'] }}</p>
            @endif
        @endif
    </x-platform.panel>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-platform.panel title="What was reported" class="lg:col-span-2">
            <p class="mt-1 text-[12.5px] text-brand-ink/55">Confidential. Only Platform Admins see this, and it is never copied into the audit log.</p>
            <p class="mt-3 whitespace-pre-line break-words rounded-2xl bg-cream px-5 py-4 text-[14px] leading-relaxed">{{ $complaint->description }}</p>
        </x-platform.panel>

        <x-platform.panel title="Counselor">
            <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                <x-platform.detail-row label="Name">{{ $complaint->counselor->name }}</x-platform.detail-row>
                @if ($profile)
                    <x-platform.detail-row label="KB number"><span class="font-mono">{{ $profile->kb_number }}</span></x-platform.detail-row>
                    <x-platform.detail-row label="Status">{{ ucfirst($profile->verification_status->value) }}</x-platform.detail-row>
                    <x-platform.detail-row label="Type">{{ $profile->isIndependent() ? 'Independent' : 'Organization counselor' }}</x-platform.detail-row>
                @endif
                <x-platform.detail-row label="Other complaints about them">
                    @if ($otherComplaints > 0)
                        <a href="{{ $profile ? route('platform.complaints', ['counselor' => $profile->id, 'tab' => null]) : '#' }}" class="text-brand-blue hover:underline">{{ $otherComplaints }}</a>
                    @else
                        None
                    @endif
                </x-platform.detail-row>
            </dl>
            @if ($profile)
                <a href="{{ route('platform.counselors.show', $profile) }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-blue hover:underline">Open the counselor's page →</a>
            @endif
        </x-platform.panel>

        <div class="space-y-5">
            <x-platform.panel title="Reporter">
                <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                    <x-platform.detail-row label="Name">{{ $complaint->reporter->name }}</x-platform.detail-row>
                </dl>
            </x-platform.panel>

            <x-platform.panel title="Session">
                @if ($booking)
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="Organization">
                            {{ $org ? ($org->org_type === \App\Enums\OrgType::Platform ? 'Independent practice' : $org->name) : '—' }}
                        </x-platform.detail-row>
                        @if ($entry)
                            <x-platform.detail-row label="Date">{{ $entry->entry_date->format('j M Y') }}</x-platform.detail-row>
                        @endif
                        <x-platform.detail-row label="Mode">{{ ucfirst($booking->session_mode->value) }}</x-platform.detail-row>
                        <x-platform.detail-row label="Booking status">{{ ucfirst(str_replace('_', ' ', $booking->status->value)) }}</x-platform.detail-row>
                    </dl>
                    @if ($org && $org->org_type !== \App\Enums\OrgType::Platform)
                        <a href="{{ route('platform.organizations.show', $org) }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-blue hover:underline">Open the organization →</a>
                    @endif
                @else
                    <p class="mt-2 text-[13.5px] text-brand-ink/60">This complaint is not linked to a session.</p>
                @endif
            </x-platform.panel>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-platform.review-notes :notes="$notes"
            :action="route('platform.complaints.notes.store', $complaint)"
            placeholder="e.g. Asked the organization admin for their side." />

        <x-platform.activity-timeline :timeline="$timeline" :history-url="route('platform.audit-log', ['target_type' => 'complaint', 'q' => 'Complaint #'.$complaint->id])" />
    </div>

    @if ($handling)
        {{-- Resolve --}}
        <x-platform.reason-modal name="resolve-complaint" :action="route('platform.complaints.status', $complaint)" bag="resolved" tone="primary"
            :title="'Resolve complaint #'.$complaint->id.'?'" textarea-id="resolve-reason" :rows="4"
            label="Outcome (kept in the audit log — do not paste the complaint text)" placeholder="What was done about it?"
            submit-label="Mark as resolved" busy-label="Saving…">
            <input type="hidden" name="to" value="resolved">
            <input type="hidden" name="from" value="{{ $seen }}">
            <p class="mt-1 text-[13px] text-brand-ink/60">Closing a complaint is final: it cannot be reopened.</p>
        </x-platform.reason-modal>

        {{-- Dismiss --}}
        <x-platform.reason-modal name="dismiss-complaint" :action="route('platform.complaints.status', $complaint)" bag="dismissed"
            :title="'Dismiss complaint #'.$complaint->id.'?'" textarea-id="dismiss-reason" :rows="4"
            label="Why it is dismissed (kept in the audit log — do not paste the complaint text)" placeholder="Why is no action being taken?"
            submit-label="Dismiss complaint" busy-label="Saving…">
            <input type="hidden" name="to" value="dismissed">
            <input type="hidden" name="from" value="{{ $seen }}">
            <p class="mt-1 text-[13px] text-brand-ink/60">Closing a complaint is final: it cannot be reopened.</p>
        </x-platform.reason-modal>

        {{-- Warn --}}
        <x-platform.reason-modal name="warn-counselor" :action="route('platform.complaints.warn', $complaint)" bag="warn" tone="primary"
            :title="'Warn '.$complaint->counselor->name.'?'" textarea-id="warn-reason" :rows="5"
            label="Message to the counselor" placeholder="What should they change?"
            submit-label="Send warning" busy-label="Sending…">
            <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Your message is emailed to the counselor. It never says who complained or what they wrote.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> It does not change the complaint's status or the counselor's account.</li>
            </ul>
        </x-platform.reason-modal>

        @if ($canSuspend)
            <x-platform.counselor-suspend-modal name="suspend-from-complaint" :action="route('platform.complaints.suspend-counselor', $complaint)"
                :counselor-name="$complaint->counselor->name" :independent="$profile->isIndependent()" :upcoming="$upcoming" :pending-requests="$pendingRequests" />
        @endif
    @endif

    <x-platform.toast />
</x-platform-layout>
