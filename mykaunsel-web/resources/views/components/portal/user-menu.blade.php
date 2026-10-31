@props(['roleLabel' => '', 'org' => null, 'several' => false])

@php $user = auth()->user(); @endphp

{{-- The avatar is a rounded square (not a circle) on the brand blue; the menu holds who you are, account, switching and sign out. --}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" aria-haspopup="true" :aria-expanded="open" aria-label="Account menu"
            class="grid h-10 w-10 place-items-center overflow-hidden rounded-[6px] bg-gradient-to-br from-[#3A92D0] to-brand-blue text-[13px] font-bold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
        @if ($user->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="" class="h-full w-full object-cover">
        @else
            {{ $user->initials }}
        @endif
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="scale-100 opacity-100" x-transition:leave-end="scale-95 opacity-0"
         class="absolute right-0 top-[50px] z-50 w-[300px] origin-top-right rounded-xl border border-brand-ink/10 bg-white py-2 text-brand-ink shadow-[0_20px_44px_-14px_rgba(14,42,51,.45)]">
        <div class="px-5 pb-3 pt-3">
            <p class="text-[15.5px] font-bold leading-tight">{{ $user->name }}</p>
            <p class="mt-0.5 truncate text-[12.5px] text-brand-ink/60">{{ $user->email }}</p>
            <span class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-blue">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-blue"></span><span class="truncate">{{ $org }} · {{ $roleLabel }}</span>
            </span>
        </div>
        <div class="mx-4 border-t border-brand-ink/10"></div>
        <div class="px-2 py-1.5">
            <a href="{{ route('profile.edit') }}" class="flex min-h-[40px] items-center gap-3 rounded-lg px-3 text-[14px] transition hover:bg-brand-ink/5">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
                Account
            </a>
            @if ($several)
                <a href="{{ route('context.select') }}" class="flex min-h-[40px] items-center gap-3 rounded-lg px-3 text-[14px] transition hover:bg-brand-ink/5">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3l4 4-4 4M21 7H9M7 21l-4-4 4-4M3 17h12"/></svg>
                    Switch organization or role
                </a>
            @endif
            <a href="{{ route('help') }}" class="flex min-h-[40px] items-center gap-3 rounded-lg px-3 text-[14px] transition hover:bg-brand-ink/5">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>
                Help
            </a>
        </div>
        <div class="mx-4 border-t border-brand-ink/10"></div>
        <form method="POST" action="{{ route('logout') }}" class="px-2 pt-1.5">
            @csrf
            <button type="submit" class="flex min-h-[40px] w-full items-center gap-3 rounded-lg px-3 text-left text-[14px] font-medium transition hover:bg-brand-red/10 hover:text-brand-red">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Log out
            </button>
        </form>
    </div>
</div>
