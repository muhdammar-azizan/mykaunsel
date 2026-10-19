{{-- Frameless bar: sits directly on the dark page background (no card, no shadow). --}}
<header class="sticky top-0 z-40 flex h-[72px] items-center justify-between bg-brand-deep px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button type="button" class="grid h-11 w-11 place-items-center rounded-full text-white hover:bg-white/10 md:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        {{-- The logo's wordmark is dark navy, so it sits on a small white chip to stay readable on the dark bar. --}}
        <a href="{{ route('platform.dashboard') }}" aria-label="MyKaunsel" class="rounded-xl bg-white px-3 py-1.5">
            <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="h-8 w-auto">
        </a>
    </div>

    <x-platform.user-menu />
</header>
