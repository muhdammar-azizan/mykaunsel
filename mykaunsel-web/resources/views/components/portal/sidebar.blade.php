@props(['items' => []])

{{-- Large screens: a fixed column beside the page. Small screens: a drawer opened by the menu button. --}}
<aside class="fixed inset-y-0 left-0 z-30 w-[240px] -translate-x-full overflow-y-auto scroll-subtle border-r border-[#EAEAEA] bg-[#FAFAFA] px-2 pb-6 pt-[76px] shadow-2xl transition-transform lg:static lg:z-auto lg:translate-x-0 lg:pt-3 lg:shadow-none"
       :class="menuOpen ? 'translate-x-0' : ''">
    <nav class="flex flex-col gap-0.5" aria-label="Portal">
        @foreach ($items as $item)
            @php
                $icon = '<svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.e($item['icon']).'"/></svg>';
            @endphp
            @if ($item['url'])
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                   class="flex min-h-[36px] items-center gap-3 rounded-md px-3 text-[14px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue {{ $item['active'] ? 'bg-brand-ink/[.07] font-semibold text-brand-ink' : 'text-brand-ink/75 hover:bg-brand-ink/[.05] hover:text-brand-ink' }}">
                    {!! $icon !!}<span class="flex-1">{{ $item['label'] }}</span>
                </a>
            @else
                <div class="flex min-h-[36px] cursor-not-allowed items-center gap-3 rounded-md px-3 text-[14px] text-brand-ink/45" aria-disabled="true" title="Coming soon">
                    {!! $icon !!}<span class="flex-1">{{ $item['label'] }}</span>
                    <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-brand-ink/55">Soon</span>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
<div x-show="menuOpen" x-cloak class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="menuOpen = false"></div>
