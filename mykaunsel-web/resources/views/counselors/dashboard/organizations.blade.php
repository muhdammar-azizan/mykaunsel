<x-portal-layout title="Organizations">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ __('Organizations') }}</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">{{ __('Where you work: each organization you belong to, and your independent practice. Times, requests and sessions belong to one of them.') }}</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if ($invitations > 0)
        <a href="{{ route('counselor.dashboard') }}" class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-[#FDF4E3] px-5 py-4 text-[14px] font-semibold text-[#6B4C0C] transition hover:brightness-95">
            <span>{{ trans_choice(':count organization has invited you.|:count organizations have invited you.', $invitations, ['count' => $invitations]) }}</span>
            <span class="underline">{{ __('Answer on your Dashboard') }}</span>
        </a>
    @endif

    @php
        $pill = [
            'active' => ['bg-emerald-50 text-emerald-700', __('Active')],
            'suspended' => ['bg-brand-yellow/25 text-[#6B4C0C]', __('Suspended')],
            'offboarded' => ['bg-gray-100 text-brand-ink/70', __('Ended')],
        ];
    @endphp

    <ul class="mt-5 space-y-3">
        @forelse ($rows as $r)
            <li class="rounded-xl border border-[#E4E2DE] bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-brand-ink">{{ $r['name'] }}</p>
                        <p class="text-[14px] text-brand-ink/65">
                            @if ($r['status'] === 'offboarded' && $r['left'])
                                {{ __('Ended on :date', ['date' => $r['left']]) }}
                            @elseif ($r['joined'])
                                {{ __('Since :date', ['date' => $r['joined']]) }}
                            @endif
                        </p>
                        @if ($r['status'] === 'active' && $r['organization_status'] !== 'active')
                            <p class="mt-1 text-[13px] font-semibold text-[#6B4C0C]">{{ __('This organization is not active right now, so clients can not book it.') }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $pill[$r['status']][0] ?? 'bg-brand-ink/10 text-brand-ink/65' }}">{{ $pill[$r['status']][1] ?? ucfirst($r['status']) }}</span>

                        @if ($r['can_leave'])
                            <form method="POST" action="{{ route('counselor.organizations.leave', $r['id']) }}"
                                  data-confirm-title="{{ __('Leave :name?', ['name' => $r['name']]) }}"
                                  data-confirm="{{ $onlyOne ? __('This is the only place you work, so clients will not find you until you join another. Later sessions with it are cancelled and the clients are told. It can not be undone.') : __('Later sessions with it are cancelled and the clients are told. The times you opened for it are removed. Your other work is not affected. It can not be undone.') }}"
                                  data-confirm-button="{{ __('Leave organization') }}" data-confirm-cancel="{{ __('Stay') }}"
                                  data-confirm-input="reason" data-confirm-input-label="{{ __('Reason (kept on your record, not sent to clients)') }}" data-confirm-input-placeholder="{{ __('Why are you leaving?') }}">
                                @csrf
                                <button class="inline-flex min-h-[44px] items-center rounded-md border border-brand-red/50 px-4 text-[14px] font-semibold text-brand-red transition hover:bg-brand-red/[.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">{{ __('Leave') }}</button>
                            </form>
                        @elseif ($r['independent'] && $r['status'] === 'active')
                            <span class="text-[12.5px] text-brand-ink/55">{{ __('Managed by MyKaunsel') }}</span>
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-[#E4E2DE] bg-white p-6 text-brand-ink/65">{{ __('You do not work for any organization yet.') }}</li>
        @endforelse
    </ul>
</x-portal-layout>
