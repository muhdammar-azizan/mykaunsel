@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white';
    $pill = ['confirmed' => 'bg-brand-blue/10 text-brand-blue', 'completed' => 'bg-emerald-50 text-emerald-700', 'no_show' => 'bg-brand-yellow/25 text-[#6B4C0C]'];
    $label = ['confirmed' => 'Not marked yet', 'completed' => 'Completed', 'no_show' => 'No-show'];
@endphp

<x-portal-layout title="Session notes">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Session notes</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">Your private notes about your sessions. Only you can read them: not the organization, not the MyKaunsel team, and not the client. They are stored encrypted and erased {{ config('session-notes.retention_years') }} years after the session.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="{{ $card }} mt-5 overflow-hidden">
        @forelse ($sessions as $s)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#EEEDEA] px-6 py-4 first:border-t-0">
                <div class="min-w-0">
                    <p class="text-[15px] font-semibold">{{ $s['client'] }}</p>
                    <p class="text-[13px] text-brand-ink/65">{{ $s['date'] }}, {{ $s['time'] }} · {{ $s['mode'] === 'online' ? 'Online' : 'In person' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $pill[$s['status']] ?? 'bg-brand-ink/10 text-brand-ink/65' }}">{{ $label[$s['status']] ?? $s['status'] }}</span>
                    <a href="{{ route('counselor.notes.show', $s['id']) }}" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">{{ $s['has_note'] ? 'Open note' : 'Write a note' }}</a>
                </div>
            </div>
        @empty
            <x-empty-state-notes title="No sessions yet." text="A note can be written once a session has started. Only you can read your notes." />
        @endforelse
    </div>

    @if ($sessions->hasPages())
        <div class="mt-5">{{ $sessions->links() }}</div>
    @endif
</x-portal-layout>
