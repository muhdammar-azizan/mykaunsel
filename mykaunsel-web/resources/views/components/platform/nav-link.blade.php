@props(['route', 'icon'])

@php $active = request()->routeIs($route); @endphp

<a href="{{ route($route) }}"
   class="flex min-h-[46px] items-center gap-3 rounded-xl px-3.5 text-[14px] font-medium transition {{ $active ? 'bg-white text-brand-ink shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
   @if ($active) aria-current="page" @endif>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
    {{ $slot }}
</a>
