<x-portal-layout title="Time requests">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Time requests') }}</h1>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @php
        $tabClass = fn (bool $on) => 'inline-flex min-h-[44px] items-center rounded-md px-4 text-sm font-semibold '.($on ? 'bg-brand-blue text-white' : 'border border-brand-ink/20 text-brand-ink');
        $pill = [
            'pending' => ['bg-brand-blue/10 text-brand-blue', __('Waiting')],
            'approved' => ['bg-emerald-50 text-emerald-700', __('Approved')],
            'rejected' => ['bg-brand-yellow/25 text-[#6B4C0C]', __('Declined')],
            'expired' => ['bg-gray-100 text-brand-ink/70', __('Expired')],
            'withdrawn' => ['bg-gray-100 text-brand-ink/70', __('Withdrawn')],
        ];
    @endphp

    <nav aria-label="{{ __('Time requests') }}" class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('counselor.requests') }}" class="{{ $tabClass($tab === 'waiting') }}" @if ($tab === 'waiting') aria-current="page" @endif>{{ __('Waiting for you') }} ({{ $waitingCount }})</a>
        <a href="{{ route('counselor.requests', ['tab' => 'answered']) }}" class="{{ $tabClass($tab === 'answered') }}" @if ($tab === 'answered') aria-current="page" @endif>{{ __('Answered') }}</a>
    </nav>

    <div class="mt-5">
        {{-- Mode B: people who asked for a time inside the weekly hours. Approving books the session. --}}
        <section aria-labelledby="requests-title">
            <h2 id="requests-title" class="sr-only">{{ $tab === 'answered' ? __('Answered requests') : __('Requests waiting for you') }}</h2>
            <p class="text-[14px] text-brand-ink/65">
                @if ($tab === 'answered')
                    {{ __('Requests answered, closed or taken back in the last :days days, newest first.', ['days' => $answeredDays]) }}
                @else
                    {{ __('A request closes by itself if you do not answer in time. Approving books the session.') }}
                @endif
            </p>

            <div class="mt-3 space-y-3">
                @forelse ($requests as $r)
                    <div class="rounded-xl border border-[#E4E2DE] bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-medium text-brand-ink">{{ $r['client'] }}</p>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold {{ $pill[$r['status']][0] ?? $pill['expired'][0] }}">{{ $pill[$r['status']][1] ?? $r['status'] }}</span>
                        </div>
                        <p class="text-[14px] text-brand-ink/65">{{ $r['date'] }}, {{ $r['time'] }} ({{ __('Malaysia time') }}) · {{ $r['minutes'] }} {{ __('min') }} · {{ $r['mode'] === 'online' ? __('Online') : __('In-person').($r['place'] ? ' · '.$r['place'] : '') }}@if ($manyContexts && $r['context']) · {{ $r['context'] }}@endif</p>
                        @if ($r['note'])
                            <p class="mt-2 text-sm text-brand-ink"><span class="font-medium">{{ __('Their note') }}:</span> {{ $r['note'] }}</p>
                        @endif

                        @if ($tab === 'waiting')
                            <p class="mt-1 text-xs text-brand-ink/55">{{ __('Answer by') }} {{ $r['until'] }}</p>

                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <form method="POST" action="{{ route('counselor.requests.approve', $r['id']) }}">
                                    @csrf
                                    <button class="min-h-[44px] px-4 rounded-md bg-brand-blue text-white text-sm font-medium">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('counselor.requests.decline', $r['id']) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label for="reason-{{ $r['id'] }}" class="block text-xs font-medium text-brand-ink/70">{{ __('Reason (optional, they will see it)') }}</label>
                                        <input id="reason-{{ $r['id'] }}" name="reason" type="text" maxlength="300" class="mt-1 block min-h-[44px] rounded-md border-brand-ink/20">
                                    </div>
                                    <button class="min-h-[44px] px-4 rounded-md border border-brand-ink/20 text-brand-ink text-sm font-medium">{{ __('Decline') }}</button>
                                </form>
                            </div>
                        @else
                            @if ($r['status'] === 'rejected' && $r['reason'])
                                <p class="mt-2 text-sm text-brand-ink"><span class="font-medium">{{ __('Reason you gave') }}:</span> {{ $r['reason'] }}</p>
                            @endif
                            @if ($r['booking'])
                                <p class="mt-2"><a href="{{ route('counselor.appointments.show', $r['booking']) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">{{ __('Open the booking') }}</a></p>
                            @endif
                        @endif
                    </div>
                @empty
                    @if ($tab === 'answered')
                        <p class="rounded-xl border border-[#E4E2DE] bg-white p-6 text-brand-ink/65">{{ __('No answered requests in the last :days days.', ['days' => $answeredDays]) }}</p>
                    @else
                        <x-empty-state-sessions kind="requests" :size="150" class="py-10" :title="__('No requests waiting.')" />
                    @endif
                @endforelse
            </div>

            <div class="mt-4">{{ $requests->links() }}</div>
        </section>
    </div>
</x-portal-layout>
