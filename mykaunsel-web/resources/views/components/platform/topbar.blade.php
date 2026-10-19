<header class="sticky top-2.5 z-40 mx-2.5 mt-2.5 flex h-[72px] items-center justify-between rounded-[26px] bg-white px-4 shadow-[0_12px_32px_-14px_rgba(0,0,0,.35)] sm:px-6">
    <div class="flex items-center gap-3">
        <button type="button" class="grid h-11 w-11 place-items-center rounded-full hover:bg-brand-ink/5 md:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a href="{{ route('platform.dashboard') }}" aria-label="MyKaunsel">
            <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="h-10 w-auto">
        </a>
    </div>

    <x-platform.user-menu />
</header>
