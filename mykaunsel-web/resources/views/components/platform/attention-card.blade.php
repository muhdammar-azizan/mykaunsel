@props(['label', 'count', 'route', 'icon', 'color' => 'blue'])

@php
    $styles = [
        'blue' => 'bg-brand-blue text-white',
        'yellow' => 'bg-brand-yellow text-brand-ink',
        'red' => 'bg-brand-red text-white',
        'ink' => 'bg-brand-ink text-white',
    ][$color];
@endphp

<a href="{{ route($route) }}" class="group flex h-[190px] flex-col rounded-[22px] p-5 {{ $styles }} shadow-[0_10px_24px_-14px_rgba(7,58,102,.5)] transition hover:-translate-y-1 hover:shadow-[0_18px_30px_-14px_rgba(7,58,102,.55)]">
    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/90 text-brand-ink">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
    </span>
    <span class="mt-3 text-[36px] font-extrabold leading-none">{{ $count }}</span>
    <span class="mt-auto flex items-center justify-between text-[14px] font-bold leading-tight">
        {{ $label }}
        <span class="translate-x-[-4px] opacity-0 transition group-hover:translate-x-0 group-hover:opacity-100" aria-hidden="true">→</span>
    </span>
</a>
