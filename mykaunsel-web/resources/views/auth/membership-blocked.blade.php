<x-auth-layout>
    <h2 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Akses Disekat') }}</h2>
    <p class="text-sm text-gray-600">
        {{ __('Keahlian anda dalam organisasi ini tidak aktif. Sila hubungi pentadbir organisasi anda.') }}
    </p>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm font-semibold text-gray-700 underline">{{ __('Log keluar') }}</button>
    </form>
</x-auth-layout>
