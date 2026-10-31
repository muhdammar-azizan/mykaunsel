<x-portal-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Papan Pemuka Organisasi') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900">
                <a href="{{ route('org.members') }}" class="font-semibold text-indigo-700 hover:underline">{{ __('Ahli Organisasi') }}</a>: approve people who joined with a reference code, and manage the codes.
            </div>
        </div>
    </div>
</x-portal-layout>
