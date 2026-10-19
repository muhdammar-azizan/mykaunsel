@props(['route', 'icon'])

@php $active = request()->routeIs($route); @endphp

<a href="{{ route($route) }}"
   class="flex min-h-[48px] items-center gap-4 rounded-xl px-3.5 text-[15px] font-medium transition {{ $active ? 'bg-white text-brand-ink' : 'text-brand-ink/80 hover:bg-white/60 hover:text-brand-ink' }}"
   @if ($active) aria-current="page" @endif>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
    {{ $slot }}
</a>
