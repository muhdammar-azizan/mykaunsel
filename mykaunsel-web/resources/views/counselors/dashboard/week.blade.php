<x-portal-layout>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Calendar') }}</h1>

        <nav aria-label="{{ __('Choose a week') }}" class="flex flex-wrap items-center gap-2">
            @if ($week['previous'])
                <a href="{{ route('counselor.week', ['week' => $week['previous']]) }}" class="inline-flex min-h-[44px] items-center rounded-md border border-brand-ink/20 px-3 text-sm font-medium text-brand-ink">&larr; {{ __('Previous week') }}</a>
            @endif
            @unless ($week['is_this_week'])
                <a href="{{ route('counselor.week') }}" class="inline-flex min-h-[44px] items-center rounded-md border border-brand-ink/20 px-3 text-sm font-medium text-brand-ink">{{ __('This week') }}</a>
            @endunless
            @if ($week['next'])
                <a href="{{ route('counselor.week', ['week' => $week['next']]) }}" class="inline-flex min-h-[44px] items-center rounded-md border border-brand-ink/20 px-3 text-sm font-medium text-brand-ink">{{ __('Next week') }} &rarr;</a>
            @endif
        </nav>
    </div>

    <p class="mt-1 text-sm text-brand-ink/65">{{ $week['label'] }} · {{ __('Malaysia time') }} · <a href="{{ route('counselor.planner') }}" class="font-semibold text-brand-blue hover:underline">{{ __('Change my schedule') }}</a></p>

    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-7">
        @foreach ($week['days'] as $day)
            <section aria-labelledby="day-{{ $day['date'] }}" class="rounded-xl border bg-white p-3 {{ $day['is_today'] ? 'border-brand-blue ring-1 ring-brand-blue' : 'border-[#E4E2DE]' }} {{ $day['is_past'] ? 'opacity-70' : '' }}">
                <h2 id="day-{{ $day['date'] }}" class="flex items-center justify-between gap-2 text-sm font-semibold text-brand-ink">
                    <span>{{ $day['name'] }} <span class="font-normal text-brand-ink/65">{{ $day['label'] }}</span></span>
                    @if ($day['is_today'])
                        <span class="rounded-full bg-brand-blue px-2 py-0.5 text-xs font-medium text-white">{{ __('Today') }}</span>
                    @endif
                </h2>

                <ul class="mt-2 space-y-2">
                    @forelse ($day['items'] as $item)
                        <li class="rounded-lg border p-2 text-sm
                            {{ $item['kind'] === 'session' ? 'border-blue-200 bg-blue-50' : ($item['kind'] === 'block' ? 'border-[#E4E2DE] bg-[#F5F4F1]' : 'border-green-200 bg-green-50') }}">
                            <p class="font-medium text-brand-ink">{{ $item['time'] }}</p>
                            @if ($item['kind'] === 'session')
                                <p class="text-blue-800">
                                    @if ($item['link'])
                                        <a href="{{ $item['link'] }}" class="font-semibold underline">{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                </p>
                            @elseif ($item['kind'] === 'block')
                                <p class="text-brand-ink/70">{{ __('Blocked') }}@if ($item['title']) · {{ $item['title'] }}@endif</p>
                            @else
                                <p class="text-green-800">{{ $item['title'] }}</p>
                            @endif
                            @if ($item['detail'])
                                <p class="text-xs text-brand-ink/65">{{ $item['detail'] }}</p>
                            @endif
                            @if ($item['hidden'])
                                <p class="mt-1 inline-block rounded-full bg-brand-yellow/25 px-2 py-0.5 text-xs font-medium text-[#6B4C0C]">{{ $item['hidden'] === 'block' ? __('Hidden from clients: blocked time') : __('Hidden from clients: too close to a session') }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-brand-ink/50">{{ __('Nothing') }}</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</x-portal-layout>
