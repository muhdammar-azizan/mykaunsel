@php
    // Items without a 'url' are shown as "Soon". Give them a url when the page is built.
    $items = [
        [
            ['label' => 'Account settings', 'url' => route('platform.account'), 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.5-6 8-6s8 2 8 6'],
            ['label' => 'Security', 'url' => route('platform.account.security'), 'icon' => 'M20 13c0 5-3.5 7.5-7.5 8.5a1.2 1.2 0 0 1-1 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.79 17 5 19 5a1 1 0 0 1 1 1z'],
            ['label' => 'Account activity', 'url' => route('platform.account.security').'#activity', 'icon' => 'M22 12h-4l-3 9L9 3l-3 9H2'],
            ['label' => 'Audit log', 'url' => route('platform.audit-log'), 'icon' => 'M9 12h6M9 16h6M9 8h6M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'],
            ['label' => 'Notification settings', 'url' => route('platform.account.notifications'), 'icon' => 'M18 8a6 6 0 1 0-12 0c0 4-2 5-2 7h16c0-2-2-3-2-7zM9.5 19a2.5 2.5 0 0 0 5 0'],
            ['label' => 'Language', 'value' => 'English', 'icon' => 'M5 8l6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6'],
        ],
        [
            ['label' => 'Help & support', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01'],
        ],
    ];
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" class="group relative grid h-10 w-10 place-items-center rounded-full transition hover:ring-2 hover:ring-white/30" @click="open = !open" aria-haspopup="true" :aria-expanded="open" aria-label="Account menu">
        <span class="grid h-10 w-10 place-items-center overflow-hidden rounded-full bg-brand-yellow text-[13px] font-bold text-brand-ink">
            @if (auth()->user()->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-full w-full object-cover">
            @else
                {{ auth()->user()->initials }}
            @endif
        </span>
        <span role="tooltip" x-show="!open" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-brand-ink px-2.5 py-1 text-[12px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100">Account</span>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition duration-150 ease-out"
         x-transition:enter-start="scale-95 opacity-0"
         x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transition duration-100 ease-in"
         x-transition:leave-start="scale-100 opacity-100"
         x-transition:leave-end="scale-95 opacity-0"
         class="absolute right-0 top-[52px] z-50 w-[300px] origin-top-right rounded-2xl bg-white py-2 shadow-[0_20px_44px_-14px_rgba(14,42,51,.45)]">

        <div class="px-5 pb-3 pt-3">
            <p class="text-[16px] font-bold leading-tight">{{ auth()->user()->name }}</p>
            <p class="mt-0.5 truncate text-[12.5px] text-brand-ink/55">{{ auth()->user()->email }}</p>
            <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-blue">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-blue"></span>Platform Admin
            </span>
        </div>

        @foreach ($items as $group)
            <div class="mx-4 border-t border-brand-ink/10"></div>
            <div class="px-2 py-1.5">
                @foreach ($group as $item)
                    @if (isset($item['url']))
                        <a href="{{ $item['url'] }}" class="flex min-h-[44px] items-center gap-3.5 rounded-lg px-3 text-[14px] transition hover:bg-brand-ink/5">
                            <svg class="shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                            <span class="flex-1">{{ $item['label'] }}</span>
                        </a>
                    @else
                        <div class="flex min-h-[44px] cursor-not-allowed items-center gap-3.5 rounded-lg px-3 text-[14px] text-brand-ink/60" title="Coming soon" aria-disabled="true">
                            <svg class="shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @isset($item['value'])
                                <span class="text-[13px] text-brand-ink/45">{{ $item['value'] }}</span>
                            @endisset
                            <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-brand-ink/55">Soon</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach

        <div class="mx-4 border-t border-brand-ink/10"></div>
        <form method="POST" action="{{ route('logout') }}" class="px-2 pt-1.5">
            @csrf
            <button type="submit" class="flex min-h-[44px] w-full items-center gap-3.5 rounded-lg px-3 text-left text-[14px] font-medium transition hover:bg-brand-red/10 hover:text-brand-red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Log out
            </button>
        </form>
    </div>
</div>
