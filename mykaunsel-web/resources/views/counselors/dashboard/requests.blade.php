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

    <div class="mt-5">
            {{-- Mode B: people who asked for a time inside the weekly hours. Approving books the session. --}}
        <section aria-labelledby="requests">
                        <p class="text-[14px] text-brand-ink/65">{{ __('A request closes by itself if you do not answer in time. Approving books the session.') }}</p>

            <div class="mt-3 space-y-3">
                @forelse ($requests as $r)
                    <div class="rounded-xl border border-[#E4E2DE] bg-white p-4">
                        <p class="font-medium text-brand-ink">{{ $r['client'] }}</p>
                        <p class="text-[14px] text-brand-ink/65">{{ $r['date'] }}, {{ $r['time'] }} ({{ __('Malaysia time') }}) · {{ $r['minutes'] }} {{ __('min') }} · {{ $r['mode'] === 'online' ? __('Online') : __('In-person').($r['place'] ? ' · '.$r['place'] : '') }}</p>
                        @if ($r['note'])
                            <p class="mt-2 text-sm text-brand-ink"><span class="font-medium">{{ __('Their note') }}:</span> {{ $r['note'] }}</p>
                        @endif
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
                    </div>
                @empty
                    <x-empty-state-sessions kind="requests" :size="150" class="py-10" :title="__('No requests waiting.')" />
                @endforelse
            </div>
        </section>
    </div>
</x-portal-layout>
