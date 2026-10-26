<x-guest-layout>
    <div class="max-w-7xl mx-auto px-6 py-24 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">
            Sokongan kaunseling, di hujung jari anda
        </h1>
        <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
            MyKaunsel menghubungkan pelajar, kakitangan, dan orang awam di Malaysia dengan
            kaunselor berdaftar melalui satu platform tempahan sesi yang mudah dan selamat.
        </p>

        <div class="mt-8 flex items-center justify-center gap-4">
            @auth
                <a href="{{ route('dashboard') }}" class="px-6 py-3 rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                    Ke Papan Pemuka
                </a>
            @else
                {{-- Individuals sign up in the mobile app, not on the web. --}}
                <a href="{{ config('registration.mobile_app_url') }}" class="px-6 py-3 rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                    Get the mobile app
                </a>
                <a href="{{ route('login') }}" class="px-6 py-3 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Log Masuk
                </a>
            @endauth
        </div>
    </div>

    {{-- Placeholder until the app is in the stores (see config/registration.php, mobile_app_url). --}}
    <section id="get-the-app" class="scroll-mt-8 border-t border-gray-100 bg-gray-50">
        <div class="max-w-3xl mx-auto px-6 py-16 text-center">
            <h2 class="text-2xl font-semibold text-gray-900">Get the mobile app</h2>
            <p class="mt-3 text-gray-600">
                Looking for support? Clients sign up, find a counselor and book sessions in the MyKaunsel mobile app.
                The app is coming soon to Google Play and the App Store.
            </p>
            <div class="mt-6 flex items-center justify-center gap-3 text-sm text-gray-500">
                <span class="px-4 py-2 rounded-md border border-gray-300 bg-white">Google Play — coming soon</span>
                <span class="px-4 py-2 rounded-md border border-gray-300 bg-white">App Store — coming soon</span>
            </div>
            <p class="mt-6 text-sm text-gray-500">
                Are you an organization or a registered counselor?
                <a href="{{ route('register.type') }}" class="font-medium text-indigo-600 hover:underline">Sign up here</a>.
            </p>
        </div>
    </section>
</x-guest-layout>
