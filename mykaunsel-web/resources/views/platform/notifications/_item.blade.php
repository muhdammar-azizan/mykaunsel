{{-- One notification, as a button that POSTs to the server's open route (it marks the item read and goes to its target; ownership is checked there).
     Unread = a filled dot AND bold text AND the words "Unread" for screen readers: never colour alone. Everything shown is escaped by Blade. --}}
@php($dim = $item['handled'] || $item['gone'])

<form method="POST" action="{{ route('platform.notifications.open', $item['id']) }}">
    @csrf
    <button type="submit" class="flex w-full items-start gap-3 px-5 py-3 text-left transition hover:bg-brand-ink/5 focus:outline-none focus-visible:bg-brand-ink/5 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue {{ $dim ? 'opacity-60' : '' }}">
        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $item['unread'] ? 'bg-brand-blue' : 'border border-brand-ink/30' }}" aria-hidden="true"></span>
        <span class="min-w-0 flex-1">
            @if ($item['unread'])
                <span class="sr-only">Unread. </span>
            @endif
            <span class="block text-[13.5px] leading-snug {{ $item['unread'] ? 'font-bold text-brand-ink' : 'font-medium text-brand-ink/80' }}">{{ $item['title'] }}</span>
            @if ($item['label'] || $item['detail'])
                <span class="block truncate text-[13px] text-brand-ink/70">{{ $item['label'] }}@if ($item['label'] && $item['detail']) · @endif{{ $item['detail'] }}</span>
            @endif
            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-brand-ink/55">
                <time datetime="{{ $item['iso'] }}" title="{{ $item['full_time'] }}">{{ $item['ago'] }}</time>
                @if ($item['handled'])
                    <span class="rounded-full bg-brand-ink/10 px-2 py-0.5 text-[10.5px] font-semibold text-brand-ink/70">Already reviewed</span>
                @elseif ($item['gone'])
                    <span class="rounded-full bg-brand-ink/10 px-2 py-0.5 text-[10.5px] font-semibold text-brand-ink/70">No longer available</span>
                @endif
            </span>
        </span>
    </button>
</form>
