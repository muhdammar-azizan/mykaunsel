@props(['name', 'action', 'title', 'submitLabel', 'busyLabel'])

{{-- Confirmation modal (approve, reactivate…). The slot is what happens next, shown under the title. --}}
<x-modal :name="$name" maxWidth="lg" focusable>
    <form method="POST" action="{{ $action }}" x-data="{ busy: false }" @submit="busy = true" class="p-6">
        @csrf
        <h2 class="text-[18px] font-extrabold tracking-tight">{{ $title }}</h2>
        {{ $slot }}
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="$dispatch('close-modal', '{{ $name }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
            <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!busy">{{ $submitLabel }}</span><span x-show="busy" x-cloak>{{ $busyLabel }}</span>
            </button>
        </div>
    </form>
</x-modal>
