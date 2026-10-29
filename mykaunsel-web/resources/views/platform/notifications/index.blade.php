@php
    $pill = 'inline-flex min-h-[36px] items-center rounded-full border px-4 text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $tabs = [
        ['key' => 'all', 'label' => 'All', 'count' => $counts['all'], 'url' => route('platform.notifications', array_filter(['group' => $group]))],
        ['key' => 'unread', 'label' => 'Unread', 'count' => $counts['unread'], 'url' => route('platform.notifications', array_filter(['tab' => 'unread', 'group' => $group]))],
    ];
@endphp

<x-platform-layout title="Notifications">
    <x-platform.page-header title="Notifications" subtitle="Things that just happened. For what is waiting for you right now, see the Dashboard." />

    @if (session('status'))
        <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[13.5px] font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @if (session('notice'))
        <div class="mt-4 rounded-2xl border border-brand-yellow/60 bg-brand-yellow/15 px-5 py-3 text-[13.5px] font-semibold text-[#6B4C0C]" role="status">{{ session('notice') }}</div>
    @endif

    <x-platform.status-tabs :tabs="$tabs" :active="$tab" label="Notification views" />

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap items-center gap-2" aria-label="Filter by kind">
            <a href="{{ route('platform.notifications', array_filter(['tab' => $tab === 'unread' ? 'unread' : null])) }}" @if ($group === null) aria-current="true" @endif
               class="{{ $pill }} {{ $group === null ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5' }}">All kinds</a>
            @foreach ($groups as $key => $label)
                <a href="{{ route('platform.notifications', array_filter(['tab' => $tab === 'unread' ? 'unread' : null, 'group' => $key])) }}" @if ($group === $key) aria-current="true" @endif
                   class="{{ $pill }} {{ $group === $key ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('platform.notifications.read-all') }}">
            @csrf
            @if ($group) <input type="hidden" name="group" value="{{ $group }}"> @endif
            <button type="submit" @disabled($counts['unread'] === 0) class="{{ $pill }} border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5 disabled:cursor-not-allowed disabled:opacity-50">
                Mark all {{ $group ? 'in '.$groups[$group].' ' : '' }}as read{{ $counts['unread'] > 0 ? ' ('.$counts['unread'].')' : '' }}
            </button>
        </form>
    </div>

    <x-platform.panel class="mt-4 !p-0">
        @if ($empty === 'none')
            <div class="px-6 py-14 text-center">
                <p class="text-[14px] font-semibold">No notifications yet</p>
                <p class="mt-1 text-[13px] text-brand-ink/60">They appear here when something happens, such as a new application.</p>
            </div>
        @elseif ($empty === 'no_unread')
            <div class="px-6 py-14 text-center">
                <p class="text-[14px] font-semibold">You're all caught up</p>
                <p class="mt-1 text-[13px] text-brand-ink/60">Nothing is unread. <a href="{{ route('platform.notifications') }}" class="font-semibold text-brand-blue hover:underline">See all notifications</a></p>
            </div>
        @elseif ($empty === 'no_match')
            <div class="px-6 py-14 text-center">
                <p class="text-[14px] font-semibold">No notifications match this filter</p>
                <p class="mt-1 text-[13px] text-brand-ink/60"><a href="{{ route('platform.notifications') }}" class="font-semibold text-brand-blue hover:underline">Clear the filter</a></p>
            </div>
        @else
            <ul class="divide-y divide-brand-ink/[.06]">
                @foreach ($items as $item)
                    <li class="flex items-stretch">
                        <div class="min-w-0 flex-1">@include('platform.notifications._item', ['item' => $item])</div>
                        <form method="POST" action="{{ route($item['unread'] ? 'platform.notifications.read' : 'platform.notifications.unread', $item['id']) }}" class="flex shrink-0 items-center pr-4">
                            @csrf
                            @foreach ($query as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <button type="submit" class="rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-brand-blue transition hover:bg-brand-blue/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
                                {{ $item['unread'] ? 'Mark as read' : 'Mark as unread' }}<span class="sr-only">: {{ $item['title'] }} {{ $item['label'] }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-platform.panel>

    <div class="mt-5">{{ $page->links() }}</div>

    <p class="mt-4 text-[12.5px] text-brand-ink/60">Read notifications are deleted after {{ $readDays }} days and unread ones after {{ $unreadDays }} days.</p>
</x-platform-layout>
