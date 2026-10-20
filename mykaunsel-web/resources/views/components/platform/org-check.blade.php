@props(['label', 'state', 'text'])

{{-- One small status chip on an application card. $state: good | warn | bad | muted --}}
@php
    $style = [
        'good' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'M20 6 9 17l-5-5'],
        'warn' => ['bg-brand-yellow/15 text-[#8A6210] ring-brand-yellow/40', 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
        'bad' => ['bg-brand-red/10 text-brand-red ring-brand-red/25', 'M18 6 6 18M6 6l12 12'],
        'muted' => ['bg-brand-ink/5 text-brand-ink/55 ring-brand-ink/10', 'M5 12h14'],
    ][$state];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-semibold ring-1 ring-inset', $style[0]]) }}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $style[1] }}"/></svg>
    <span class="font-medium opacity-75">{{ $label }}:</span> {{ $text }}
</span>
