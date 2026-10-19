@props(['title' => 'Platform Admin'])

@php
    $nav = [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'route' => 'platform.dashboard', 'icon' => 'M3 12h5v8H3zM10 7h5v13h-5zM17 3h5v17h-5z'],
        ]],
        ['group' => 'Operations', 'items' => [
            ['label' => 'Organizations', 'route' => 'platform.organizations', 'icon' => 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18zM6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M10 6h4M10 10h4M10 14h4'],
            ['label' => 'Counselors', 'route' => 'platform.counselors', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ]],
        ['group' => 'Trust & Safety', 'items' => [
            ['label' => 'Complaints', 'route' => 'platform.complaints', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        ]],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ config('app.name', 'MyKaunsel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-deep font-sans text-brand-ink antialiased" x-data="{ menuOpen: false }">

    {{-- TOP BAR --}}
    <header class="sticky top-2.5 z-40 mx-2.5 mt-2.5 flex h-[72px] items-center justify-between rounded-[26px] bg-white px-4 shadow-[0_12px_32px_-14px_rgba(0,0,0,.35)] sm:px-6">
        <div class="flex items-center gap-3">
            <button type="button" class="grid h-11 w-11 place-items-center rounded-full hover:bg-brand-ink/5 md:hidden" aria-label="Open menu" @click="menuOpen = !menuOpen">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('platform.dashboard') }}" aria-label="MyKaunsel">
                <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="h-10 w-auto">
            </a>
        </div>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-3 hover:bg-brand-ink/5" @click="open = !open" aria-haspopup="true" :aria-expanded="open">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-blue text-[13px] font-bold text-white">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
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
    </header>

    <div class="flex">
        {{-- SIDEBAR --}}
        <aside class="fixed inset-y-0 left-0 z-30 w-[260px] -translate-x-full overflow-y-auto bg-brand-deep px-3 pb-6 pt-[96px] transition-transform md:static md:z-auto md:block md:translate-x-0 md:pt-4"
               :class="menuOpen ? 'translate-x-0' : ''">
            <nav class="flex flex-col gap-1" aria-label="Platform admin">
                @foreach ($nav as $section)
                    <div x-data="{ open: true }" class="{{ $section['group'] ? 'mt-3' : '' }}">
                        @if ($section['group'])
                            <button type="button" class="flex min-h-[32px] w-full items-center justify-between px-3 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-[.08em] text-white/45 hover:text-white/70" @click="open = !open">
                                {{ $section['group'] }}
                                <svg class="transition-transform" :class="open ? '' : '-rotate-90'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                        @endif
                        <div x-show="open" class="flex flex-col gap-1">
                            @foreach ($section['items'] as $item)
                                @php $active = request()->routeIs($item['route']); @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex min-h-[46px] items-center gap-3 rounded-xl px-3.5 text-[14px] font-medium transition {{ $active ? 'bg-white text-brand-ink shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                                   @if ($active) aria-current="page" @endif>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>
        <div x-show="menuOpen" x-cloak class="fixed inset-0 z-20 bg-black/40 md:hidden" @click="menuOpen = false"></div>

        {{-- CONTENT PANEL --}}
        <main class="m-2.5 min-h-[calc(100vh-112px)] min-w-0 flex-1 rounded-[28px] bg-white px-5 pb-12 pt-8 shadow-[0_24px_60px_-20px_rgba(0,0,0,.4)] md:ml-0 md:px-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
