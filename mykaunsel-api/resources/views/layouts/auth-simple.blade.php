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
<body class="font-sans text-navy antialiased bg-cream">

    <div class="p-8">
        <x-mykaunsel-logo />
    </div>

    <div class="flex min-h-[calc(100vh-104px)] flex-col items-center justify-center px-5 pb-16">
        <div class="w-full" style="max-width: {{ $contentMaxWidth }}">
            {{ $slot }}
        </div>
    </div>

    @stack('scripts')
</body>
</html>
