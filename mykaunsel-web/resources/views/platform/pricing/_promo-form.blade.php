@php
    /** One add/edit modal for a promo code. $promo is null when adding. $redeemed: it was used, so some fields are locked. */
    $e = $errors->getBag($bag);
    $failed = $errors->hasBag($bag);
    $value = fn (string $field, $default) => $failed ? old($field, $default) : $default;
    $editing = $promo !== null;
    $lock = $editing && $redeemed;
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $locked = 'bg-brand-ink/5 text-brand-ink/70';
    $local = fn ($moment) => $moment ? $moment->copy()->timezone($timezone)->format('Y-m-d\TH:i') : null;
    $currentType = $value('type', $promo?->type ?? 'percent');
    $currentValue = $promo ? ($promo->type === 'percent' ? (string) $promo->value : \App\Support\Money::plain($promo->value)) : null;
@endphp

<x-modal :name="$modal" maxWidth="lg" :show="$failed" focusable>
    <form method="POST" action="{{ $action }}" x-data="{ busy: false, type: @js($currentType) }" @submit="busy = true" class="p-6">
        @csrf
        <h2 class="text-[18px] font-extrabold tracking-tight">{{ $editing ? 'Edit '.$promo->code : 'Add a promo code' }}</h2>
        <p class="mt-1 text-[13px] text-brand-ink/60">An admin applies a code when giving an organization a plan. No payment is processed: a code only lowers the recorded price.</p>

        @if ($e->has('form'))
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-4 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ $e->first('form') }}</div>
        @endif

        @if ($editing)
            <input type="hidden" name="version" value="{{ $promo->version }}">
        @endif
        @if ($lock)
            <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/10 px-4 py-3 text-[13px] leading-snug">
                This code has already been used, so its code, type, value, start date and "applies to" are locked. You can still change the end date and the usage limit, or disable it.
            </div>
        @endif

        <label for="{{ $modal }}-code" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Code</label>
        <input id="{{ $modal }}-code" type="text" name="code" value="{{ $value('code', $promo?->code) }}" minlength="4" maxlength="32" required autocomplete="off" spellcheck="false"
               @readonly($lock) oninput="this.value = this.value.toUpperCase()" aria-describedby="{{ $modal }}-code-hint"
               class="{{ $input }} font-mono uppercase tracking-wide {{ $lock ? $locked : '' }}">
        <p id="{{ $modal }}-code-hint" class="mt-1.5 text-[12px] text-brand-ink/55">4 to 32 letters, numbers or hyphens. Capitals are added for you, and "welcome" and "WELCOME" count as the same code.</p>
        @if ($e->has('code'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('code') }}</p>@endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $modal }}-type" class="block text-[12.5px] font-semibold text-brand-ink/70">Type</label>
                @if ($lock)
                    <input type="hidden" name="type" value="{{ $promo->type }}">
                    <select id="{{ $modal }}-type" disabled class="{{ $input }} {{ $locked }}">
                        <option>{{ \App\Models\PromoCode::TYPES[$promo->type] }}</option>
                    </select>
                @else
                    <select id="{{ $modal }}-type" name="type" x-model="type" class="{{ $input }}">
                        @foreach (\App\Models\PromoCode::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($e->has('type'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('type') }}</p>@endif
            </div>
            <div>
                <label for="{{ $modal }}-value" class="block text-[12.5px] font-semibold text-brand-ink/70"><span x-show="type === 'percent'">Discount (%)</span><span x-show="type !== 'percent'" x-cloak>Discount (RM)</span></label>
                <input id="{{ $modal }}-value" type="text" inputmode="decimal" name="value" value="{{ $value('value', $currentValue) }}" required autocomplete="off" @readonly($lock)
                       :placeholder="type === 'percent' ? '20' : '100.00'" class="{{ $input }} {{ $lock ? $locked : '' }}">
                @if ($e->has('value'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('value') }}</p>@endif
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $modal }}-from" class="block text-[12.5px] font-semibold text-brand-ink/70">Valid from</label>
                <input id="{{ $modal }}-from" type="datetime-local" name="valid_from" value="{{ $value('valid_from', $local($promo?->valid_from)) }}" required @readonly($lock) class="{{ $input }} {{ $lock ? $locked : '' }}">
                @if ($e->has('valid_from'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('valid_from') }}</p>@endif
            </div>
            <div>
                <label for="{{ $modal }}-until" class="block text-[12.5px] font-semibold text-brand-ink/70">Valid until</label>
                <input id="{{ $modal }}-until" type="datetime-local" name="valid_until" value="{{ $value('valid_until', $local($promo?->valid_until)) }}" required class="{{ $input }}">
                @if ($e->has('valid_until'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('valid_until') }}</p>@endif
            </div>
        </div>
        <p class="mt-1.5 text-[12px] text-brand-ink/55">Times are in your timezone ({{ $timezone }}). The code works through the end of the last minute.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $modal }}-limit" class="block text-[12.5px] font-semibold text-brand-ink/70">Usage limit <span class="font-normal text-brand-ink/50">(optional)</span></label>
                <input id="{{ $modal }}-limit" type="number" name="usage_limit" value="{{ $value('usage_limit', $promo?->usage_limit) }}" min="{{ max(1, $promo?->used_count ?? 1) }}" placeholder="Unlimited" class="{{ $input }}">
                @if ($editing && $promo->used_count > 0)<p class="mt-1.5 text-[12px] text-brand-ink/55">Used {{ number_format($promo->used_count) }} {{ $promo->used_count === 1 ? 'time' : 'times' }} so far, so not lower than that.</p>@endif
                @if ($e->has('usage_limit'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('usage_limit') }}</p>@endif
            </div>
            <div>
                <label for="{{ $modal }}-applies" class="block text-[12.5px] font-semibold text-brand-ink/70">Applies to</label>
                @if ($lock)
                    <input type="hidden" name="applies_to" value="{{ $promo->applies_to }}">
                    <select id="{{ $modal }}-applies" disabled class="{{ $input }} {{ $locked }}">
                        <option>{{ $promo->appliesToLabel() }}</option>
                    </select>
                @else
                    <select id="{{ $modal }}-applies" name="applies_to" class="{{ $input }}">
                        @foreach (\App\Models\PromoCode::APPLIES_TO as $key => $label)
                            <option value="{{ $key }}" @selected($value('applies_to', $promo?->applies_to ?? 'all') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($e->has('applies_to'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('applies_to') }}</p>@endif
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="$dispatch('close-modal', '{{ $modal }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
            <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!busy">{{ $editing ? 'Save code' : 'Add code' }}</span><span x-show="busy" x-cloak>Saving…</span>
            </button>
        </div>
    </form>
</x-modal>
