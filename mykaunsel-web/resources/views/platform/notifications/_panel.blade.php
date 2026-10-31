{{-- The contents of the bell's panel: the latest items, Today and Earlier. Fetched only when the bell is opened. --}}
@if ($empty)
    <p class="px-5 py-10 text-center text-[13.5px] text-brand-ink/60">You're all caught up.</p>
@else
    @foreach ($groups as $heading => $items)
        <h3 class="px-5 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wide text-brand-ink/50">{{ $heading }}</h3>
        <ul class="divide-y divide-brand-ink/[.06]">
            @foreach ($items as $item)
                <li>@include('platform.notifications._item', ['item' => $item, 'p' => $p ?? 'platform'])</li>
            @endforeach
        </ul>
    @endforeach
@endif
