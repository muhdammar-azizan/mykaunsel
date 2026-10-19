@props(['label', 'value', 'icon', 'color' => 'blue', 'featured' => false])

{{-- Number counts up from 0 when the card appears. --}}
@php
    $chip = ['blue' => 'bg-brand-blue/10 text-brand-blue', 'yellow' => 'bg-brand-yellow/20 text-[#B98417]', 'red' => 'bg-brand-red/10 text-brand-red', 'ink' => 'bg-brand-ink/10 text-brand-ink'][$color];
@endphp

<div x-data="{ n: 0, to: {{ (int) $value }} }"
     x-init="const s = performance.now(); const step = t => { const p = Math.min((t - s) / 900, 1); n = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); }; requestAnimationFrame(step)"
     @class([
        'relative overflow-hidden transition duration-300',
        'rounded-[20px] bg-gradient-to-br from-brand-blue to-[#3A92D0] p-5 text-white shadow-[0_14px_30px_-14px_rgba(10,79,136,.7)]' => $featured,
        'flex items-center gap-3.5 rounded-2xl bg-white p-3.5 ring-1 ring-brand-ink/10 hover:-translate-y-0.5 hover:shadow-[0_10px_24px_-12px_rgba(14,42,51,.25)]' => ! $featured,
     ])>

    @if ($featured)
        <span class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full bg-white/10"></span>
        <span class="pointer-events-none absolute -bottom-12 right-10 h-28 w-28 rounded-full bg-white/10"></span>
        <div class="relative flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/20">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
            </span>
            <span class="text-[13px] font-medium text-white/85">{{ $label }}</span>
        </div>
        <p class="relative mt-4 text-[40px] font-extrabold leading-none tracking-tightest" x-text="n.toLocaleString()">{{ number_format($value) }}</p>
    @else
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $chip }}">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
        </span>
        <span class="flex-1 text-[13px] text-brand-ink/60">{{ $label }}</span>
        <span class="text-[22px] font-extrabold leading-none tracking-tightest" x-text="n.toLocaleString()">{{ number_format($value) }}</span>
    @endif
</div>
