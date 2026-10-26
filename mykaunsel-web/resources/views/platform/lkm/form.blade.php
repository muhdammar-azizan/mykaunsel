<x-platform-layout title="Add LKM record">
    <a href="{{ route('platform.lkm') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        LKM Directory
    </a>

    <x-platform.page-header class="mt-3" title="Add LKM record" subtitle="A counselor can only register on MyKaunsel if their details match a record here." />

    <x-platform.panel class="mt-5 max-w-[760px]">
        <form method="POST" action="{{ route('platform.lkm.store') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            @include('platform.lkm._fields')

            <div class="mt-5 flex justify-end gap-2">
                <a href="{{ route('platform.lkm') }}" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</a>
                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">Add record</button>
            </div>
        </form>
    </x-platform.panel>
</x-platform-layout>
