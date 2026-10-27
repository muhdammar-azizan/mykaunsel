@php
    /** One add/edit modal for a plan. $plan is null when adding. */
    $e = $errors->getBag($bag);
    $failed = $errors->hasBag($bag);
    $value = fn (string $field, $default) => $failed ? old($field, $default) : $default;
    $editing = $plan !== null;
    $lockedType = $editing && $everUsed;
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
@endphp

<x-modal :name="$modal" maxWidth="lg" :show="$failed" focusable>
    <form method="POST" action="{{ $action }}" x-data="{ busy: false }" @submit="busy = true" class="p-6">
        @csrf
        <h2 class="text-[18px] font-extrabold tracking-tight">{{ $editing ? 'Edit '.$plan->name : 'Add a '.$groupLabel.' plan' }}</h2>
        <p class="mt-1 text-[13px] text-brand-ink/60">A yearly plan. No payment is processed: this only records the price.</p>

        @if ($e->has('form'))
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-4 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ $e->first('form') }}</div>
        @endif

        @if ($editing)
            <input type="hidden" name="version" value="{{ $plan->version }}">
            @if ($everUsed)
                <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/10 px-4 py-3 text-[13px] leading-snug">
                    Existing organizations keep their current price. Only new assignments use the new price.
                </div>
            @endif
        @endif

        <label for="{{ $modal }}-name" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Name</label>
        <input id="{{ $modal }}-name" type="text" name="name" value="{{ $value('name', $plan?->name) }}" maxlength="100" required class="{{ $input }}" autocomplete="off">
        @if ($e->has('name'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('name') }}</p>@endif

        <label for="{{ $modal }}-type" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Organization type</label>
        @if ($lockedType)
            <input type="hidden" name="org_type" value="{{ $plan->org_type->value }}">
            <select id="{{ $modal }}-type" disabled class="{{ $input }} bg-brand-ink/5" aria-describedby="{{ $modal }}-type-note">
                <option>{{ $groupLabel }}</option>
            </select>
            <p id="{{ $modal }}-type-note" class="mt-1.5 text-[12px] text-brand-ink/55">The type cannot change because organizations have already been given this plan.</p>
        @else
            <select id="{{ $modal }}-type" name="org_type" class="{{ $input }}">
                @foreach ($groups as $key => $label)
                    <option value="{{ $key }}" @selected($value('org_type', $plan?->org_type->value ?? $type) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        @endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $modal }}-min" class="block text-[12.5px] font-semibold text-brand-ink/70">Minimum members</label>
                <input id="{{ $modal }}-min" type="number" name="min_members" value="{{ $value('min_members', $plan?->min_members) }}" min="1" required class="{{ $input }}">
                @if ($e->has('min_members'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('min_members') }}</p>@endif
            </div>
            <div>
                <label for="{{ $modal }}-max" class="block text-[12.5px] font-semibold text-brand-ink/70">Maximum members <span class="font-normal text-brand-ink/50">(optional)</span></label>
                <input id="{{ $modal }}-max" type="number" name="max_members" value="{{ $value('max_members', $plan?->max_members) }}" min="1" placeholder="No upper limit" class="{{ $input }}">
                @if ($e->has('max_members'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('max_members') }}</p>@endif
            </div>
        </div>

        <label for="{{ $modal }}-price" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">Yearly price (RM)</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-4 top-[calc(50%+3px)] -translate-y-1/2 text-[14px] font-semibold text-brand-ink/55">RM</span>
            <input id="{{ $modal }}-price" type="text" inputmode="decimal" name="price" value="{{ $value('price', $plan ? \App\Support\Money::plain($plan->price_cents) : null) }}" required placeholder="1200.00" class="{{ $input }} pl-12" autocomplete="off">
        </div>
        @if ($e->has('price'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $e->first('price') }}</p>@endif
        <p class="mt-1.5 text-[12px] text-brand-ink/55">Billed yearly. The price excludes tax (SST).</p>

        <label class="mt-4 flex items-start gap-3 text-[13.5px]">
            <input type="checkbox" name="active" value="1" @checked($failed ? (bool) old('active') : ($plan?->active ?? true)) class="mt-1 h-4 w-4 rounded border-brand-ink/30 text-brand-blue focus:ring-brand-blue/30">
            <span><span class="font-semibold">Active</span><br><span class="text-[12.5px] text-brand-ink/60">An active plan can be given to organizations. Active plans of the same type must not overlap. Plans are never deleted: make a plan inactive to stop offering it (organizations already on it keep it).</span></span>
        </label>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="$dispatch('close-modal', '{{ $modal }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
            <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!busy">{{ $editing ? 'Save plan' : 'Add plan' }}</span><span x-show="busy" x-cloak>Saving…</span>
            </button>
        </div>
    </form>
</x-modal>
