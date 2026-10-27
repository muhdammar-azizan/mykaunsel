@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $commissionErrors = $errors->getBag('commission');
@endphp

<x-platform-layout title="Pricing & Promotions">
    <x-platform.page-header title="Pricing & Promotions" subtitle="Yearly plans for universities and corporates, and the commission for clinics and independent counselors." />

    <div class="mt-4 rounded-2xl border border-brand-blue/25 bg-brand-blue/[.05] px-5 py-3 text-[13.5px] leading-snug" role="note">
        <strong>No payment is processed.</strong> Plans and prices only record what an organization was given; nothing is charged. The prices on this page are <strong>examples</strong>, and prices exclude tax (SST).
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[13.5px] font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @foreach ((array) session('warnings', []) as $warning)
        <div class="mt-3 rounded-2xl border border-brand-yellow/60 bg-brand-yellow/15 px-5 py-3 text-[13.5px] font-semibold text-[#6B4C0C]" role="alert">Warning: {{ $warning }}</div>
    @endforeach
    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <x-platform.status-tabs :tabs="$tabs" :active="$tab" label="Pricing sections" />

    @foreach ($groups as $type => $groupLabel)
        @php
            $list = $plans->get($type, collect());
            $addModal = 'add-plan-'.$type;
        @endphp

        <section class="mt-8" aria-labelledby="group-{{ $type }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="group-{{ $type }}" class="text-[17px] font-extrabold tracking-tight">{{ $groupLabel }} plans</h2>
                    <p class="text-[13px] text-brand-ink/55">Yearly price by number of members.</p>
                </div>
                <button type="button" x-data @click="$dispatch('open-modal', '{{ $addModal }}')" class="inline-flex min-h-[40px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep">Add plan</button>
            </div>

            @foreach ($coverage[$type] as $warning)
                <div class="mt-3 rounded-2xl border border-brand-yellow/60 bg-brand-yellow/15 px-4 py-2.5 text-[13px] text-[#6B4C0C]" role="note">{{ $warning }}</div>
            @endforeach

            @if ($list->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                    <p class="text-[14px] font-semibold">No {{ strtolower($groupLabel) }} plans yet</p>
                    <p class="mt-1 text-[13px] text-brand-ink/55">Add the first plan to be able to give it to a {{ strtolower($groupLabel) }} when approving it.</p>
                </div>
            @else
                <ul class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($list as $plan)
                        @php $using = $usage['current'][$plan->id] ?? 0; @endphp
                        <li class="flex flex-col rounded-2xl border border-brand-ink/10 bg-white px-5 py-4 {{ $plan->active ? '' : 'opacity-75' }}">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-[15px] font-bold leading-snug">{{ $plan->name }}</h3>
                                @if ($plan->active)
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11.5px] font-bold text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-brand-ink/10 px-2.5 py-0.5 text-[11.5px] font-bold text-brand-ink/70">Inactive</span>
                                @endif
                            </div>
                            <p class="mt-3 text-[26px] font-extrabold leading-none tracking-tightest">{{ \App\Support\Money::rm($plan->price_cents) }}<span class="ml-1 text-[13px] font-semibold text-brand-ink/50">/ year</span></p>
                            <p class="mt-2 text-[13px] text-brand-ink/70">{{ $plan->rangeLabel() }} members</p>
                            <p class="mt-1 text-[13px] text-brand-ink/55">{{ number_format($using) }} {{ \Illuminate\Support\Str::plural('organization', $using) }} {{ $using === 1 ? 'uses' : 'use' }} this plan</p>
                            <div class="mt-4 flex justify-end">
                                <button type="button" x-data @click="$dispatch('open-modal', 'edit-plan-{{ $plan->id }}')" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/20 px-4 text-[12.5px] font-semibold transition hover:bg-brand-ink/5" aria-label="Edit {{ $plan->name }}">Edit</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @include('platform.pricing._plan-form', [
            'modal' => $addModal, 'action' => route('platform.pricing.plans.store'), 'bag' => 'plan-new-'.$type, 'plan' => null,
            'type' => $type, 'groupLabel' => $groupLabel, 'groups' => $groups, 'everUsed' => false,
        ])

        @foreach ($list as $plan)
            @include('platform.pricing._plan-form', [
                'modal' => 'edit-plan-'.$plan->id, 'action' => route('platform.pricing.plans.update', $plan), 'bag' => 'plan-'.$plan->id, 'plan' => $plan,
                'type' => $type, 'groupLabel' => $groupLabel, 'groups' => $groups, 'everUsed' => in_array($plan->id, $usage['ever'], true),
            ])
        @endforeach
    @endforeach

    {{-- Commission --}}
    <section class="mt-10" aria-labelledby="commission-title">
        <x-platform.panel title="Commission">
            <p class="text-[13.5px] text-brand-ink/70">Clinics and independent counselors have no plan: their clients pay, and the platform keeps a percentage of each session. This is one setting for all of them.</p>

            <form method="POST" action="{{ route('platform.pricing.commission') }}" x-data="{ busy: false }" @submit="busy = true" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label id="commission-title" for="commission_percent" class="block text-[12.5px] font-semibold text-brand-ink/70">Commission on sessions (%)</label>
                    <div class="relative">
                        <input id="commission_percent" type="text" inputmode="decimal" name="commission_percent" required autocomplete="off"
                               value="{{ $commissionErrors->any() ? old('commission_percent') : rtrim(rtrim(number_format($commission, 2, '.', ''), '0'), '.') }}"
                               class="{{ $input }} w-40 pr-9" aria-describedby="commission-note">
                        <span class="pointer-events-none absolute right-4 top-[calc(50%+3px)] -translate-y-1/2 text-[14px] font-semibold text-brand-ink/55">%</span>
                    </div>
                </div>
                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!busy">Save</span><span x-show="busy" x-cloak>Saving…</span>
                </button>
            </form>
            @if ($commissionErrors->has('commission_percent'))
                <p class="mt-2 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $commissionErrors->first('commission_percent') }}</p>
            @endif
            <p id="commission-note" class="mt-2 text-[12.5px] text-brand-ink/55">Record only. No payments are processed. 10 is an example value.</p>
        </x-platform.panel>
    </section>
</x-platform-layout>
