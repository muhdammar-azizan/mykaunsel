@props(['route' => null, 'icon', 'child' => false])

@php
    $soon = $route === null;
    $active = ! $soon && request()->routeIs($route);
    $base = 'flex min-h-[40px] items-center gap-3 rounded-xl px-3 text-[13.5px] font-medium transition '.($child ? 'ml-3 ' : '');
@endphp

@if ($soon)
    <span class="{{ $base }} cursor-not-allowed text-brand-ink/45" title="Coming soon" aria-disabled="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
        <span class="flex-1">{{ $slot }}</span>
        <span class="rounded-full bg-brand-ink/10 px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-brand-ink/55">Soon</span>
    </span>
@else
    <a href="{{ route($route) }}"
       class="{{ $base }}{{ $active ? 'bg-white text-brand-ink' : 'text-brand-ink/80 hover:bg-white/60 hover:text-brand-ink' }}"
       @if ($active) aria-current="page" @endif>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
        {{ $slot }}
    </a>
@endif
