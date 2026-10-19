{{-- Frameless bar on the dark page background. Logo shows the icon only; the wordmark slides out on hover. --}}
<header class="sticky top-0 z-40 flex h-[72px] items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button type="button" class="grid h-11 w-11 place-items-center rounded-full text-white hover:bg-white/10 md:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <a href="{{ route('platform.dashboard') }}" aria-label="MyKaunsel" class="group flex items-center gap-2.5">
            <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-10 w-auto transition-transform duration-300 group-hover:scale-105">
            <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-out group-hover:max-w-[180px] group-hover:opacity-100 group-focus-visible:max-w-[180px] group-focus-visible:opacity-100">
                <span class="block text-[24px] font-extrabold leading-none tracking-tightest text-white">MyKaunsel</span>
                <span class="mt-1 flex h-[3px] gap-0.5">
                    <span class="flex-1 rounded-full bg-[#4A9BE8]"></span>
                    <span class="flex-1 rounded-full bg-brand-yellow"></span>
                    <span class="flex-1 rounded-full bg-brand-red"></span>
                </span>
            </span>
        </a>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" aria-label="Search" class="grid h-10 w-10 place-items-center rounded-full border border-white/25 text-white transition hover:bg-white/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        </button>
        <x-platform.user-menu />
    </div>
</header>
