@props(['items' => []])

@php
    $user = auth()->user();
    $org = \App\Models\Organization::query()->whereKey(session('current_org_id'))->value('name');
    $roleLabel = ['counselor' => 'Counselor', 'org_admin' => 'Organization admin'][session('current_role')] ?? '';
    $several = $user->memberships()->count() > 1;
    $pages = collect($items)->whereNotNull('url')->map(fn ($i) => ['label' => $i['label'], 'url' => $i['url']])->values()->all();
@endphp

{{-- Fixed dark bar. Left: menu button (small screens), logo. Middle: page finder. Right: who you are working as, help, notifications, account. --}}
<header class="relative z-40 flex h-[64px] shrink-0 items-center gap-3 bg-brand-ink px-3 text-white sm:gap-5 sm:px-5">
    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-md text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white lg:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <a href="{{ route('dashboard') }}" aria-label="MyKaunsel" class="flex shrink-0 items-center gap-2.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
        <img src="{{ asset('images/mykaunsel-icon.png') }}" alt="" class="h-9 w-auto">
        <span class="hidden text-[20px] font-extrabold tracking-tightest sm:block">MyKaunsel</span>
    </a>

    {{-- Page finder: type a page name, Enter goes to the first match. --}}
    <div class="relative mx-auto hidden min-w-0 max-w-[740px] flex-1 md:block" x-data="{ q: '', pages: @js($pages), open: false,
            get found() { const q = this.q.trim().toLowerCase(); return q === '' ? [] : this.pages.filter(p => p.label.toLowerCase().includes(q)); } }"
         @click.outside="open = false">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-white/80" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <label for="portal-find" class="sr-only">Find a page</label>
        <input id="portal-find" type="search" x-model="q" @focus="open = true" @input="open = true" @keydown.enter.prevent="if (found.length) window.location = found[0].url" @keydown.escape="open = false" autocomplete="off"
               placeholder="Find a page" class="block h-[40px] w-full rounded-md border border-white/30 bg-[#111C36] pl-11 pr-3 text-[14.5px] text-white placeholder:text-white/70 focus:border-white focus:outline-none focus:ring-1 focus:ring-white">
        <div x-show="open && q.trim() !== ''" x-cloak class="absolute left-0 right-0 top-[46px] z-50 overflow-hidden rounded-lg border border-brand-ink/10 bg-white py-1 text-brand-ink shadow-[0_16px_36px_-12px_rgba(14,42,51,.45)]">
            <template x-for="p in found" :key="p.url"><a :href="p.url" class="block px-4 py-2.5 text-[14px] hover:bg-brand-ink/5" x-text="p.label"></a></template>
            <p x-show="found.length === 0" class="px-4 py-3 text-[13.5px] text-brand-ink/60">No page found.</p>
        </div>
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
