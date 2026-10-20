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

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
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
            <x-platform.panel title="Domain verification" class="lg:col-span-2">
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

                    <x-platform.table :headings="['Domain', 'Default role', 'Match', 'DNS status', 'Last checked']">
                        @foreach ($org->orgDomains as $domain)
                            <tr class="border-t border-brand-ink/[.07]">
                                <td class="px-4 py-3 text-[14px] font-semibold">{{ $domain->domain }}</td>
                                <td class="px-4 py-3 text-[13.5px] capitalize">{{ str_replace('_', ' ', $domain->default_role->value) }}</td>
                                <td class="px-4 py-3 text-[13.5px] capitalize">{{ $domain->match_type->value }}</td>
                                <td class="px-4 py-3">
                                    @if ($domain->dns_verified)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11.5px] font-bold text-emerald-700">Verified</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11.5px] font-bold text-[#8A6210]">Skipped</span>
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
                            </tr>
                        @endforeach
                    </x-platform.table>

                    {{-- Actions arrive with the DNS phase. --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (['Check DNS now', 'Send reminder email'] as $action)
                            <span class="inline-flex cursor-not-allowed items-center gap-2 rounded-full border border-brand-ink/10 px-4 py-1.5 text-[13px] font-semibold text-brand-ink/40" title="Coming soon">
                                {{ $action }}
                                <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide">Soon</span>
                            </span>
                        @endforeach
                    </div>
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
</x-platform-layout>
