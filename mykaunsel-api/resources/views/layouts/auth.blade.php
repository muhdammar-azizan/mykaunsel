<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'MyKaunsel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans text-navy antialiased">

    <div class="flex min-h-screen md:h-screen md:overflow-hidden">

        <!-- LEFT COLUMN -->
        <aside class="relative z-10 hidden w-[45%] shrink-0 bg-cream md:block">
            <div class="flex h-full flex-col p-8">
                <x-mykaunsel-logo />

                <div class="mt-8 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[20px] text-cream"
                     style="background:linear-gradient(150deg,#0B3A42 0%,#0E2A33 55%,#08202A 100%); box-shadow: 0 24px 60px -24px rgba(14,42,51,.35)">
                    <div class="relative min-h-0 flex-1" style="aspect-ratio: 4 / 3">
                        <img src="/images/placeholder.jpg" data-slot="{{ $imageSlot }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-24" style="background:linear-gradient(to bottom, rgba(11,58,66,0) 0%, rgba(11,58,66,.85) 100%)"></span>
                    </div>
                    <div class="shrink-0 p-7">
                        <p id="authAsideTitle" class="max-w-[24ch] text-[22px] font-semibold leading-[1.2] tracking-tightest lg:text-[26px]" style="text-wrap:pretty">{{ $asideTitle }}</p>
                        <p id="authAsideSubtitle" class="mt-3 text-[14.5px] leading-relaxed text-cream/65">{{ $asideSubtitle }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- RIGHT COLUMN -->
        <main class="relative flex flex-1 flex-col items-center overflow-hidden px-6 py-10 md:overflow-y-auto md:px-10">
            <div class="mesh-wrap" aria-hidden="true">
                <div class="plume">
                    <div class="strand s1"><div class="strand-inner"></div><div class="strand-lines"></div></div>
                    <div class="strand s2"><div class="strand-inner"></div><div class="strand-lines"></div></div>
                    <div class="strand s3"><div class="strand-inner"></div><div class="strand-lines"></div></div>
                    <div class="strand s4"><div class="strand-inner"></div><div class="strand-lines"></div></div>
                </div>
                <div class="mesh-veil"></div>
            </div>

            <div class="my-auto w-full" style="max-width: {{ $contentMaxWidth }}; position:relative; z-index:1;">
                <x-mykaunsel-logo class="mb-8 md:hidden" />

                {{ $slot }}
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
