@props(['title' => null, 'header' => null])

{{-- The shell of the counselor and organization admin portals: a dark header that stays on top, a sidebar, the page, and a footer that stays at the
     bottom. Only the page scrolls. Pages can pass a `header` slot (their old heading) or a `title`. Platform Admin has its own layout. --}}
@php
    $role = session('current_role');
    $items = collect(config('portal-nav.'.$role, []))->map(function (array $item) {
        $exists = isset($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']);

        return $item + ['url' => $exists ? route($item['route']) : null, 'active' => $exists && request()->routeIs($item['match'] ?? $item['route'])];
    })->all();
    $pageTitle = $title ?? ($header ? trim(strip_tags((string) $header)) : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle ? $pageTitle.' — ' : '' }}{{ config('app.name', 'MyKaunsel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Moving between two portal pages: the browser keeps the sidebar, the header and the footer in place and only fades the page in between
           (a "cross-document view transition": Chrome and Edge; other browsers simply load the page as before). Both pages must opt in, so
           this only ever happens from one portal page to another, never to or from the login or the public pages. */
        @view-transition { navigation: auto; }
        ::view-transition-old(root), ::view-transition-new(root) { animation-duration: 140ms; animation-timing-function: ease-out; }
        ::view-transition-group(portal-sidebar), ::view-transition-group(portal-header), ::view-transition-group(portal-footer) { animation-duration: 0s; }
        @media (prefers-reduced-motion: reduce) {
            ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; }
        }

        /* On paper the page is just the page: no header, sidebar or footer, and it may run over several sheets. */
        @media print {
            body { height: auto !important; overflow: visible !important; display: block !important; }
            header, footer, aside, body > a.sr-only { display: none !important; }
            body > div { display: block !important; }
            main { overflow: visible !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-white font-geist text-brand-ink antialiased" x-data="{ menuOpen: false, collapsed: (() => { try { return localStorage.getItem('portal.collapsed') === '1'; } catch (e) { return false; } })(),
                       toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('portal.collapsed', this.collapsed ? '1' : '0'); } catch (e) {} } }" @keydown.escape.window="menuOpen = false">
    <a href="#portal-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-[14px] focus:font-semibold">Skip to the page</a>

    <div class="flex min-h-0 flex-1">
        <x-portal.sidebar :items="$items" />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-portal.topbar :items="$items" />

            <main id="portal-main" tabindex="-1" class="min-h-0 min-w-0 flex-1 overflow-y-auto scroll-subtle px-4 pb-10 pt-6 focus:outline-none sm:px-8 lg:px-[60px]">
                @if ($header)
                    <div class="mb-5 [&_h2]:text-[24px] [&_h2]:font-bold [&_h2]:leading-tight [&_h2]:tracking-tight [&_h2]:text-brand-ink">{{ $header }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Full width, under the sidebar too. --}}
    <x-portal.footer />
    <x-confirm-dialog />
</body>
</html>
