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
        <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
            <!-- Branding panel -->
            <div class="hidden lg:flex flex-col justify-between bg-indigo-600 text-white p-12">
                <a href="/">
                    <x-application-logo class="w-16 h-16 fill-current text-white" />
                </a>
                <div>
                    <h1 class="text-3xl font-bold">MyKaunsel</h1>
                    <p class="mt-2 text-indigo-100">Platform pengurusan tempahan sesi kaunseling untuk Malaysia.</p>
                </div>
                <p class="text-sm text-indigo-200">&copy; {{ date('Y') }} MyKaunsel</p>
            </div>

            <!-- Form panel -->
            <div class="flex flex-col justify-center items-center px-6 py-12 bg-gray-100">
                <div class="lg:hidden mb-6">
                    <a href="/">
                        <x-application-logo class="w-16 h-16 fill-current text-gray-500" />
                    </a>
                </div>

                <div class="w-full sm:max-w-md px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
