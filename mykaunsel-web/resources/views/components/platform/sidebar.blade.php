{{-- Desktop: transparent, part of the same light panel as the content. Mobile: slide-in drawer. --}}
<aside class="fixed inset-y-0 left-0 z-30 w-[300px] -translate-x-full overflow-y-auto bg-[#E9E9EB] px-3 pb-6 pt-[88px] shadow-2xl transition-transform md:static md:z-auto md:block md:translate-x-0 md:bg-[#E9E9EB] md:pt-4 md:shadow-none"
       :class="menuOpen ? 'translate-x-0' : ''">
    <nav class="flex flex-col gap-0.5" aria-label="Platform admin">
        @foreach (config('platform-nav') as $section)
            <div x-data="{ open: true }" class="{{ $section['group'] ? 'mt-5' : '' }}">
                @if ($section['group'])
                    <button type="button" class="flex min-h-[32px] w-full items-center justify-between px-3 pb-1.5 pt-2 text-[13px] font-medium text-brand-ink/55 hover:text-brand-ink/80" @click="open = !open">
                        {{ $section['group'] }}
                        <svg class="transition-transform" :class="open ? '' : '-rotate-90'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                @endif
                <div x-show="open" class="flex flex-col gap-0.5">
                    @foreach ($section['items'] as $item)
                        <x-platform.nav-link :route="$item['route']" :icon="$item['icon']" :child="(bool) $section['group']">{{ $item['label'] }}</x-platform.nav-link>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
<div x-show="menuOpen" x-cloak class="fixed inset-0 z-20 bg-black/40 md:hidden" @click="menuOpen = false"></div>
