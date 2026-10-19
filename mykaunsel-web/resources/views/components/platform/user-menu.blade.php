<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="group relative grid h-10 w-10 place-items-center rounded-full transition hover:ring-2 hover:ring-white/30" aria-label="Account menu" @click="open = !open" aria-haspopup="true" :aria-expanded="open">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-yellow text-[13px] font-bold text-brand-ink">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span role="tooltip" x-show="!open" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-brand-ink px-2.5 py-1 text-[12px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100">Account</span>
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 top-[52px] w-60 rounded-2xl bg-cream p-2 shadow-[0_20px_44px_-14px_rgba(14,42,51,.4)]">
        <div class="px-3 py-2">
            <p class="text-[13px] font-bold">{{ auth()->user()->name }}</p>
            <p class="truncate text-[12px] text-brand-ink/55">{{ auth()->user()->email }}</p>
        </div>
        <div class="my-1 h-px bg-brand-ink/10"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-[44px] w-full items-center gap-2.5 rounded-lg px-3 text-left text-[13px] font-medium text-brand-red hover:bg-brand-red/10">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Log out
            </button>
        </form>
    </div>
</div>
