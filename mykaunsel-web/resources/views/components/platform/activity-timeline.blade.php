@props(['timeline', 'historyUrl'])

{{-- Activity timeline, newest first. $timeline: list of ['at', 'title', 'detail', 'actor', 'tone']. --}}
<x-platform.panel>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-[14px] font-bold">Activity</h2>
        <a href="{{ $historyUrl }}" class="text-[13px] font-semibold text-brand-blue hover:underline">View full history →</a>
    </div>
    <ol class="mt-3 space-y-0">
        @foreach ($timeline as $event)
            @php
                $dot = ['good' => 'bg-emerald-500', 'bad' => 'bg-brand-red', 'info' => 'bg-brand-blue', 'neutral' => 'bg-brand-ink/35'][$event['tone']];
            @endphp
            <li class="relative flex gap-3 pb-5 last:pb-0">
                @unless ($loop->last)
                    <span class="absolute left-[5px] top-4 h-full w-px bg-brand-ink/10" aria-hidden="true"></span>
                @endunless
                <span class="relative mt-1.5 h-[11px] w-[11px] shrink-0 rounded-full ring-4 ring-white {{ $dot }}"></span>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-semibold">{{ $event['title'] }}</p>
                    @if ($event['detail'])
                        <p class="mt-0.5 break-words text-[12.5px] text-brand-ink/65">{{ $event['detail'] }}</p>
                    @endif
                    <p class="mt-0.5 text-[11.5px] text-brand-ink/50" title="{{ $event['at']->format('j M Y, g:i A') }}">
                        {{ $event['actor'] }} · {{ $event['at']->format('j M Y, g:i A') }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
</x-platform.panel>
