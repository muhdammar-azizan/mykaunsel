@php
    $pill = 'inline-flex min-h-[36px] items-center gap-2 rounded-full border px-4 text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
@endphp

<x-portal-layout title="Notifications">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Notifications</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">Things that just happened.</p>

    @if (session('notice'))
        <div class="mt-4 rounded-xl border border-brand-yellow/60 bg-brand-yellow/15 px-5 py-3 text-[13.5px] font-semibold text-[#6B4C0C]" role="status">{{ session('notice') }}</div>
    @endif

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex items-center gap-2" aria-label="Notification views">
            @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
                <a href="{{ route('portal.notifications', $key === 'unread' ? ['tab' => 'unread'] : []) }}" @if ($tab === $key) aria-current="true" @endif
                   class="{{ $pill }} {{ $tab === $key ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 hover:bg-brand-ink/5' }}">{{ $label }} <span class="text-[12px] opacity-80">{{ $counts[$key] }}</span></a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('portal.notifications.read-all') }}">
            @csrf
            <button type="submit" @disabled($counts['unread'] === 0) class="{{ $pill }} border-brand-ink/20 hover:bg-brand-ink/5 disabled:cursor-not-allowed disabled:opacity-50">Mark all as read</button>
        </form>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-[#E4E2DE] bg-white">
        @forelse ($items as $item)
            <div class="flex items-stretch border-t border-[#EEEDEA] first:border-t-0">
                <div class="min-w-0 flex-1">@include('platform.notifications._item', ['item' => $item, 'p' => 'portal'])</div>
                <form method="POST" action="{{ route($item['unread'] ? 'portal.notifications.read' : 'portal.notifications.unread', $item['id']) }}" class="flex shrink-0 items-center pr-4">
                    @csrf
                    <input type="hidden" name="page" value="{{ $page->currentPage() }}">
                    @if ($tab === 'unread')<input type="hidden" name="tab" value="unread">@endif
                    <button type="submit" class="rounded text-[12.5px] font-semibold text-brand-blue hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">{{ $item['unread'] ? 'Mark read' : 'Mark unread' }}</button>
                </form>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-[14px] text-brand-ink/60">{{ $tab === 'unread' ? 'Nothing is unread.' : 'No notifications yet.' }}</p>
        @endforelse
    </div>

    @if ($page->hasPages())
        <div class="mt-5">{{ $page->links() }}</div>
    @endif
</x-portal-layout>
