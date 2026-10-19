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

    <div id="wizardRoot" class="flex min-h-screen md:h-screen md:overflow-hidden">

        <!-- LEFT COLUMN — static -->
        <aside class="hidden w-[45%] shrink-0 relative overflow-hidden md:block" style="background:linear-gradient(150deg,#0B3A42 0%,#0E2A33 55%,#08202A 100%)">
            <img src="/images/placeholder.jpg" data-slot="{{ $imageSlot }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <span class="pointer-events-none absolute inset-0" style="background:linear-gradient(to bottom, rgba(14,42,51,.45) 0%, rgba(14,42,51,.25) 40%, rgba(14,42,51,.85) 82%, #08202A 100%)"></span>

            <div class="relative flex h-full flex-col justify-between p-10 lg:p-12">
                <x-mykaunsel-logo href="/" variant="dark" />
                <div class="max-w-[26ch]">
                    <p class="text-[24px] font-semibold leading-[1.2] tracking-tightest text-cream lg:text-[28px]" style="text-wrap:pretty">{{ $asideHeadline }}</p>
                    <p class="mt-4 text-[15px] leading-relaxed text-cream/65">{{ $asideSubtext }}</p>
                </div>
            </div>
        </aside>

        <!-- RIGHT COLUMN — scrollable -->
        <main class="flex flex-1 flex-col items-center overflow-y-auto px-6 py-10 md:px-10">
            <div class="masuk w-full" style="max-width: {{ $contentMaxWidth }}">

                <x-mykaunsel-logo class="mb-8 md:hidden" />

                {{ $slot }}
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
