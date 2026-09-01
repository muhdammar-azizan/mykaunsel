<x-auth-layout>
    <h2 class="text-lg font-semibold text-gray-800 mb-4">
        {{ __('Pilih Organisasi') }}
    </h2>

    <form method="POST" action="{{ route('context.store') }}" class="space-y-4">
        @csrf

        @forelse ($memberships as $membership)
            <label class="flex items-center justify-between border rounded-md px-4 py-3 cursor-pointer hover:bg-gray-50">
                <span>
                    <span class="block font-medium text-gray-900">{{ $membership->organization->name }}</span>
                    <span class="block text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $membership->role->value)) }}</span>
                </span>
                <input
                    type="radio"
                    name="org_id"
                    value="{{ $membership->org_id }}"
                    onchange="document.getElementById('role_input').value = '{{ $membership->role->value }}'"
                    required
                />
            </label>
        @empty
            <p class="text-sm text-gray-500">{{ __('Tiada keahlian organisasi ditemui.') }}</p>
        @endforelse

        <input type="hidden" id="role_input" name="role" value="">

        <x-button type="submit" class="w-full">
            {{ __('Teruskan') }}
        </x-button>
    </form>
</x-auth-layout>
