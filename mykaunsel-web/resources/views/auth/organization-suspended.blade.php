<x-auth-layout>
    <h2 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Organisasi Disekat') }}</h2>
    <p class="text-sm text-gray-600">
        {{ __('Organisasi ini telah disekat. Sila hubungi pasukan sokongan MyKaunsel untuk maklumat lanjut.') }}
    </p>
    <a href="{{ route('context.select') }}" class="mt-4 inline-block text-sm font-semibold text-gray-700 underline">{{ __('Pilih organisasi lain') }}</a>
</x-auth-layout>
