@props(['title' => 'Platform Admin'])

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
<body class="bg-[#05294A] bg-[radial-gradient(circle_at_0%_0%,#3A92D0_0%,rgba(20,105,176,.6)_14%,rgba(10,79,136,0)_38%)] flex h-screen flex-col overflow-hidden font-sans text-brand-ink antialiased" x-data="{ menuOpen: false, searchOpen: false }">

    <x-platform.topbar />

    @if (app(\App\Services\PlatformSettings::class)->maintenanceOn())
        <a href="{{ route('platform.registration') }}" class="mx-2.5 mb-2.5 flex items-center justify-center gap-2 rounded-xl bg-brand-yellow px-4 py-2 text-[13px] font-bold text-[#4A3405] transition hover:brightness-95">
            Maintenance mode is ON — everyone except Platform Admins is blocked.
            @if ($reopen = app(\App\Services\PlatformSettings::class)->maintenanceReopenAt())
                Switches off by itself {{ $reopen->setTimezone(auth()->user()?->display_timezone ?? config('account.default_timezone'))->format('j M, g:i A') }}.
            @endif
            <span class="underline">Manage</span>
        </a>
    @endif

    {{-- One joined light panel: sidebar + content share the same surface and the same rounded outer corners. --}}
    <div class="mx-2.5 mb-2.5 flex min-h-0 flex-1 overflow-hidden rounded-[28px] bg-[#F5F5F6]">
        <x-platform.sidebar />

        <main class="min-w-0 flex-1 overflow-y-auto scroll-subtle px-5 pb-12 pt-8 md:px-10">
            {{ $slot }}
        </main>
    </div>
    <x-platform.search-palette />
</body>
</html>
