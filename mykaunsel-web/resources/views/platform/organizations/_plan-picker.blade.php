@php
    /**
     * Choose a plan (and optionally a promo code) for an organization, with the price worked out by the server before anything is saved.
     * $org, $offer (SubscriptionAssignmentService::offeredPlans), $prefix (unique ids), $bag (error bag), $allowNone, $currentPlanId.
     * There is no payment: the preview is a record of what would be written down.
     */
    $e = $errors->getBag($bag);
    $failed = $errors->hasBag($bag);
    $chosen = (string) ($failed ? old('plan_id', '') : ($allowNone ? '' : ($currentPlanId ?? '')));
    $badges = [
        'fits' => ['Fits', 'bg-emerald-100 text-emerald-800'],
        'partial' => ['Covers part of the stated size', 'bg-brand-yellow/25 text-[#7A5410]'],
        'outside' => ['Outside the stated size', 'bg-brand-ink/10 text-brand-ink/70'],
    ];
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
@endphp

<fieldset class="mt-5" @if ($offer['applicable']) x-data="{
        plan: @js($chosen), code: @js($failed ? (string) old('promo_code', '') : ''), loading: false, result: null, error: null, timer: null,
        async refresh() {
            if (!this.plan) { this.result = null; this.error = null; return; }
            this.loading = true;
            try {
                const r = await fetch(@js(route('platform.organizations.plan-preview', $org)), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify({ plan_id: this.plan, promo_code: this.code }),
                });
                const d = await r.json();
                if (r.ok) { this.result = d; this.error = null; } else { this.result = null; this.error = d.message || 'This could not be checked.'; }
            } catch (err) { this.result = null; this.error = 'The price could not be checked. Try again.'; }
            this.loading = false;
        },
        later() { clearTimeout(this.timer); this.timer = setTimeout(() => this.refresh(), 400); },
    }" x-init="refresh()" @endif>
    <legend class="text-[13.5px] font-bold">Plan</legend>

    @if (! $offer['applicable'])
        <p class="mt-2 rounded-xl bg-brand-ink/5 px-4 py-3 text-[13px] leading-snug text-brand-ink/70">Clinics and independent counselors have no plan: their clients pay and the platform keeps a commission. Nothing to choose here.</p>
    @else
        <p class="mt-1 text-[12.5px] text-brand-ink/60">
            A yearly plan, recorded for this organization. <strong>No payment is processed.</strong>
            @if ($offer['size_label'])
                They said their size is {{ $offer['size_label'] }}.
            @else
                Size not stated, so every active plan is shown.
            @endif
        </p>

        @if ($e->has('plan'))
            <div class="mt-3 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-4 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ $e->first('plan') }}</div>
        @endif

        @if ($offer['none_cover'])
            <p class="mt-3 rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 px-4 py-3 text-[13px] font-semibold text-[#6B4C0C]" role="status">No plan covers this size.{{ $allowNone ? ' You can still continue without a plan.' : '' }}</p>
        @endif

        <div class="mt-3 space-y-2">
            @if ($allowNone)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-ink/12 px-4 py-3 text-[13.5px] has-[:checked]:border-brand-blue has-[:checked]:bg-brand-blue/[.04]">
                    <input type="radio" name="plan_id" value="" x-model="plan" @change="refresh()" class="mt-1 h-4 w-4 border-brand-ink/30 text-brand-blue focus:ring-brand-blue/30">
                    <span><span class="font-semibold">No plan for now</span><br><span class="text-[12.5px] text-brand-ink/60">You can give one later from this page.</span></span>
                </label>
            @endif

            @foreach ($offer['plans'] as $row)
                @php
                    $plan = $row['plan'];
                    $badge = $badges[$row['fit']] ?? null;
                @endphp
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-ink/12 px-4 py-3 text-[13.5px] has-[:checked]:border-brand-blue has-[:checked]:bg-brand-blue/[.04]">
                    <input type="radio" name="plan_id" value="{{ $plan->id }}" x-model="plan" @change="refresh()" class="mt-1 h-4 w-4 border-brand-ink/30 text-brand-blue focus:ring-brand-blue/30">
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $plan->name }}</span>
                            @if ($badge)<span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>@endif
                            @if ($currentPlanId === $plan->id)<span class="rounded-full bg-brand-ink/10 px-2 py-0.5 text-[11px] font-bold text-brand-ink/70">Current plan</span>@endif
                        </span>
                        <span class="block text-[12.5px] text-brand-ink/60">{{ $plan->rangeLabel() }} members · {{ \App\Support\Money::rm($plan->price_cents) }} a year</span>
                    </span>
                </label>
            @endforeach
        </div>

        <label for="{{ $prefix }}-promo" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Promo code <span class="font-normal text-brand-ink/50">(optional)</span></label>
        <input id="{{ $prefix }}-promo" type="text" name="promo_code" x-model="code" @input="code = code.toUpperCase(); later()" maxlength="32" autocomplete="off" spellcheck="false"
               class="{{ $input }} font-mono uppercase tracking-wide" aria-describedby="{{ $prefix }}-promo-note">
        <p id="{{ $prefix }}-promo-note" class="mt-1.5 text-[12px] text-brand-ink/55">Applied once, when you confirm. It is checked now only to show the price.</p>
        @if ($e->has('promo_code'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('promo_code') }}</p>@endif

        <div class="mt-4" aria-live="polite">
            <p x-show="loading" x-cloak class="text-[12.5px] text-brand-ink/55">Checking the price…</p>
            <p x-show="error" x-cloak x-text="error" class="rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-4 py-2.5 text-[13px] font-semibold text-brand-red" role="alert"></p>

            <div x-show="result && !error" x-cloak class="rounded-xl border border-brand-ink/12 bg-brand-ink/[.03] px-4 py-3 text-[13.5px]">
                <dl class="space-y-1.5">
                    <div class="flex justify-between gap-4"><dt class="text-brand-ink/65">Plan price</dt><dd class="font-semibold" x-text="result && result.plan_price"></dd></div>
                    <div class="flex justify-between gap-4" x-show="result && result.promo_code"><dt class="text-brand-ink/65">Discount (<span class="font-mono" x-text="result && result.promo_code"></span>, <span x-text="result && result.promo_label"></span>)</dt><dd class="font-semibold" x-text="result && ('− ' + result.discount)"></dd></div>
                    <div class="flex justify-between gap-4 border-t border-brand-ink/10 pt-1.5"><dt class="font-bold">Price recorded</dt><dd class="text-[15px] font-extrabold" x-text="result && result.final_price"></dd></div>
                </dl>
                <p class="mt-2 text-[12.5px] text-brand-ink/60">For one year, <span x-text="result && result.starts_on"></span> to <span x-text="result && result.ends_on"></span>. Prices exclude tax (SST).</p>
                <template x-for="warning in (result ? result.warnings : [])" :key="warning"><p class="mt-2 text-[12.5px] font-semibold text-[#6B4C0C]" x-text="'Warning: ' + warning"></p></template>
            </div>
        </div>
    @endif
</fieldset>
