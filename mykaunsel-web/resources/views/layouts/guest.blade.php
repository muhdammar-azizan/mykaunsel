<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MyKaunsel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen bg-white">
            <header class="border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <a href="/" class="flex items-center gap-2">
                        <x-application-logo class="w-8 h-8 fill-current text-indigo-600" />
                        <span class="font-semibold text-lg">MyKaunsel</span>
                    </a>

                    <nav class="flex items-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Log in') }}</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="text-sm px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-500">{{ __('Register') }}</a>
                            @endif
                        @endauth
                    </nav>
                </div>
            </header>

            <main>
                {{ $slot }}
            </main>

            <footer class="border-t border-gray-100 mt-24">
                <div class="max-w-7xl mx-auto px-6 py-8 text-sm text-gray-500">
                    &copy; {{ date('Y') }} MyKaunsel. All rights reserved.
                </div>
            </footer>
        </div>
    </body>
</html>
