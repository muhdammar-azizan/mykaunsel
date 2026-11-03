@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $primary = 'inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2';
    $outline = 'inline-flex min-h-[44px] items-center rounded-md border border-brand-ink/70 bg-white px-5 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $pill = ['confirmed' => 'bg-brand-blue/10 text-brand-blue', 'completed' => 'bg-emerald-50 text-emerald-700', 'no_show' => 'bg-brand-yellow/25 text-[#6B4C0C]', 'cancelled' => 'bg-brand-ink/10 text-brand-ink/65'][$s['status']] ?? 'bg-brand-ink/10 text-brand-ink/65';
    $label = ['confirmed' => 'Confirmed', 'completed' => 'Completed', 'no_show' => 'No-show', 'cancelled' => 'Cancelled'][$s['status']] ?? $s['status'];
@endphp

<x-portal-layout title="Booking">
    <p><a href="{{ route('counselor.appointments') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">&larr; All bookings</a></p>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ $s['client'] }}</h1>
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $pill }}">{{ $label }}</span>
    </div>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section class="{{ $card }} p-6" aria-labelledby="details-title">
            <h2 id="details-title" class="text-[20px] font-bold tracking-tight">Session</h2>
            <dl class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Date</dt><dd class="font-semibold">{{ $s['date'] }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Time (Malaysia time)</dt><dd class="font-semibold">{{ $s['time'] }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Length</dt><dd class="font-semibold">{{ $s['minutes'] }} min</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Where</dt><dd class="text-right font-semibold">{{ $s['mode'] === 'online' ? 'Online' : 'In person'.($s['place'] ? ' · '.$s['place'] : '') }}</dd></div>
                @if ($s['status'] === 'cancelled')
                    <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Cancelled by</dt><dd class="font-semibold">{{ $s['cancelled_by'] === 'counselor' ? 'You' : ($s['cancelled_by'] === 'client' ? 'The client' : 'The client, or the system') }}</dd></div>
                    @if ($s['cancel_reason'])
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Reason you gave</dt><dd class="text-right font-semibold">{{ $s['cancel_reason'] }}</dd></div>
                    @endif
                @endif
            </dl>

            @if ($s['can_set_meeting'])
                <form method="POST" action="{{ route('counselor.appointments.meeting', $s['id']) }}" class="mt-5 grid items-end gap-3 border-t border-[#EEEDEA] pt-5 sm:grid-cols-[10rem_1fr_auto]">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="provider" class="block text-[13px] font-semibold text-brand-ink/70">Meeting app</label>
                        <select id="provider" name="meeting_provider" class="{{ $input }}">
                            @foreach ($providers as $p)<option value="{{ $p }}" @selected(($s['meeting_provider'] ?? 'Google Meet') === $p)>{{ $p }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="url" class="block text-[13px] font-semibold text-brand-ink/70">Meeting link (https)</label>
                        <input id="url" name="meeting_url" type="url" maxlength="255" value="{{ $s['meeting_url'] }}" placeholder="https://" class="{{ $input }}">
                    </div>
                    <button class="{{ $primary }}">Save link</button>
                    <p class="text-[12.5px] text-brand-ink/55 sm:col-span-3">The client sees this link 15 minutes before the session. Leave it empty and save to remove it.</p>
                </form>
            @endif
        </section>

        <div class="space-y-5">
            @if ($s['can_note'])
                <section class="{{ $card }} p-6" aria-labelledby="note-link-title">
                    <h2 id="note-link-title" class="text-[20px] font-bold tracking-tight">Session note</h2>
                    <p class="mt-1 text-[14px] text-brand-ink/65">Your private note about this session. Only you can read it.</p>
                    <a href="{{ route('counselor.notes.show', $s['id']) }}" class="{{ $outline }} mt-4">Open the note</a>
                </section>
            @endif

            @if ($s['can_mark'])
                <section class="{{ $card }} p-6" aria-labelledby="mark-title">
                    <h2 id="mark-title" class="text-[20px] font-bold tracking-tight">What happened?</h2>
                    <p class="mt-1 text-[14px] text-brand-ink/65">The session has ended. Say what happened.</p>
                    <form method="POST" action="{{ route('counselor.appointments.outcome', $s['id']) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        <button name="outcome" value="completed" class="{{ $primary }}">Mark completed</button>
                        <button name="outcome" value="no_show" class="{{ $outline }}">Mark no-show</button>
                    </form>
                </section>
            @endif

            @if ($s['can_cancel'])
                <section class="{{ $card }} p-6" aria-labelledby="cancel-title">
                    <h2 id="cancel-title" class="text-[20px] font-bold tracking-tight">Cancel this session</h2>
                    <p class="mt-1 text-[14px] text-brand-ink/65">The client is told by e-mail and in the app, with your reason. The time opens for booking again. You can not undo this.</p>
                    <form method="POST" action="{{ route('counselor.appointments.cancel', $s['id']) }}" class="mt-4" data-confirm-title="Cancel this session?" data-confirm="The client will be told." data-confirm-button="Cancel session" data-confirm-cancel="Keep session">
                        @csrf
                        <label for="reason" class="block text-[13px] font-semibold text-brand-ink/70">Reason for the client</label>
                        <textarea id="reason" name="reason" rows="3" maxlength="300" required class="mt-1 block w-full rounded-md border border-brand-ink/20 px-3 py-2 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20">{{ old('reason') }}</textarea>
                        <p class="mt-1 text-[12.5px] text-brand-ink/55">Keep it short and kind. Do not write private details about yourself or the client.</p>
                        <button type="submit" class="mt-3 inline-flex min-h-[44px] items-center rounded-md border border-brand-red/50 px-5 text-[14px] font-semibold text-brand-red transition hover:bg-brand-red/[.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">Cancel session</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-portal-layout>
