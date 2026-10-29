<x-platform-layout title="Notifications">
    <x-platform.page-header title="Notifications" subtitle="Things that just happened. For what is waiting for you right now, see the Dashboard." />

    @if ($notice)
        <div class="mt-4 rounded-2xl border border-brand-yellow/60 bg-brand-yellow/15 px-5 py-3 text-[13.5px] font-semibold text-[#6B4C0C]" role="status">{{ $notice }}</div>
    @endif

    <x-platform.panel class="mt-5 !p-0">
        @if ($items === [])
            <p class="px-6 py-12 text-center text-[13.5px] text-brand-ink/60">You're all caught up. Nothing has happened yet.</p>
        @else
            <ul class="divide-y divide-brand-ink/[.06]">
                @foreach ($items as $item)
                    <li>@include('platform.notifications._item', ['item' => $item])</li>
                @endforeach
            </ul>
        @endif
    </x-platform.panel>

    <div class="mt-5">{{ $page->links() }}</div>
</x-platform-layout>
