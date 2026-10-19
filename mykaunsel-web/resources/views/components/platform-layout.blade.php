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
<body class="bg-brand-deep font-sans text-brand-ink antialiased" x-data="{ menuOpen: false }">

    <x-platform.topbar />

    <div class="flex">
        <x-platform.sidebar />

        <main class="m-2.5 min-h-[calc(100vh-112px)] min-w-0 flex-1 rounded-[28px] bg-white px-5 pb-12 pt-8 shadow-[0_24px_60px_-20px_rgba(0,0,0,.4)] md:ml-0 md:px-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
