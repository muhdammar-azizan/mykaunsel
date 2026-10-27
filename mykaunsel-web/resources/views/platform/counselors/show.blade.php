@php
    use App\Enums\VerificationStatus;
    use App\Models\CounselorDocument;

    $status = $profile->verification_status;
    $independent = $profile->isIndependent();
    $managed = in_array($status, [VerificationStatus::Approved, VerificationStatus::Suspended], true);
    $name = $profile->user->name;
    $local = fn ($value, $format = 'j M Y, g:i A') => $value ? $value->copy()->timezone($timezone)->format($format) : '—';

    $backTab = match ($status) {
        VerificationStatus::Approved => 'active',
        VerificationStatus::Suspended => 'suspended',
        VerificationStatus::Rejected => 'rejected',
        default => null,
    };

    $sourceLabel = ['platform' => 'a Platform Admin', 'complaint' => 'a complaint', 'system' => 'the system (practising certificate expired)'][$profile->suspension_source] ?? 'a Platform Admin';

    $organizations = $profile->memberships
        ->filter(fn ($m) => $m->organization && $m->role === \App\Enums\MembershipRole::Counselor)
        ->values();

    $paExpired = \App\Support\PaValidity::isExpired($profile->pa_valid_until);
    $paSoon = \App\Support\PaValidity::expiresWithin($profile->pa_valid_until, \App\Models\CounselorProfile::PA_EXPIRING_DAYS);
    $paLeft = \App\Support\PaValidity::daysLeft($profile->pa_valid_until);

    $kbDuplicateAttempts = $profile->kbAttempts;

    $pending = $status === VerificationStatus::Pending;
    $reviewable = $independent && $pending;
@endphp

<x-platform-layout :title="$name">
    <div x-data="docViewer()" @keydown.escape.window="close()">

        <a href="{{ route('platform.counselors', array_filter(['tab' => $backTab])) }}"
           class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Counselors
        </a>

        {{-- Header --}}
        <header class="mt-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-[24px] font-extrabold leading-tight tracking-tightest">{{ $name }}</h1>
                <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 font-mono text-[12px] font-semibold text-brand-ink/70">{{ $profile->kb_number }}</span>
                <x-platform.org-status-pill :status="$status === VerificationStatus::Approved ? 'active' : $status->value" />
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $independent ? 'bg-brand-blue/10 text-brand-blue' : 'bg-brand-yellow/20 text-[#8A6210]' }}">{{ $independent ? 'Independent' : 'Organization' }}</span>
                @if ($profile->submission_count >= 2)
                    <span class="inline-flex items-center rounded-full bg-brand-blue px-2.5 py-0.5 text-[11px] font-bold text-white">Resubmitted</span>
                @endif
                @if ($kbDuplicateAttempts->isNotEmpty())
                    <span class="inline-flex items-center rounded-full bg-brand-red px-2.5 py-0.5 text-[11px] font-bold text-white">Duplicate KB attempt</span>
                @endif
            </div>
            <p class="mt-1.5 text-[13.5px] text-brand-ink/60">
                @if ($independent)
                    Submitted {{ $local($profile->submittedAtOrCreated()) }} ({{ $profile->submittedAtOrCreated()->diffForHumans() }}) · Attempt {{ $profile->submission_count }}
                @else
                    Added by their organization on {{ $local($profile->created_at, 'j M Y') }} — their KB and PA were verified then, so there is no approval step here.
                @endif
            </p>
        </header>

        {{-- Earlier decisions --}}
        @if ($status === VerificationStatus::Suspended)
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-4 text-[13.5px] leading-relaxed">
                <p class="font-bold text-brand-red">Suspended {{ $profile->suspended_at ? 'on '.$local($profile->suspended_at, 'j M Y') : '' }} by {{ $sourceLabel }}{{ $profile->suspender ? ' ('.$profile->suspender->name.')' : '' }}</p>
                <p class="mt-1 text-brand-ink/80">
                    @if ($profile->suspension_reason !== null)
                        {{ $profile->suspension_reason }}
                    @elseif ($profile->suspension_source === 'complaint')
                        The reason was kept with the complaint and has been erased under its retention rule.
                    @else
                        No reason recorded.
                    @endif
                    @if ($profile->suspension_complaint_id)
                        <a href="{{ route('platform.complaints.show', $profile->suspension_complaint_id) }}" class="ml-1 font-semibold text-brand-blue hover:underline">See complaint #{{ $profile->suspension_complaint_id }} →</a>
                    @endif
                </p>
            </div>
        @endif
        @if ($profile->rejection_reason && in_array($status, [VerificationStatus::Rejected, VerificationStatus::Pending], true))
            <div class="mt-4 rounded-2xl border border-brand-ink/10 bg-white px-5 py-4 text-[13.5px] leading-relaxed">
                <p class="font-bold">
                    {{ $status === VerificationStatus::Pending ? 'Rejected earlier' : 'Rejected' }}
                    @if ($profile->reviewed_at) on {{ $local($profile->reviewed_at, 'j M Y') }} @endif
                    @if ($profile->reviewer) by {{ $profile->reviewer->name }} @endif
                </p>
                <p class="mt-1 text-brand-ink/75">{{ $profile->rejection_reason }}</p>
            </div>
        @endif

        {{-- Practising certificate warning --}}
        @if ($managed && ($paExpired || $paSoon))
            <div class="mt-4 flex gap-3 rounded-2xl border px-5 py-4 text-[13.5px] leading-relaxed {{ $paExpired ? 'border-brand-red/25 bg-brand-red/[.06] text-brand-ink/85' : 'border-brand-yellow/50 bg-brand-yellow/15 text-[#6B4C0C]' }}" role="status">
                <svg class="mt-0.5 shrink-0 {{ $paExpired ? 'text-brand-red' : '' }}" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <p>
                    @if ($paExpired)
                        <span class="font-bold text-brand-red">Practising certificate expired on {{ $profile->pa_valid_until->format('j M Y') }}.</span>
                        This counselor cannot be listed or reactivated until a valid PA is on record.
                    @else
                        <span class="font-bold">Practising certificate runs out {{ $paLeft === 0 ? 'today' : 'in '.$paLeft.' '.\Illuminate\Support\Str::plural('day', $paLeft) }} ({{ $profile->pa_valid_until->format('j M Y') }}).</span>
                        It stays valid through that whole day; after it the counselor is suspended automatically.
                    @endif
                </p>
            </div>
        @endif

        @if ($managed)
            <p class="mt-4 inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold {{ $listable ? 'bg-emerald-50 text-emerald-700' : 'bg-brand-ink/[.06] text-brand-ink/65' }}">
                <span class="h-2 w-2 rounded-full {{ $listable ? 'bg-emerald-500' : 'bg-brand-ink/35' }}"></span>
                {{ $listable ? 'Listed in search — clients can find and book this counselor.' : 'Not listed in search.' }}
            </p>
        @endif

        @if (session('error'))
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->document->any())
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ $errors->document->first() }}</div>
        @endif

        {{-- Decision (independent applicants only: organization counselors have no approval step) --}}
        @if ($independent)
            <x-platform.panel class="mt-5">
                @if ($reviewable)
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-[14px] font-bold">Decision</h2>
                            <p class="mt-0.5 text-[13px] text-brand-ink/60">Approving lists the counselor and lets them open slots. Rejecting sends your reason, and the documents to replace, to the applicant.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-data @click="$dispatch('open-modal', 'reject-counselor')"
                                    class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Reject</button>
                            <button type="button" x-data @click="$dispatch('open-modal', 'approve-counselor')"
                                    class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Approve</button>
                        </div>
                    </div>
                    @if ($readiness['blockers'])
                        <ul class="mt-3 space-y-1 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13px] text-brand-ink/80">
                            <li class="font-bold text-brand-red">Cannot be approved yet:</li>
                            @foreach ($readiness['blockers'] as $blocker)<li class="flex gap-2"><span aria-hidden="true">!</span> {{ $blocker }}</li>@endforeach
                        </ul>
                    @endif
                @else
                    <h2 class="text-[14px] font-bold">Decision</h2>
                    <p class="mt-0.5 text-[13px] text-brand-ink/60">
                        {{ $status === VerificationStatus::Rejected ? 'Rejected' : 'Approved' }}
                        @if ($profile->reviewed_at) on {{ $local($profile->reviewed_at, 'j M Y, g:i A') }} @endif
                        @if ($profile->reviewer) by {{ $profile->reviewer->name }} @endif
                        — this application is closed.
                    </p>
                @endif
            </x-platform.panel>
        @endif

        {{-- Looking after an approved counselor: suspend / reactivate / PA reminder --}}
        @if ($managed)
            <x-platform.panel class="mt-5">
                <div id="manage" class="flex scroll-mt-6 flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-[14px] font-bold">Manage counselor</h2>
                        <p class="mt-0.5 text-[13px] text-brand-ink/60">
                            @if ($status === VerificationStatus::Approved)
                                Suspending removes them from search and stops new slots. You choose what happens to sessions already booked.
                            @else
                                Reactivating lists them again. It is blocked while their practising certificate is expired — the LKM record is checked first.
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($paExpired || $paSoon)
                            <form method="POST" action="{{ route('platform.counselors.renewal-reminder', $profile) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5 disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="!busy">Send renewal reminder</span><span x-show="busy" x-cloak>Sending…</span>
                                </button>
                            </form>
                        @endif
                        @if ($status === VerificationStatus::Approved)
                            <button type="button" x-data @click="$dispatch('open-modal', 'suspend-counselor')"
                                    class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Suspend counselor</button>
                        @else
                            <button type="button" x-data @click="$dispatch('open-modal', 'reactivate-counselor')"
                                    class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Reactivate counselor</button>
                        @endif
                    </div>
                </div>

                @if ($status === VerificationStatus::Suspended && $paExpired)
                    <div class="mt-3 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13px] text-brand-ink/80">
                        <p><span class="font-bold text-brand-red">Practising certificate expired on {{ $profile->pa_valid_until->format('j M Y') }}.</span>
                            Reactivation needs a valid PA on record. When the counselor renews, their re-check request (with evidence) waits under LKM Directory → Review Requests; once the LKM record shows the new date, reactivating refreshes it automatically.</p>
                    </div>
                @endif

                @if ($pendingLkmReview)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-brand-blue/[.07] px-4 py-3 text-[13px]">
                        <p><span class="font-bold text-brand-blue">Asked for their LKM record to be checked again</span> {{ $pendingLkmReview->created_at->diffForHumans() }}: PA valid until {{ \Illuminate\Support\Carbon::parse($pendingLkmReview->claimed['pa_valid_until'])->format('j M Y') }}.</p>
                        <a href="{{ route('platform.lkm.reviews') }}#review-{{ $pendingLkmReview->id }}" class="font-semibold text-brand-blue hover:underline">Review the request →</a>
                    </div>
                @endif

                @if ($devTools)
                    <form method="POST" action="{{ route('platform.counselors.dev.lkm-date', $profile) }}" class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-brand-ink/25 px-4 py-3 text-[12.5px]">
                        @csrf
                        <span class="rounded-full bg-brand-ink/10 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide">Dev only</span>
                        <label for="dev-lkm-date" class="text-brand-ink/65">Set the LKM record's PA valid-until date (a shortcut for local testing; real renewals come as review requests):</label>
                        <input id="dev-lkm-date" type="date" name="date" required value="{{ now()->addYear()->toDateString() }}" class="rounded-lg border border-brand-ink/15 px-2 py-1 text-[12.5px]">
                        <button type="submit" class="rounded-full border border-brand-ink/20 px-3 py-1 font-semibold transition hover:bg-brand-ink/5">Set date</button>
                    </form>
                @endif
            </x-platform.panel>
        @endif

        <div class="mt-5 grid gap-5 lg:grid-cols-2">

            {{-- LKM verification (independent applicants) --}}
            @if ($lkm)
                <x-platform.panel id="lkm" title="LKM verification" class="scroll-mt-6 lg:col-span-2">
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[12.5px] text-brand-ink/55">What the applicant submitted, next to the LKM directory record for {{ $profile->kb_number }}. Checked live.
                            @if ($lkmRecordId)<a href="{{ route('platform.lkm.show', $lkmRecordId) }}" class="ml-1 font-semibold text-brand-blue hover:underline">Open the LKM record →</a>@endif</p>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold',
                            'bg-emerald-50 text-emerald-700' => $lkm->tone() === 'good',
                            'bg-brand-yellow/25 text-[#7A5410]' => $lkm->tone() === 'warn',
                            'bg-brand-red/10 text-brand-red' => $lkm->tone() === 'bad',
                        ])>{{ $lkm->label() }}</span>
                        @if ($reviewable)
                            <form method="POST" action="{{ route('platform.counselors.lkm-recheck', $profile) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/15 px-4 text-[13px] font-semibold transition hover:border-brand-ink hover:bg-brand-ink hover:text-white disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="!busy">Re-run LKM check</span><span x-show="busy" x-cloak>Checking…</span>
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($lkm->problems)
                        <ul class="mt-3 space-y-1.5 rounded-2xl {{ $lkm->blocksApproval() ? 'bg-brand-red/[.06]' : 'bg-brand-yellow/15' }} px-4 py-3 text-[13px]">
                            @foreach ($lkm->problems as $problem)
                                <li class="flex gap-2"><span aria-hidden="true">!</span> {{ $problem }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-3 overflow-hidden rounded-xl border border-brand-ink/10">
                        <table class="w-full text-left text-[13.5px]">
                            <thead class="bg-cream">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">Field</th>
                                    <th scope="col" class="px-4 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">Applicant submitted</th>
                                    <th scope="col" class="px-4 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">LKM record</th>
                                    <th scope="col" class="w-10 px-2 py-2.5"><span class="sr-only">Result</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lkm->fields as $field)
                                    <tr class="border-t border-brand-ink/[.07]">
                                        <td class="px-4 py-2.5 font-semibold">{{ $field['label'] }}</td>
                                        <td class="px-4 py-2.5">{{ $field['submitted'] ?? '—' }}</td>
                                        <td class="px-4 py-2.5 {{ $field['state'] === 'mismatch' ? 'font-semibold text-brand-red' : '' }}">{{ $field['record'] ?? '—' }}</td>
                                        <td class="px-2 py-2.5 text-center">
                                            @if ($field['state'] === 'match')
                                                <span class="text-emerald-600" title="Matches" aria-label="Matches">✓</span>
                                            @elseif ($field['state'] === 'mismatch')
                                                <span class="font-bold text-brand-red" title="Does not match" aria-label="Does not match">✕</span>
                                            @else
                                                <span class="text-brand-ink/30" aria-hidden="true">–</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-platform.panel>
            @endif

            {{-- Documents (independent applicants) --}}
            @if ($independent)
                <x-platform.panel id="documents" title="Documents" class="scroll-mt-6 lg:col-span-2">
                    <p class="mt-1 text-[12.5px] text-brand-ink/55">Kept privately. Each time you open one it is recorded in the audit log.</p>

                    <ul class="mt-3 divide-y divide-brand-ink/[.07] rounded-xl border border-brand-ink/10">
                        @foreach (CounselorDocument::TYPES as $type => $label)
                            @php $doc = $documents->get($type); @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-[14px] font-semibold">{{ $label }}</p>
                                    @if ($doc && $doc->hasFile())
                                        <p class="truncate text-[12.5px] text-brand-ink/55">
                                            {{ $doc->original_name ?? 'Uploaded file' }}
                                            @if ($doc->size_bytes) · {{ number_format($doc->size_bytes / 1024) }} KB @endif
                                            · uploaded {{ $local($doc->uploaded_at) }}
                                        </p>
                                    @elseif ($doc && $doc->isPurged())
                                        <p class="text-[12.5px] text-brand-ink/55">Removed on {{ $local($doc->purged_at, 'j M Y') }} under the retention rules.</p>
                                    @else
                                        <p class="text-[12.5px] text-brand-ink/55">Not uploaded yet.</p>
                                    @endif
                                    @if ($doc?->note)
                                        <p class="mt-1 text-[12.5px] text-brand-red">Note: {{ $doc->note }}</p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    <x-platform.doc-chip :document="$doc" :label="'Status'" />
                                    @if ($doc && $doc->hasFile())
                                        <button type="button" @click="open(@js(route('platform.counselors.documents.link', [$profile, $doc])), @js($label))"
                                                class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/15 px-4 text-[13px] font-semibold transition hover:border-brand-ink hover:bg-brand-ink hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">View</button>
                                    @endif
                                </div>

                                @if ($reviewable && $doc && $doc->hasFile())
                                    <form method="POST" action="{{ route('platform.counselors.documents.mark', [$profile, $doc]) }}" class="flex w-full flex-wrap items-center gap-2 border-t border-brand-ink/[.07] pt-3">
                                        @csrf @method('PATCH')
                                        <label for="mark-{{ $doc->id }}" class="sr-only">Mark {{ $label }}</label>
                                        <select id="mark-{{ $doc->id }}" name="status" class="rounded-full border border-brand-ink/10 bg-white py-1.5 pl-3 pr-8 text-[13px] focus:border-brand-blue focus:ring-brand-blue">
                                            <option value="ok" @selected($doc->status !== CounselorDocument::STATUS_NEEDS_REPLACEMENT)>OK</option>
                                            <option value="needs_replacement" @selected($doc->status === CounselorDocument::STATUS_NEEDS_REPLACEMENT)>Needs replacement</option>
                                        </select>
                                        <label for="mark-note-{{ $doc->id }}" class="sr-only">Short note for the applicant</label>
                                        <input id="mark-note-{{ $doc->id }}" type="text" name="note" maxlength="255" value="{{ $doc->note }}" placeholder="Short note for the applicant (optional)"
                                               class="min-w-[220px] flex-1 rounded-full border border-brand-ink/10 bg-white px-4 py-1.5 text-[13px] focus:border-brand-blue focus:ring-brand-blue">
                                        <button type="submit" class="inline-flex min-h-[34px] items-center rounded-full bg-brand-blue px-4 text-[13px] font-semibold text-white transition hover:bg-brand-deep">Save mark</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-platform.panel>

                {{-- Duplicate check --}}
                <x-platform.panel title="Duplicate KB check">
                    @if ($kbDuplicateAttempts->isEmpty())
                        <p class="mt-2 text-[13.5px] text-brand-ink/60">No one has tried to register {{ $profile->kb_number }} again.</p>
                    @else
                        <p class="mt-1 text-[12.5px] text-brand-ink/55">Blocked attempts to register this account's KB number again. The person trying is never told who owns it.</p>
                        <ul class="mt-3 divide-y divide-brand-ink/[.07] rounded-xl border border-brand-ink/10">
                            @foreach ($kbDuplicateAttempts as $attempt)
                                <li class="px-4 py-2.5 text-[13px]">
                                    <p class="font-semibold">{{ $attempt->submitted_name }}</p>
                                    <p class="text-[12px] text-brand-ink/55">{{ $attempt->kb_number }} · IP {{ $attempt->ip ?? 'unknown' }} · {{ $local($attempt->created_at) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-platform.panel>

                {{-- Applicant --}}
                <x-platform.panel title="Applicant">
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="Email">{{ $profile->user->email }}</x-platform.detail-row>
                        <x-platform.detail-row label="Registered">{{ $local($profile->created_at) }}</x-platform.detail-row>
                        <x-platform.detail-row label="Documents submitted">{{ $profile->submitted_at ? $local($profile->submitted_at) : 'Not yet' }}</x-platform.detail-row>
                        <x-platform.detail-row label="Attempt">{{ $profile->submission_count }}</x-platform.detail-row>
                    </dl>
                </x-platform.panel>
            @endif

            {{-- Profile (read-only) --}}
            @if ($managed)
                <x-platform.panel title="Profile">
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="Type">
                            {{ $independent ? 'Independent' : 'Organization counselor' }}
                        </x-platform.detail-row>
                        <x-platform.detail-row label="Works in">
                            @forelse ($organizations as $membership)
                                <span class="block">{{ $membership->organization->org_type === \App\Enums\OrgType::Platform ? 'Independent practice' : $membership->organization->name }}</span>
                            @empty
                                —
                            @endforelse
                        </x-platform.detail-row>
                        <x-platform.detail-row label="Languages">{{ $profile->languages->pluck('language')->implode(', ') ?: '—' }}</x-platform.detail-row>
                        <x-platform.detail-row label="Specializations">{{ $profile->specializations->pluck('specialization')->implode(', ') ?: '—' }}</x-platform.detail-row>
                        <x-platform.detail-row label="Display name (organization)">{{ $profile->display_name_org ?? '—' }}</x-platform.detail-row>
                        <x-platform.detail-row label="Display name (independent)">{{ $profile->display_name_independent ?? '—' }}</x-platform.detail-row>
                    </dl>
                </x-platform.panel>

                <x-platform.panel title="Credentials">
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="KB number"><span class="font-mono">{{ $profile->kb_number }}</span></x-platform.detail-row>
                        <x-platform.detail-row label="PA number"><span class="font-mono">{{ $profile->pa_number }}</span></x-platform.detail-row>
                        <x-platform.detail-row label="PA valid until"><x-platform.pa-badge :date="$profile->pa_valid_until" :long="true" /></x-platform.detail-row>
                        <x-platform.detail-row label="LKM record">
                            @if ($lkmRecordId)<a href="{{ route('platform.lkm.show', $lkmRecordId) }}" class="text-brand-blue hover:underline">Open in LKM Directory →</a>@else<span class="text-brand-ink/55">Not in the directory</span>@endif
                        </x-platform.detail-row>
                        <x-platform.detail-row label="Verification">{{ $independent ? 'Verified by the platform' : 'Verified by their organization' }}</x-platform.detail-row>
                    </dl>
                </x-platform.panel>

                {{-- Statistics: counts only --}}
                <x-platform.panel title="Activity at a glance" class="lg:col-span-2">
                    <p class="mt-1 text-[12.5px] text-brand-ink/55">Numbers only. Client names and what happens in sessions are never shown on this page.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['Total sessions', $stats['total_sessions'], 'Not counting cancelled', null],
                            ['Upcoming sessions', $stats['upcoming_sessions'], 'Confirmed, from today', null],
                            ['Open complaints', $stats['open_complaints'], 'Open or under review', $stats['open_complaints'] > 0],
                            ['Past complaints', $stats['past_complaints'], 'Resolved or dismissed', null],
                        ] as [$label, $value, $hint, $alert])
                            <div class="rounded-2xl border border-brand-ink/10 px-4 py-3.5">
                                <p class="text-[12.5px] font-medium text-brand-ink/55">{{ $label }}</p>
                                <p class="mt-1 text-[24px] font-extrabold leading-none tracking-tightest {{ $alert ? 'text-brand-red' : '' }}">{{ number_format($value) }}</p>
                                <p class="mt-1.5 text-[12px] text-brand-ink/45">{{ $hint }}</p>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('platform.complaints', ['counselor' => $profile->id]) }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-blue hover:underline">
                        View complaints involving this counselor →
                    </a>
                </x-platform.panel>
            @endif
        </div>

        @if ($independent)
            <x-platform.review-checklist class="mt-5"
                :items="$checklist"
                :open="$pending"
                :endpoint="route('platform.counselors.checklist', $profile)" />

            <div class="mt-5 max-w-[760px]">
                <x-platform.review-notes :notes="$notes"
                    :action="route('platform.counselors.notes.store', $profile)"
                    placeholder="e.g. Called the applicant — the ID photo will be re-sent." />
            </div>
        @endif

        {{-- Activity --}}
        <div class="mt-5 max-w-[760px]">
            <x-platform.activity-timeline :timeline="$timeline" :history-url="route('platform.audit-log', ['target_type' => 'counselor', 'q' => $name])" />
        </div>

        {{-- Document viewer --}}
        <div x-show="shown" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="doc-viewer-title">
            <div class="absolute inset-0 bg-brand-ink/60" @click="close()"></div>
            <div class="absolute inset-4 flex flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:inset-10">
                <header class="flex items-center justify-between gap-3 border-b border-brand-ink/10 px-5 py-3">
                    <div class="min-w-0">
                        <h2 id="doc-viewer-title" class="truncate text-[15px] font-extrabold" x-text="title"></h2>
                        <p class="text-[12px] text-brand-ink/50">Confidential. This view is recorded in the audit log.</p>
                    </div>
                    <button type="button" x-ref="close" @click="close()" class="grid h-9 w-9 shrink-0 place-items-center rounded-full transition hover:bg-brand-ink/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue" aria-label="Close document">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="relative min-h-0 flex-1 bg-brand-ink/[.04]">
                    <div x-show="loading" class="absolute inset-0 grid place-items-center text-[13px] text-brand-ink/55">
                        <span class="animate-pulse">Loading document…</span>
                    </div>
                    <p x-show="error" x-cloak class="absolute inset-0 grid place-items-center px-6 text-center text-[14px] font-semibold text-brand-red" role="alert" x-text="error"></p>
                    <template x-if="src && mime === 'application/pdf'"><iframe :src="src" title="Document" class="h-full w-full" @load="loading = false"></iframe></template>
                    <template x-if="src && mime !== 'application/pdf'"><div class="grid h-full place-items-center overflow-auto p-4"><img :src="src" alt="Document" class="max-h-full max-w-full object-contain" @load="loading = false"></div></template>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('docViewer', () => ({
                shown: false, loading: false, error: '', src: '', mime: '', title: '', trigger: null,

                // Ask for a short-lived signed link, then show the file. Nothing is public.
                async open(linkUrl, title) {
                    this.trigger = document.activeElement;
                    this.shown = true; this.loading = true; this.error = ''; this.src = ''; this.title = title;
                    this.$nextTick(() => this.$refs.close && this.$refs.close.focus());

                    try {
                        const res = await fetch(linkUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(data.message || 'Could not open this document.');
                        this.mime = data.mime;
                        this.src = data.url;
                    } catch (e) {
                        this.loading = false;
                        this.error = e.message || 'Could not open this document.';
                    }
                },

                close() {
                    if (!this.shown) return;
                    this.shown = false; this.src = ''; this.error = '';
                    this.$nextTick(() => this.trigger && this.trigger.focus());
                },
            }));
        });
    </script>

    @if ($reviewable)
        {{-- Approve --}}
        <x-platform.confirm-modal name="approve-counselor" :action="route('platform.counselors.approve', $profile)"
            :title="'Approve '.$name.'?'" submit-label="Approve counselor" busy-label="Approving…">
            <p class="mt-1 text-[13px] text-brand-ink/60">The LKM record is checked again the moment you confirm. This is what happens next:</p>

            <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The counselor becomes <strong>approved and listed</strong> in search, and can open slots.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> {{ $name }} is emailed.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Their PA valid-until date is refreshed from the LKM record if it changed, and the change is saved in the audit log.</li>
            </ul>

            @if ($readiness['blockers'])
                <ul class="mt-4 space-y-1 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13px]">
                    <li class="font-bold text-brand-red">Approval will be blocked until this is fixed:</li>
                    @foreach ($readiness['blockers'] as $blocker)<li class="flex gap-2"><span aria-hidden="true">!</span> {{ $blocker }}</li>@endforeach
                </ul>
            @endif

            @if ($readiness['needs_acknowledgement'])
                <div class="mt-4 rounded-xl bg-brand-yellow/15 px-4 py-3 text-[13px] text-[#6B4C0C]">
                    <ul class="space-y-1">
                        @foreach ($readiness['lkm']->problems as $problem)<li class="flex gap-2"><span aria-hidden="true">!</span> {{ $problem }}</li>@endforeach
                    </ul>
                    <label class="mt-3 flex cursor-pointer items-start gap-2.5 font-semibold">
                        <input type="checkbox" name="acknowledge" value="1" required class="mt-0.5 h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-blue focus:ring-brand-blue">
                        <span>I have reviewed this discrepancy. It is recorded in the audit log.</span>
                    </label>
                </div>
            @endif
        </x-platform.confirm-modal>

        {{-- Reject --}}
        <x-platform.reason-modal name="reject-counselor" :action="route('platform.counselors.reject', $profile)"
            :title="'Reject '.$name.'?'" textarea-id="reject-counselor-reason" :rows="5"
            placeholder="Tell the applicant what to fix."
            :templates="[
                'Documents unclear' => 'One or more of your documents is unclear or cut off. Please upload clear, complete copies and apply again.',
                'LKM record mismatch' => 'Your details do not match the LKM record for this KB number. Please check them and apply again.',
                'PA expired' => 'Your practising certificate (PA) has expired. Please renew it with LKM, then apply again.',
                'Other' => '',
            ]"
            submit-label="Reject application" busy-label="Rejecting…">
            <p class="mt-1 text-[13px] text-brand-ink/60">The reason below is <strong>sent to the applicant</strong> by email.</p>

            <fieldset class="mt-4">
                <legend class="text-[12.5px] font-semibold text-brand-ink/70">Which documents must they upload again? <span class="font-normal text-brand-ink/50">(they only replace these)</span></legend>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    @foreach (CounselorDocument::TYPES as $type => $typeLabel)
                        @php $d = $documents->get($type); @endphp
                        <label class="inline-flex items-center gap-2 text-[13.5px] {{ $d && $d->hasFile() ? 'cursor-pointer' : 'opacity-50' }}">
                            <input type="checkbox" name="documents[]" value="{{ $type }}" @checked($d && $d->needsReplacement()) @disabled(! ($d && $d->hasFile()))
                                   class="h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-red focus:ring-brand-red">
                            {{ $typeLabel }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </x-platform.reason-modal>
    @endif

    @if ($status === VerificationStatus::Approved)
        {{-- Suspend --}}
        <x-platform.counselor-suspend-modal name="suspend-counselor" :action="route('platform.counselors.suspend', $profile)"
            :counselor-name="$name" :independent="$independent" :upcoming="$stats['upcoming_sessions']" :pending-requests="$stats['pending_requests']" />
    @endif

    @if ($status === VerificationStatus::Suspended)
        {{-- Reactivate --}}
        <x-platform.confirm-modal name="reactivate-counselor" :action="route('platform.counselors.reactivate', $profile)"
            :title="'Reactivate '.$name.'?'" submit-label="Reactivate" busy-label="Reactivating…">
            <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The LKM record is checked first and the PA date refreshed from it. If the record is not active or the PA is still expired, this is blocked.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> They are listed again and can open slots; slots they opened earlier are as they left them.</li>
                <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> {{ $name }} is emailed. The suspension reason stays in the audit log.</li>
            </ul>
        </x-platform.confirm-modal>
    @endif

    <x-platform.toast />
</x-platform-layout>
