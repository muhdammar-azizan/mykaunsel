<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-3 text-white hover:bg-white/10" @click="open = !open" aria-haspopup="true" :aria-expanded="open">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-yellow text-[13px] font-bold text-brand-ink">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="hidden text-[13px] font-semibold sm:block">{{ auth()->user()->name }}</span>
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
