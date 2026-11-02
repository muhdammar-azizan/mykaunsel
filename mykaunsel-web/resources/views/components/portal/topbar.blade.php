@props(['items' => []])

@php
    $user = auth()->user();
    $org = \App\Models\Organization::query()->whereKey(session('current_org_id'))->value('name');
    $roleLabel = ['counselor' => 'Counselor', 'org_admin' => 'Organization admin'][session('current_role')] ?? '';
    $several = $user->memberships()->count() > 1;
@endphp

{{-- Fixed dark bar to the right of the sidebar. Left: menu button (small screens). Middle: the AI chat bar. Middle: page finder. Right: who you are working as, help, notifications, account. --}}
<header class="relative z-40 flex h-[64px] shrink-0 items-center gap-3 bg-brand-ink px-3 text-white sm:gap-5 sm:px-5">
    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-md text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white lg:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    {{-- Small screens: the sidebar is a drawer, so the small icon stays here. Large screens: the logo is at the top of the sidebar. --}}
    <a href="{{ route('dashboard') }}" aria-label="MyKaunsel" class="shrink-0 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white lg:hidden">
        <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-9 w-auto">
    </a>

    {{-- The bar for the AI chat. It only looks the part for now: nothing is connected, so it is read only. The page finder is in the sidebar. --}}
    <div class="relative mx-auto hidden min-w-0 max-w-[740px] flex-1 md:block">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-white/80" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
        <label for="portal-ai" class="sr-only">Ask AI</label>
        <input id="portal-ai" type="text" readonly title="Coming soon" placeholder="Ask AI" autocomplete="off"
               class="block h-[40px] w-full cursor-default rounded-md border border-white/30 bg-[#111C36] pl-11 pr-3 text-[14.5px] text-white placeholder:text-white/70 focus:border-white focus:outline-none focus:ring-1 focus:ring-white">
    </div>

    <div class="ml-auto flex items-center gap-1 sm:gap-2 md:ml-0">
        {{-- "Working as": the organization and the role. With more than one, it leads to the role chooser. --}}
        @if ($several)
            <a href="{{ route('context.select') }}" class="hidden max-w-[260px] items-center gap-2 rounded-md px-3 py-2 text-[14px] font-semibold hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white lg:flex" title="Switch organization or role">
                <span class="truncate">{{ $org }} · {{ $roleLabel }}</span>
                <svg class="shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </a>
        @else
            <span class="hidden max-w-[260px] truncate px-3 text-[14px] font-semibold lg:block">{{ $org }} · {{ $roleLabel }}</span>
        @endif

        <a href="{{ route('help') }}" aria-label="Help" class="grid h-10 w-10 place-items-center rounded-full hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>
        </a>

        <x-portal.notification-bell />
        <x-portal.user-menu :role-label="$roleLabel" :org="$org" :several="$several" />
    </div>
</header>
