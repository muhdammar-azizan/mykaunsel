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
<body class="bg-[#0F69B0] bg-[linear-gradient(135deg,#3A92D0_0%,#1469B0_50%,#0A4F88_100%)] bg-fixed font-sans text-brand-ink antialiased" x-data="{ menuOpen: false }">

    <x-platform.topbar />

    {{-- One joined light panel: sidebar + content share the same surface and the same rounded outer corners. --}}
    <div class="mx-2.5 mb-2.5 flex min-h-[calc(100vh-82px)] overflow-hidden rounded-[28px] bg-[#F5F5F6]">
        <x-platform.sidebar />

        <main class="min-w-0 flex-1 px-5 pb-12 pt-8 md:px-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
