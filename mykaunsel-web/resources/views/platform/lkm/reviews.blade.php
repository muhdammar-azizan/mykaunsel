@php
    $pending = $view === 'pending';
    $fieldLabels = \App\Models\LkmReviewRequest::FIELDS;
    // Dates are shown as people write them; names and numbers as they are.
    $show = fn (string $field, ?string $value) => $value === null || $value === '' ? '—' : ($field === 'pa_valid_until' ? \Illuminate\Support\Carbon::parse($value)->format('j M Y') : $value);
    $at = fn ($time) => $time?->copy()->timezone($timezone)->format('j M Y, g:i A');
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-[14px] text-brand-ink focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
@endphp

<x-platform-layout title="LKM review requests">
    <x-platform.page-header title="LKM Directory" subtitle="Simulated dataset used for credential verification. Counselors can ask for their record to be checked again, with evidence." />

    <x-platform.lkm-simulated-note class="mt-4" />

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/30 bg-brand-red/10 px-5 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <x-platform.lkm-tabs active="reviews" />

    <x-platform.status-tabs class="!mt-3" label="Review requests" :active="$view" :tabs="[
        ['key' => 'pending', 'label' => 'Pending', 'count' => $counts['pending'], 'url' => route('platform.lkm.reviews')],
        ['key' => 'resolved', 'label' => 'Resolved', 'count' => $counts['resolved'], 'url' => route('platform.lkm.reviews', ['view' => 'resolved'])],
    ]" />

    <div class="mt-4 space-y-4">
        @forelse ($reviews as $review)
            @php
                $profile = $review->counselorProfile;
                $person = $profile->user;
                $record = $records->get($profile->kb_number);
                $suspended = $profile->verification_status === \App\Enums\VerificationStatus::Suspended;
                $liveCurrent = $record ? ['pa_valid_until' => $record->pa_valid_until?->format('Y-m-d'), 'pa_number' => $record->pa_number, 'full_name' => $record->full_name] : $review->current;
                $waitDays = $review->created_at->diffInDays(now());
            @endphp

            <x-platform.panel id="review-{{ $review->id }}" class="scroll-mt-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <a href="{{ route('platform.counselors.show', $profile) }}" class="text-[16px] font-extrabold tracking-tight text-brand-ink hover:text-brand-blue hover:underline">{{ $person->name }}</a>
                            <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[12px] font-semibold text-brand-ink/70">{{ $profile->kb_number }}</span>
                            @if ($suspended)
                                <span class="inline-flex rounded-full bg-brand-red/10 px-2.5 py-0.5 text-[11.5px] font-bold text-brand-red">{{ $profile->suspension_source === 'system' ? 'Suspended: PA expired' : 'Suspended' }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-[12.5px] text-brand-ink/55">
                            Requested {{ $at($review->created_at) }}
                            @if ($pending)
                                · <span @class(['font-semibold', 'text-[#7A5410]' => $waitDays >= 7, 'text-brand-ink/70' => $waitDays < 7])>waiting {{ $review->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                            @endif
                        </p>
                    </div>

                    @unless ($pending)
                        <span @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-[12px] font-bold',
                            'bg-emerald-50 text-emerald-700' => $review->status === \App\Models\LkmReviewRequest::APPROVED,
                            'bg-brand-red/10 text-brand-red' => $review->status === \App\Models\LkmReviewRequest::REJECTED,
                        ])>{{ $review->status === \App\Models\LkmReviewRequest::APPROVED ? 'Record updated' : 'Rejected' }}</span>
                    @endunless
                </div>

                {{-- What the system has now, and what the counselor says it should be. (Decided requests show what changed.) --}}
                <x-platform.table :headings="$pending ? ['Detail', 'Current in system', '', 'Claimed by counselor'] : ['Detail', $review->status === \App\Models\LkmReviewRequest::APPROVED ? 'Before' : 'In the record', '', $review->status === \App\Models\LkmReviewRequest::APPROVED ? 'After' : 'Claimed by counselor']">
                    @foreach ($review->claimedFields() as $field)
                        @php
                            $from = $pending ? ($liveCurrent[$field] ?? null) : ($review->status === \App\Models\LkmReviewRequest::APPROVED ? ($review->before[$field] ?? null) : ($review->current[$field] ?? null));
                            $to = $pending || $review->status === \App\Models\LkmReviewRequest::REJECTED ? ($review->claimed[$field] ?? null) : ($review->after[$field] ?? null);
                            $earlier = $pending && $field === 'pa_valid_until' && $from && $to && $to < $from;
                        @endphp
                        <tr class="border-t border-brand-ink/[.07]">
                            <td class="px-4 py-3 text-[13px] font-semibold text-brand-ink/70">{{ $fieldLabels[$field] }}</td>
                            <td class="px-4 py-3 text-[13.5px] {{ $field === 'pa_number' ? 'font-mono' : '' }}">{{ $show($field, $from) }}</td>
                            <td class="px-1 py-3 text-brand-ink/40" aria-label="changes to"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></td>
                            <td class="px-4 py-3 text-[13.5px] font-semibold {{ $field === 'pa_number' ? 'font-mono' : '' }}">
                                {{ $show($field, $to) }}
                                @if ($earlier)<span class="ml-1.5 rounded-full bg-brand-yellow/25 px-2 py-0.5 text-[11px] font-bold text-[#7A5410]">earlier than now</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </x-platform.table>

                {{-- Evidence: opened through a signed link, never a public address. --}}
                <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px]">
                    @if ($review->hasEvidence())
                        <a href="{{ route('platform.lkm.reviews.evidence.open', $review) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-semibold text-brand-blue hover:underline">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5"/></svg>
                            View evidence
                        </a>
                        <span class="text-brand-ink/55">{{ $review->evidence_name }} · {{ number_format($review->evidence_size / 1024) }} KB</span>
                    @else
                        <span class="text-brand-ink/55">Evidence removed {{ $at($review->evidence_purged_at) }} (kept {{ config('lkm.review.evidence_retention_days') }} days after the decision).</span>
                    @endif
                </p>

                @if ($pending)
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-brand-ink/10 pt-4">
                        <button type="button" x-data @click="$dispatch('open-modal', 'review-approve-{{ $review->id }}')" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Update record</button>
                        <button type="button" x-data @click="$dispatch('open-modal', 'review-reject-{{ $review->id }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Reject</button>
                        <a href="{{ route('platform.counselors.show', $profile) }}" class="ml-auto text-[13px] font-semibold text-brand-blue hover:underline">Counselor details →</a>
                    </div>
                @else
                    <div class="mt-4 space-y-2 border-t border-brand-ink/10 pt-4 text-[13px]">
                        <p class="text-brand-ink/65">
                            {{ $review->status === \App\Models\LkmReviewRequest::APPROVED ? 'Updated' : 'Rejected' }} by <span class="font-semibold text-brand-ink">{{ $review->reviewer?->name ?? 'a former admin' }}</span> on {{ $at($review->reviewed_at) }}.
                        </p>
                        @if ($review->decision_reason)
                            <p class="rounded-xl bg-brand-ink/[.04] px-4 py-2.5 text-brand-ink/80"><span class="font-semibold">Reason given to the counselor:</span> {{ $review->decision_reason }}</p>
                        @endif
                        @if ($review->status === \App\Models\LkmReviewRequest::APPROVED && $suspended)
                            <p class="rounded-xl bg-brand-yellow/20 px-4 py-2.5 text-[#6B4C0C]">
                                This counselor is still suspended: updating the record does not lift it.
                                <a href="{{ route('platform.counselors.show', $profile) }}#manage" class="font-semibold underline">Reactivate on the Counselors page →</a>
                            </p>
                        @else
                            <a href="{{ route('platform.counselors.show', $profile) }}" class="inline-block font-semibold text-brand-blue hover:underline">Counselor details →</a>
                        @endif
                    </div>
                @endif
            </x-platform.panel>

            @if ($pending)
                {{-- Update the record: the claimed values are the starting point and can be adjusted; the changes are listed before confirming. --}}
                <x-modal name="review-approve-{{ $review->id }}" maxWidth="lg" focusable>
                    <form method="POST" action="{{ route('platform.lkm.reviews.approve', $review) }}" class="p-6"
                          x-data="{
                              busy: false,
                              until: @js($review->claimed['pa_valid_until'] ?? ''),
                              pa: @js($review->claimed['pa_number'] ?? ''),
                              name: @js($review->claimed['full_name'] ?? ''),
                              cur: @js(['until' => $liveCurrent['pa_valid_until'] ?? '', 'pa' => $liveCurrent['pa_number'] ?? '', 'name' => $liveCurrent['full_name'] ?? '']),
                              fields: @js($review->claimedFields()),
                              fmt(v) { if (! v) return '—'; const d = new Date(v + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) },
                              get changes() {
                                  const out = [];
                                  if (this.until !== this.cur.until) out.push(['PA valid until', this.fmt(this.cur.until), this.fmt(this.until)]);
                                  if (this.fields.includes('pa_number') && this.pa !== this.cur.pa) out.push(['PA number', this.cur.pa, this.pa]);
                                  if (this.fields.includes('full_name') && this.name !== this.cur.name) out.push(['Name', this.cur.name, this.name]);
                                  return out;
                              },
                          }"
                          @submit="busy = true">
                        @csrf
                        <h2 class="text-[18px] font-extrabold tracking-tight">Update the LKM record for {{ $person->name }}?</h2>
                        <p class="mt-1 text-[13px] text-brand-ink/60">Check the evidence first. The values are what the counselor claims; change them if the evidence shows something different.</p>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label for="until-{{ $review->id }}" class="{{ $label }}">PA valid until</label>
                                <input id="until-{{ $review->id }}" type="date" name="pa_valid_until" x-model="until" required class="{{ $input }}">
                                <p class="mt-1 text-[11.5px] text-brand-ink/50">Valid through the whole of this day.</p>
                            </div>
                            @if (in_array('pa_number', $review->claimedFields(), true))
                                <div>
                                    <label for="pa-{{ $review->id }}" class="{{ $label }}">PA number</label>
                                    <input id="pa-{{ $review->id }}" type="text" name="pa_number" x-model="pa" maxlength="7" required class="{{ $input }} font-mono uppercase">
                                </div>
                            @endif
                            @if (in_array('full_name', $review->claimedFields(), true))
                                <div>
                                    <label for="name-{{ $review->id }}" class="{{ $label }}">Full name</label>
                                    <input id="name-{{ $review->id }}" type="text" name="full_name" x-model="name" maxlength="255" required class="{{ $input }}">
                                    <p class="mt-1 text-[11.5px] text-brand-ink/50">Name must match the counselor’s registration name exactly. Changing it affects verification.</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 rounded-xl bg-brand-ink/[.04] px-4 py-3 text-[13px]" aria-live="polite">
                            <p class="font-bold">What will change</p>
                            <ul class="mt-1.5 space-y-1" x-show="changes.length">
                                <template x-for="change in changes" :key="change[0]">
                                    <li><span class="font-semibold" x-text="change[0] + ':'"></span> <span x-text="change[1]"></span> → <span class="font-semibold" x-text="change[2]"></span></li>
                                </template>
                            </ul>
                            <p class="mt-1.5 text-brand-ink/60" x-show="! changes.length" x-cloak>Nothing — the record already has these values.</p>
                        </div>

                        <ul class="mt-4 space-y-2 text-[13px] leading-snug">
                            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The LKM record is updated, and the change is saved in the audit log.</li>
                            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> {{ $person->name }}’s profile takes the new PA date from the record.</li>
                            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> They are emailed.</li>
                            @if ($suspended)
                                <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> They stay <strong>suspended</strong>. Lifting that is a separate decision on the Counselors page.</li>
                            @endif
                        </ul>

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="$dispatch('close-modal', 'review-approve-{{ $review->id }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                            <button type="submit" :disabled="busy || ! changes.length" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40">
                                <span x-show="! busy">Update record</span><span x-show="busy" x-cloak>Updating…</span>
                            </button>
                        </div>
                    </form>
                </x-modal>

                <x-platform.reason-modal name="review-reject-{{ $review->id }}" :action="route('platform.lkm.reviews.reject', $review)" :bag="'reject-'.$review->id"
                    :title="'Reject the request from '.$person->name.'?'" textarea-id="reject-reason-{{ $review->id }}" submit-label="Reject request" busy-label="Rejecting…"
                    label="Reason (the counselor is emailed this)" placeholder="Tell the counselor what was wrong and what to send next time."
                    :templates="[
                        'Evidence unclear' => 'The evidence is not clear enough to read. Please send a clearer photo or PDF of your renewed practising certificate.',
                        'Dates do not match' => 'The date on the evidence does not match the date you gave. Please check it and send a new request.',
                        'Evidence needed' => 'We need a copy of your renewed practising certificate to update your record. Please attach it and send a new request.',
                    ]">
                    <p class="mt-1 text-[13px] text-brand-ink/60">The LKM record stays as it is. {{ $person->name }} is emailed the reason and can send a new request.</p>
                </x-platform.reason-modal>
            @endif
        @empty
            <x-platform.panel class="py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <p class="mt-4 text-[15px] font-bold">{{ $pending ? 'No pending review requests from counselors.' : 'No review requests have been decided yet.' }}</p>
                <p class="mt-1 text-[13px] text-brand-ink/55">{{ $pending ? 'When a counselor renews their practising certificate and asks for a re-check, it shows up here.' : 'Decided requests are listed here with what changed.' }}</p>
            </x-platform.panel>
        @endforelse

        @if ($reviews->hasPages())
            <div class="mt-5">{{ $reviews->links() }}</div>
        @endif
    </div>

    <x-platform.toast />
</x-platform-layout>
