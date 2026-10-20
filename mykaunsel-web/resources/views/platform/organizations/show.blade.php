@php
    $org = $organization;
    $isClinic = $org->org_type === \App\Enums\OrgType::Clinic;
    $location = $org->primaryLocation;
    $photos = $location?->locationPhotos->sortBy('sort_order')->values() ?? collect();
    $submitted = $org->submitted_at ?? $org->created_at;

    $domainState = $org->domainCheckState();
    $hasSsm = filled($org->ssm_number);
    $ssmValid = $org->hasValidSsmFormat();
    $verifiedCount = $org->orgDomains->where('dns_verified', true)->count();
@endphp

<x-platform-layout :title="$org->name">
    <a href="{{ route('platform.organizations', ['tab' => match (true) {
            $org->subscription_status === \App\Enums\OrgStatus::Rejected => 'rejected',
            $org->subscription_status !== \App\Enums\OrgStatus::Pending => 'approved',
            $domainState === 'skipped' => 'skipped',
            default => null,
        }]) }}"
       class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Organizations
    </a>

    {{-- Header --}}
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-[24px] font-extrabold leading-tight tracking-tightest">{{ $org->name }}</h1>
            <x-platform.org-type-badge :type="$org->org_type" />
            <x-platform.org-status-pill :status="$org->subscription_status" />
            @if ($org->submission_count >= 2)
                <span class="inline-flex items-center rounded-full bg-brand-blue px-2.5 py-0.5 text-[11px] font-bold text-white">Resubmitted</span>
            @endif
        </div>
        <p class="mt-1.5 text-[13.5px] text-brand-ink/60">
            Submitted {{ $submitted->format('j M Y, g:i A') }} ({{ $submitted->diffForHumans() }})
            · Attempt {{ $org->submission_count }}
        </p>
    </header>

    {{-- Earlier decision, if any --}}
    @if ($org->subscription_status === \App\Enums\OrgStatus::Suspended && $org->suspension_reason)
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-4 text-[13.5px] leading-relaxed">
            <p class="font-bold text-brand-red">Suspended{{ $org->suspended_at ? ' on '.$org->suspended_at->format('j M Y') : '' }}</p>
            <p class="mt-1 text-brand-ink/80">{{ $org->suspension_reason }}</p>
        </div>
    @endif
    @if ($org->rejection_reason && in_array($org->subscription_status, [\App\Enums\OrgStatus::Rejected, \App\Enums\OrgStatus::Pending], true))
        <div class="mt-4 rounded-2xl border border-brand-ink/10 bg-white px-5 py-4 text-[13.5px] leading-relaxed">
            <p class="font-bold">
                {{ $org->subscription_status === \App\Enums\OrgStatus::Pending ? 'Rejected earlier' : 'Rejected' }}
                @if ($org->reviewed_at) on {{ $org->reviewed_at->format('j M Y') }} @endif
                @if ($org->reviewer) by {{ $org->reviewer->name }} @endif
            </p>
            <p class="mt-1 text-brand-ink/75">{{ $org->rejection_reason }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Decision --}}
    <x-platform.panel class="mt-5">
        @if ($org->subscription_status === \App\Enums\OrgStatus::Pending)
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-[14px] font-bold">Decision</h2>
                    <p class="mt-0.5 text-[13px] text-brand-ink/60">Approving activates the organization. Rejecting sends your reason to the applicant.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" x-data @click="$dispatch('open-modal', 'reject-org')"
                            class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Reject</button>
                    <button type="button" x-data @click="$dispatch('open-modal', 'approve-org')"
                            class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Approve</button>
                </div>
            </div>
        @elseif (in_array($org->subscription_status, [\App\Enums\OrgStatus::Active, \App\Enums\OrgStatus::Suspended], true))
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-[14px] font-bold">Manage organization</h2>
                    <p class="mt-0.5 text-[13px] text-brand-ink/60">
                        @if ($org->subscription_status === \App\Enums\OrgStatus::Active)
                            @if ($org->reviewer) Approved by {{ $org->reviewer->name }}. @endif
                            Suspending blocks every member and counselor from this organization. Nothing is deleted.
                        @else
                            Suspended{{ $org->suspended_at ? ' on '.$org->suspended_at->format('j M Y') : '' }}. Reactivating restores access straight away.
                        @endif
                    </p>
                </div>
                @if ($org->subscription_status === \App\Enums\OrgStatus::Active)
                    <button type="button" x-data @click="$dispatch('open-modal', 'suspend-org')"
                            class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Suspend organization</button>
                @else
                    <button type="button" x-data @click="$dispatch('open-modal', 'reactivate-org')"
                            class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Reactivate organization</button>
                @endif
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-[14px] font-bold">Decision</h2>
                    <p class="mt-0.5 text-[13px] text-brand-ink/60">
                        @if ($org->subscription_status === \App\Enums\OrgStatus::Rejected)
                            Rejected
                        @else
                            Approved
                        @endif
                        @if ($org->reviewed_at) on {{ $org->reviewed_at->format('j M Y, g:i A') }} @endif
                        @if ($org->reviewer) by {{ $org->reviewer->name }} @endif
                        — this application is closed.
                    </p>
                </div>
                <div class="flex gap-2 opacity-50">
                    <span class="inline-flex min-h-[42px] cursor-not-allowed items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold">Reject</span>
                    <span class="inline-flex min-h-[42px] cursor-not-allowed items-center rounded-full bg-brand-ink/20 px-6 text-[13.5px] font-semibold">Approve</span>
                </div>
            </div>
        @endif
    </x-platform.panel>

    {{-- Approved organizations: overview, subscription and complaints --}}
    @if ($overview)
        <section class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Overview">
            @foreach ([
                ['Members', number_format($overview['members']), 'Students, staff and employees'],
                ['Counselors', number_format($overview['counselors']), 'Active in this organization'],
                ['Sessions this month', number_format($overview['sessions_this_month']), 'Booked since '.now()->startOfMonth()->format('j M')],
                ['Approved', $overview['approved_at']->format('j M Y'), $overview['approved_at']->diffForHumans()],
            ] as [$label, $value, $hint])
                <div class="rounded-2xl border border-brand-ink/10 bg-white px-5 py-4">
                    <p class="text-[12.5px] font-medium text-brand-ink/55">{{ $label }}</p>
                    <p class="mt-1 text-[24px] font-extrabold leading-none tracking-tightest">{{ $value }}</p>
                    <p class="mt-1.5 text-[12px] text-brand-ink/45">{{ $hint }}</p>
                </div>
            @endforeach
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <x-platform.panel title="Subscription">
                <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                    <x-platform.detail-row label="Tier">
                        @if ($org->subscription_tier)
                            {{ $org->subscription_tier }}
                        @else
                            <span class="inline-flex rounded-full bg-brand-ink/10 px-2.5 py-0.5 text-[11.5px] font-bold text-brand-ink/65">Not set</span>
                        @endif
                    </x-platform.detail-row>
                    @if ($org->subscription_ends_at)
                        <x-platform.detail-row label="Ends">{{ $org->subscription_ends_at->format('j M Y') }}</x-platform.detail-row>
                    @endif
                </dl>
                <p class="mt-2 text-[12.5px] text-brand-ink/50">Plans can be assigned here once Pricing is ready.</p>
            </x-platform.panel>

            <x-platform.panel title="Complaints">
                <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                    <x-platform.detail-row label="Open or under review">
                        <span class="{{ $overview['complaints_open'] > 0 ? 'text-brand-red' : '' }}">{{ $overview['complaints_open'] }}</span>
                    </x-platform.detail-row>
                    <x-platform.detail-row label="All time">{{ $overview['complaints_total'] }}</x-platform.detail-row>
                </dl>
                <a href="{{ route('platform.complaints', ['organization' => $org->id]) }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-blue hover:underline">
                    View complaints involving this organization
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </x-platform.panel>
        </div>
    @endif

    {{-- Review checklist: shared reference for all Platform Admins, never blocks a decision. --}}
    @php $checklistOpen = $org->subscription_status === \App\Enums\OrgStatus::Pending; @endphp
    <x-platform.panel class="mt-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-[14px] font-bold">Review checklist</h2>
            <p class="text-[12.5px] text-brand-ink/55">
                {{ $checklistOpen ? 'Saves automatically and is shared with other admins. For reference only — it does not block approval.' : 'Closed — this application has been decided.' }}
            </p>
        </div>

        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($checklist as $item)
                <li x-data="{
                        on: @js($item['checked']),
                        by: @js($item['by']),
                        at: @js($item['at']),
                        state: 'idle',
                        timer: null,
                        async toggle() {
                            const next = !this.on;
                            const prev = { on: this.on, by: this.by, at: this.at };
                            this.on = next;
                            this.state = 'saving';
                            clearTimeout(this.timer);
                            try {
                                const res = await fetch(@js(route('platform.organizations.checklist', $org)), {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    },
                                    body: JSON.stringify({ key: @js($item['key']), checked: next }),
                                });
                                if (!res.ok) throw new Error('save failed');
                                const data = await res.json();
                                this.by = data.by;
                                this.at = data.at;
                                this.state = 'saved';
                            } catch (e) {
                                Object.assign(this, prev);
                                this.state = 'error';
                            }
                            this.timer = setTimeout(() => this.state = 'idle', 2200);
                        },
                    }">
                    <label class="flex items-start gap-3 rounded-xl border px-4 py-3 transition {{ $checklistOpen ? 'cursor-pointer hover:border-brand-blue/40' : 'cursor-default opacity-70' }}"
                           :class="on ? 'border-brand-blue/30 bg-brand-blue/[.04]' : 'border-brand-ink/10 bg-white'">
                        <input type="checkbox" class="mt-0.5 h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-blue focus:ring-brand-blue"
                               :checked="on" @change="toggle()" @disabled(! $checklistOpen)>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[14px] font-semibold">{{ $item['label'] }}</span>
                            <span class="block text-[12px] text-brand-ink/50">{{ $item['hint'] }}</span>
                            <span x-show="on && by" x-cloak class="mt-1 block text-[11.5px] text-brand-blue">Checked by <span x-text="by"></span> · <span x-text="at"></span></span>
                            <span class="mt-1 block text-[11.5px]" aria-live="polite">
                                <span x-show="state === 'saving'" x-cloak class="text-brand-ink/45">Saving…</span>
                                <span x-show="state === 'error'" x-cloak class="font-semibold text-brand-red">Couldn't save — try again</span>
                            </span>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    </x-platform.panel>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        {{-- Organization info --}}
        <x-platform.panel title="Organization info">
            <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                <x-platform.detail-row label="Type"><x-platform.org-type-badge :type="$org->org_type" /></x-platform.detail-row>
                <x-platform.detail-row label="Estimated size">{{ $org->estimated_size ?? '—' }}</x-platform.detail-row>
                <x-platform.detail-row label="Access model">
                    {{ $org->isClosed() ? 'Closed' : 'Open' }}
                    <span class="block text-[12px] font-normal text-brand-ink/50">
                        {{ $org->isClosed() ? 'Only verified members can join.' : 'Anyone can book without joining.' }}
                    </span>
                </x-platform.detail-row>
                <x-platform.detail-row label="Join method">{{ $org->join_method ? ucwords(str_replace('_', ' ', $org->join_method->value)) : '—' }}</x-platform.detail-row>
            </dl>
        </x-platform.panel>

        {{-- Business registration --}}
        <x-platform.panel title="Business registration (SSM)">
            <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                <x-platform.detail-row label="SSM number">
                    @if ($hasSsm)
                        <span class="font-mono tabular-nums">{{ $org->ssm_number }}</span>
                    @else
                        <span class="inline-flex rounded-full bg-brand-ink/10 px-2.5 py-0.5 text-[11.5px] font-bold text-brand-ink/65">Not provided</span>
                    @endif
                </x-platform.detail-row>
                <x-platform.detail-row label="Requirement">
                    {{ $org->requiresSsm() ? 'Required' : 'Optional' }} for {{ $org->org_type->value }}
                </x-platform.detail-row>
                <x-platform.detail-row label="Format check">
                    @if (! $hasSsm)
                        <span class="{{ $org->requiresSsm() ? 'text-brand-red' : 'text-brand-ink/55' }}">{{ $org->requiresSsm() ? 'Missing — required' : 'Nothing to check' }}</span>
                    @elseif ($ssmValid)
                        <span class="text-emerald-700">12 digits ✓</span>
                    @else
                        <span class="text-brand-red">Not a 12-digit number</span>
                    @endif
                </x-platform.detail-row>
            </dl>
            <a href="https://www.ssm.com.my" target="_blank" rel="noopener noreferrer"
               class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-blue hover:underline">
                Check manually on the SSM website
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
            </a>
        </x-platform.panel>

        {{-- Domain verification (not shown for clinics) --}}
        @unless ($isClinic)
            <x-platform.panel id="domains" title="Domain verification" class="scroll-mt-6 lg:col-span-2">
                @if ($org->orgDomains->isEmpty())
                    <p class="mt-2 text-[13.5px] text-brand-ink/60">No domain was claimed. Members join with an invite code instead.</p>
                @else
                    <p class="mt-1 text-[12.5px] text-brand-ink/55">{{ $verifiedCount }} of {{ $org->orgDomains->count() }} verified</p>

                    @if ($domainState === 'skipped')
                        <div class="mt-3 flex gap-3 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/15 px-4 py-3 text-[13px] leading-relaxed text-[#6B4C0C]">
                            <svg class="mt-0.5 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                            <p><span class="font-bold">Needs a manual check.</span> At least one domain is not DNS-verified. Members will not be matched by email domain until it is verified.</p>
                        </div>
                    @endif

                    @php $canReverify = $org->subscription_status === \App\Enums\OrgStatus::Active; @endphp
                    <x-platform.table :headings="$canReverify ? ['Domain', 'Default role', 'Match', 'DNS status', 'Last checked', ''] : ['Domain', 'Default role', 'Match', 'DNS status', 'Last checked']">
                        @foreach ($org->orgDomains as $domain)
                            <tr class="border-t border-brand-ink/[.07]">
                                <td class="px-4 py-3 text-[14px] font-semibold">{{ $domain->domain }}</td>
                                <td class="px-4 py-3 text-[13.5px] capitalize">{{ str_replace('_', ' ', $domain->default_role->value) }}</td>
                                <td class="px-4 py-3 text-[13.5px] capitalize">{{ $domain->match_type->value }}</td>
                                <td class="px-4 py-3">
                                    @php $dnsState = $dns[$domain->id]['state']; @endphp
                                    @if ($dnsState === 'verified')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11.5px] font-bold text-emerald-700">Verified</span>
                                    @elseif ($dnsState === 'pending')
                                        <span class="inline-flex rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11.5px] font-bold text-[#8A6210]">Pending</span>
                                        <span class="mt-0.5 block text-[11px] text-brand-ink/45">Auto-checks until {{ $dns[$domain->id]['window_ends']->format('j M') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-brand-red/10 px-2.5 py-0.5 text-[11.5px] font-bold text-brand-red">Expired</span>
                                        <span class="mt-0.5 block text-[11px] text-brand-ink/45">Auto-checks stopped</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-[13px] text-brand-ink/65">
                                    @if ($domain->last_checked_at)
                                        {{ $domain->last_checked_at->diffForHumans() }}
                                        <span class="text-brand-ink/40">· {{ $domain->check_attempts }} {{ \Illuminate\Support\Str::plural('check', $domain->check_attempts) }}</span>
                                    @else
                                        Never checked
                                    @endif
                                </td>
                                @if ($canReverify)
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('platform.organizations.dns.check', $org) }}">
                                            @csrf
                                            <input type="hidden" name="domain" value="{{ $domain->id }}">
                                            <button type="submit" class="text-[12.5px] font-semibold text-brand-blue hover:underline">Re-verify</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </x-platform.table>

                    @if ($verifiedCount < $org->orgDomains->count() && in_array($org->subscription_status, [\App\Enums\OrgStatus::Pending, \App\Enums\OrgStatus::Active], true))
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('platform.organizations.dns.check', $org) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy"
                                        class="inline-flex min-h-[38px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="!busy">Check DNS now</span><span x-show="busy" x-cloak>Checking…</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('platform.organizations.dns.remind', $org) }}" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" :disabled="busy"
                                        class="inline-flex min-h-[38px] items-center rounded-full border border-brand-ink/20 px-5 text-[13px] font-semibold transition hover:bg-brand-ink/5 disabled:cursor-wait disabled:opacity-60">
                                    <span x-show="!busy">Send reminder email</span><span x-show="busy" x-cloak>Sending…</span>
                                </button>
                            </form>
                        </div>
                    @endif
                @endif
            </x-platform.panel>
        @endunless

        {{-- Location --}}
        <x-platform.panel title="Location" class="lg:col-span-2">
            @if ($location)
                <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                    <x-platform.detail-row label="Name">{{ $location->name }}</x-platform.detail-row>
                    <x-platform.detail-row label="Address">
                        {{ $location->address }}<br>
                        <span class="font-normal text-brand-ink/65">{{ trim($location->postcode.' '.$location->city.', '.$location->state, ' ,') }}</span>
                    </x-platform.detail-row>
                </dl>
            @elseif ($isClinic)
                <p class="mt-2 text-[13.5px] font-semibold text-brand-red">No location provided — required for clinics.</p>
            @else
                <p class="mt-2 text-[13.5px] text-brand-ink/60">No location provided — can be added later.</p>
            @endif

            @if ($isClinic)
                <h3 class="mt-5 text-[13px] font-bold">Premises photos</h3>
                @if ($photos->isEmpty())
                    <p class="mt-1.5 rounded-xl bg-brand-red/[.06] px-4 py-3 text-[13px] font-semibold text-brand-red">No premises photos — required for clinics.</p>
                @else
                    <div x-data="{ open: null }" @keydown.escape.window="open = null">
                        <ul class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($photos as $i => $photo)
                                <li>
                                    <button type="button" @click="open = @js($photo->photo_url)" class="group block w-full overflow-hidden rounded-xl bg-brand-ink/5 ring-1 ring-brand-ink/10">
                                        <img src="{{ $photo->photo_url }}" alt="Premises photo {{ $i + 1 }}" loading="lazy"
                                             class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-105">
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div x-show="open" x-cloak x-transition.opacity @click.self="open = null"
                             class="fixed inset-0 z-50 grid place-items-center bg-black/75 p-6" role="dialog" aria-modal="true" aria-label="Premises photo">
                            <img :src="open" alt="" class="max-h-[85vh] max-w-full rounded-2xl shadow-2xl">
                            <button type="button" @click="open = null" class="absolute right-5 top-5 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-brand-ink" aria-label="Close photo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                @endif
            @endif
        </x-platform.panel>

        {{-- Admin account --}}
        <x-platform.panel title="Admin account" class="lg:col-span-2">
            @if ($registrant)
                <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                    <x-platform.detail-row label="Name">{{ $registrant->name }}</x-platform.detail-row>
                    <x-platform.detail-row label="Position">{{ $registrantTitle ?? '—' }}</x-platform.detail-row>
                    <x-platform.detail-row label="Work email">
                        {{ $registrant->email }}
                        @if (! $registrant->email_verified_at)
                            <span class="ml-1 rounded-full bg-brand-ink/10 px-2 py-0.5 text-[10.5px] font-bold text-brand-ink/60">Email not verified</span>
                        @endif
                    </x-platform.detail-row>
                    <x-platform.detail-row label="Authority confirmation">
                        @if ($org->authority_confirmed_at)
                            <span class="text-emerald-700">Confirmed</span>
                            <span class="block text-[12px] font-normal text-brand-ink/50">Ticked the box on {{ $org->authority_confirmed_at->format('j M Y, g:i A') }}</span>
                        @else
                            <span class="text-brand-red">Not recorded</span>
                        @endif
                    </x-platform.detail-row>
                    <x-platform.detail-row label="Email vs claimed domain">
                        @if ($isClinic || $org->orgDomains->isEmpty())
                            <span class="text-brand-ink/55">No domain to compare</span>
                        @elseif ($emailMatch)
                            <span class="text-emerald-700">Matches {{ $emailMatch->domain }}</span>
                        @else
                            <span class="text-[#8A6210]">Does not match any claimed domain</span>
                        @endif
                    </x-platform.detail-row>
                </dl>
            @else
                <p class="mt-2 text-[13.5px] text-brand-ink/60">No admin account found for this organization.</p>
            @endif
        </x-platform.panel>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        {{-- Internal notes --}}
        <x-platform.panel id="notes" title="Internal notes" class="scroll-mt-6">
            <p class="mt-0.5 text-[12.5px] text-brand-ink/55">Only Platform Admins can see these. The applicant never does.</p>

            <form method="POST" action="{{ route('platform.organizations.notes.store', $org) }}" class="mt-3" x-data="{ body: @js(old('body', '')), busy: false }" @submit="busy = true">
                @csrf
                <label for="note-body" class="sr-only">New note</label>
                <textarea id="note-body" name="body" x-model="body" rows="3" maxlength="2000" required
                          placeholder="e.g. Called the registrar — confirmed the domain belongs to them."
                          class="block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20"></textarea>
                @error('body', 'note')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                <div class="mt-2 flex justify-end">
                    <button type="submit" :disabled="busy || body.trim().length === 0"
                            class="inline-flex min-h-[38px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40">Add note</button>
                </div>
            </form>

            @if ($notes->isEmpty())
                <p class="mt-4 rounded-xl bg-brand-ink/[.04] px-4 py-5 text-center text-[13px] text-brand-ink/50">No notes yet.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($notes as $note)
                        <li class="rounded-xl border border-brand-ink/10 bg-cream px-4 py-3">
                            <p class="whitespace-pre-line break-words text-[13.5px] leading-relaxed">{{ $note->body }}</p>
                            <p class="mt-2 text-[11.5px] text-brand-ink/50" title="{{ $note->created_at->format('j M Y, g:i A') }}">
                                {{ $note->author?->name ?? 'Deleted user' }} · {{ $note->created_at->diffForHumans() }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-platform.panel>

        {{-- Activity timeline --}}
        <x-platform.panel title="Activity">
            <ol class="mt-3 space-y-0">
                @foreach ($timeline as $event)
                    @php
                        $dot = ['good' => 'bg-emerald-500', 'bad' => 'bg-brand-red', 'info' => 'bg-brand-blue', 'neutral' => 'bg-brand-ink/35'][$event['tone']];
                    @endphp
                    <li class="relative flex gap-3 pb-5 last:pb-0">
                        @unless ($loop->last)
                            <span class="absolute left-[5px] top-4 h-full w-px bg-brand-ink/10" aria-hidden="true"></span>
                        @endunless
                        <span class="relative mt-1.5 h-[11px] w-[11px] shrink-0 rounded-full ring-4 ring-white {{ $dot }}"></span>
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-semibold">{{ $event['title'] }}</p>
                            @if ($event['detail'])
                                <p class="mt-0.5 break-words text-[12.5px] text-brand-ink/65">{{ $event['detail'] }}</p>
                            @endif
                            <p class="mt-0.5 text-[11.5px] text-brand-ink/50" title="{{ $event['at']->format('j M Y, g:i A') }}">
                                {{ $event['actor'] }} · {{ $event['at']->format('j M Y, g:i A') }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-platform.panel>
    </div>

    @if ($org->subscription_status === \App\Enums\OrgStatus::Pending)
        @php
            $unverified = $org->orgDomains->where('dns_verified', false)->count();
            $templates = [
                'SSM number' => 'We could not verify your SSM registration number. Please check it and apply again.',
                'Domain' => 'We could not verify ownership of your domain. Please complete the DNS verification and apply again.',
                'Authority' => 'We could not confirm that you are authorised to register this organization. Please apply again with a work email or supporting document.',
                'Photos' => 'The premises photos are missing or unclear. Please upload clear photos of the clinic and apply again.',
            ];
        @endphp

        {{-- Approve --}}
        <x-modal name="approve-org" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('platform.organizations.approve', $org) }}" x-data="{ busy: false }" @submit="busy = true" class="p-6">
                @csrf
                <h2 class="text-[18px] font-extrabold tracking-tight">Approve {{ $org->name }}?</h2>
                <p class="mt-1 text-[13px] text-brand-ink/60">This is what happens next:</p>

                <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The organization becomes <strong>Active</strong>.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> {{ $registrant ? $registrant->name.' ('.$registrant->email.')' : 'The admin' }} is emailed and can manage the organization.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The decision is saved in the audit log under your name.</li>
                    @if ($unverified > 0)
                        <li class="flex gap-2.5 text-[#6B4C0C]"><span class="mt-0.5">!</span> {{ $unverified }} {{ \Illuminate\Support\Str::plural('domain', $unverified) }} not DNS-verified — members will not be matched by email domain until verified.</li>
                    @endif
                </ul>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'approve-org')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                    <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!busy">Approve organization</span><span x-show="busy" x-cloak>Approving…</span>
                    </button>
                </div>
            </form>
        </x-modal>

        {{-- Reject --}}
        <x-modal name="reject-org" maxWidth="lg" :show="$errors->has('reason')" focusable>
            <form method="POST" action="{{ route('platform.organizations.reject', $org) }}" x-data="{ busy: false, reason: @js(old('reason', '')) }" @submit="busy = true" class="p-6">
                @csrf
                <h2 class="text-[18px] font-extrabold tracking-tight">Reject {{ $org->name }}?</h2>
                <p class="mt-1 text-[13px] text-brand-ink/60">The reason below is <strong>sent to the applicant</strong> by email.</p>

                <div class="mt-4 flex flex-wrap gap-2" aria-label="Quick reasons">
                    @foreach ($templates as $label => $text)
                        <button type="button" @click="reason = @js($text)"
                                class="rounded-full border border-brand-ink/15 px-3 py-1 text-[12px] font-semibold text-brand-ink/70 transition hover:border-brand-blue hover:text-brand-blue">{{ $label }}</button>
                    @endforeach
                </div>

                <label for="reject-reason" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Reason</label>
                <textarea id="reject-reason" name="reason" x-model="reason" rows="5" maxlength="1000" required
                          class="mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20"
                          placeholder="Tell the applicant what to fix."></textarea>
                @error('reason')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                <p class="mt-1 text-right text-[11.5px] text-brand-ink/45"><span x-text="reason.length">0</span>/1000</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'reject-org')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                    <button type="submit" :disabled="busy || reason.trim().length < 10" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-red px-6 text-[13.5px] font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                        <span x-show="!busy">Reject application</span><span x-show="busy" x-cloak>Rejecting…</span>
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($org->subscription_status === \App\Enums\OrgStatus::Active)
        {{-- Suspend --}}
        <x-modal name="suspend-org" maxWidth="lg" :show="$errors->suspend->has('reason')" focusable>
            <form method="POST" action="{{ route('platform.organizations.suspend', $org) }}" x-data="{ busy: false, reason: @js(old('reason', '')) }" @submit="busy = true" class="p-6">
                @csrf
                <h2 class="text-[18px] font-extrabold tracking-tight">Suspend {{ $org->name }}?</h2>

                <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                    <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> All <strong>{{ number_format($overview['members']) }}</strong> members and <strong>{{ number_format($overview['counselors']) }}</strong> counselors lose access to this organization right away.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> All data is kept. Nothing is deleted.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> You can reactivate it at any time.</li>
                </ul>

                <label for="suspend-reason" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Reason (kept in the audit log)</label>
                <textarea id="suspend-reason" name="reason" x-model="reason" rows="4" maxlength="1000" required
                          class="mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20"
                          placeholder="Why is this organization being suspended?"></textarea>
                @error('reason', 'suspend')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                <p class="mt-1 text-right text-[11.5px] text-brand-ink/45"><span x-text="reason.length">0</span>/1000</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'suspend-org')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                    <button type="submit" :disabled="busy || reason.trim().length < 10" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-red px-6 text-[13.5px] font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                        <span x-show="!busy">Suspend organization</span><span x-show="busy" x-cloak>Suspending…</span>
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($org->subscription_status === \App\Enums\OrgStatus::Suspended)
        {{-- Reactivate --}}
        <x-modal name="reactivate-org" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('platform.organizations.reactivate', $org) }}" x-data="{ busy: false }" @submit="busy = true" class="p-6">
                @csrf
                <h2 class="text-[18px] font-extrabold tracking-tight">Reactivate {{ $org->name }}?</h2>
                <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Members and counselors can use this organization again straight away.</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> The suspension reason stays in the audit log.</li>
                </ul>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'reactivate-org')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                    <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!busy">Reactivate</span><span x-show="busy" x-cloak>Reactivating…</span>
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-platform.toast />
</x-platform-layout>
