<x-auth-layout>
    <h2 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Menunggu Kelulusan') }}</h2>
    <p class="text-sm text-gray-600">
        {{ __('Organisasi ini masih menunggu kelulusan pentadbir platform sebelum boleh digunakan.') }}
    </p>
    <a href="{{ route('context.select') }}" class="mt-4 inline-block text-sm font-semibold text-gray-700 underline">{{ __('Pilih organisasi lain') }}</a>
</x-auth-layout>
