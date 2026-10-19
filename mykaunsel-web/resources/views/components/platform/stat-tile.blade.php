@props(['label', 'value', 'color' => 'blue'])

@php
    $dot = ['blue' => 'bg-brand-blue', 'yellow' => 'bg-brand-yellow', 'red' => 'bg-brand-red', 'ink' => 'bg-brand-ink'][$color];
@endphp

<div class="rounded-2xl bg-cream p-4">
    <p class="flex items-center gap-2 text-[12px] text-brand-ink/60">
        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }}
    </p>
    <p class="mt-1.5 text-[26px] font-extrabold leading-none">{{ number_format($value) }}</p>
</div>
