{{-- Frameless bar on the dark page background. Logo shows the icon only; the wordmark slides out on hover. --}}
<header class="relative z-40 flex h-[72px] shrink-0 items-center justify-between px-4 sm:px-6">
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
        <button type="button" aria-label="Search" @click="searchOpen = true" class="group relative grid h-10 w-10 place-items-center rounded-full border border-brand-ink/10 bg-cream text-brand-ink shadow-[0_2px_6px_-2px_rgba(14,42,51,.25)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_16px_-6px_rgba(14,42,51,.35)]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <span role="tooltip" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-brand-ink px-2.5 py-1 text-[12px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100">Search</span>
        </button>
        {{-- Notifications are not built yet: the bell is switched off and shows no dot (a dot would claim something is waiting). --}}
        <button type="button" disabled aria-disabled="true" aria-label="Notifications (coming soon)" class="group relative grid h-10 w-10 cursor-not-allowed place-items-center rounded-full border border-brand-ink/10 bg-cream text-brand-ink opacity-60 shadow-[0_2px_6px_-2px_rgba(14,42,51,.25)]">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 4-2 5-2 7h16c0-2-2-3-2-7z"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0"/></svg>
            <span role="tooltip" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-brand-ink px-2.5 py-1 text-[12px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100">Notifications — coming soon</span>
        </button>
        <x-platform.user-menu />
    </div>
</header>
