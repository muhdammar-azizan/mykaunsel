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
                <a href="{{ route('register') }}" class="px-6 py-3 rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                    Mula Sekarang
                </a>
                <a href="{{ route('login') }}" class="px-6 py-3 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Log Masuk
                </a>
            @endauth
        </div>
    </div>
</x-guest-layout>
