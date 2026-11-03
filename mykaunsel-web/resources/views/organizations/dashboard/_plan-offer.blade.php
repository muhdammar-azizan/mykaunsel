{{-- "Choose a plan" card for an organization with no plan. Nothing is bought here. The button asks MyKaunsel (the same request as "Ask to renew"),
     MyKaunsel agrees the plan and sends the invoice. Prices are the plans' yearly prices as they are now, without tax (SST); a promo code is applied by MyKaunsel. --}}
@php
    $rows = $offered->map(function (array $row) {
        $plan = $row['plan'];
        [$whole, $sen] = array_pad(explode('.', \App\Support\Money::rm($plan->price_cents), 2), 2, '00');

        return ['id' => $plan->getKey(), 'name' => $plan->name, 'range' => $plan->rangeLabel(), 'whole' => $whole, 'sen' => $sen, 'fits' => $row['fit'] === 'fits'];
    })->values();
@endphp

<div class="mt-6 grid gap-0 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" x-data="{ plans: @js($rows), picked: @js($rows->first()['id']), get plan() { return this.plans.find(p => p.id === this.picked); } }">
    {{-- Left: what this is --}}
    <div class="rounded-2xl border border-[#E4E2DE] bg-[#FAFAFA] p-6 md:rounded-r-none md:border-r-0">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-blue/10 px-3 py-1 text-[12px] font-semibold text-brand-blue">One price a year</span>
        <h3 class="mt-3 text-[22px] font-extrabold leading-tight tracking-tight">Pick the plan that fits your organization</h3>
        <p class="mt-2 max-w-[52ch] text-[13.5px] leading-relaxed text-brand-ink/65">Plans are sized by the number of active members and paid once a year. Choose one and ask MyKaunsel: they confirm it with you and send the invoice.</p>

        <p class="mt-5 text-[15px] font-bold">How it works</p>
        <ul class="mt-3 grid gap-x-6 gap-y-2.5 text-[13.5px] sm:grid-cols-2">
            @foreach (['One yearly price for the whole organization', 'Sized by the members you have', 'MyKaunsel sets the plan up with you', 'The invoice appears below, ready to pay', 'Asking costs nothing', 'Any promo code is applied by MyKaunsel'] as $point)
                <li class="flex items-start gap-2">
                    <svg class="mt-0.5 shrink-0 text-emerald-600" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><path d="m8 12 3 3 5-6" stroke="#fff"/></svg>
                    <span>{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Right: the price and the button --}}
    <div class="flex flex-col items-center rounded-2xl border border-[#E4E2DE] bg-white p-6 text-center md:rounded-l-none md:border-l-0 md:border-dashed">
        @if ($rows->count() > 1)
            <div class="flex w-full flex-wrap justify-center gap-1.5" role="radiogroup" aria-label="Plan">
                <template x-for="p in plans" :key="p.id">
                    <button type="button" role="radio" :aria-checked="picked === p.id" @click="picked = p.id"
                            class="rounded-full border px-3 py-1 text-[12.5px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
                            :class="picked === p.id ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5'" x-text="p.name"></button>
                </template>
            </div>
        @endif

        <p class="mt-4 text-[13px] font-semibold text-brand-ink/65"><span x-text="plan.name"></span> · <span x-text="plan.range"></span> members</p>
        <p x-show="plan.fits" class="mt-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11.5px] font-bold text-emerald-700">Fits the size you gave us</p>
        <p class="mt-2 text-[36px] font-extrabold leading-none tracking-tight tabular-nums"><span x-text="plan.whole"></span><span class="text-brand-ink/30">.<span x-text="plan.sen"></span></span></p>
        <p class="mt-1 text-[12.5px] text-brand-ink/55">a year, without tax (SST)</p>

        <form method="POST" action="{{ route('org.subscription.renewal') }}" class="mt-5 w-full">
            @csrf
            <input type="hidden" name="plan_id" :value="picked">
            <button class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-full bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">
                Get started <span aria-hidden="true">→</span>
            </button>
        </form>
    </div>
</div>
