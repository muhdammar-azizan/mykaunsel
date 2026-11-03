<x-portal-layout title="Session note">
    <p><a href="{{ route('counselor.notes') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">&larr; All session notes</a></p>

    <h1 class="mt-3 text-[24px] font-bold leading-tight tracking-tight">Note: {{ $s['client'] }}</h1>
    <p class="mt-1 text-[14px] text-brand-ink/65">{{ $s['date'] }}, {{ $s['time'] }} (Malaysia time) · {{ $s['mode'] === 'online' ? 'Online' : 'In person' }}</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <section class="mt-5 max-w-3xl rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="note-title">
        <h2 id="note-title" class="text-[20px] font-bold tracking-tight">Your private note</h2>
        <p class="mt-1 text-[13.5px] text-brand-ink/65">Only you can read this. It is stored encrypted, is never shown to the client, the organization or the MyKaunsel team, and is erased {{ config('session-notes.retention_years') }} years after the session. Write only what you need for your work.</p>

        <form method="POST" action="{{ route('counselor.notes.save', $s['id']) }}" class="mt-4" x-data="{ n: {{ \Illuminate\Support\Js::from(mb_strlen(old('body', $s['body']))) }} }">
            @csrf
            @method('PUT')
            <label for="body" class="sr-only">Note</label>
            <textarea id="body" name="body" rows="12" maxlength="{{ $max }}" required autocomplete="off" @input="n = $event.target.value.length"
                      class="block w-full rounded-md border border-brand-ink/20 px-3 py-2 text-[14.5px] leading-relaxed focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20">{{ old('body', $s['body']) }}</textarea>
            @error('body')<p class="mt-1.5 text-[13px] font-semibold text-brand-red" role="alert">{{ $message }}</p>@enderror
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-[12.5px] text-brand-ink/55"><span x-text="n">0</span> / {{ number_format($max) }} characters {{ $s['saved_at'] ? '· saved '.$s['saved_at'] : '' }}</p>
                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">Save note</button>
            </div>
        </form>

        @if ($s['has_note'])
            <form method="POST" action="{{ route('counselor.notes.destroy', $s['id']) }}" class="mt-5 border-t border-[#EEEDEA] pt-5" data-confirm-title="Delete this note?" data-confirm="It will be deleted for good. It can not be undone." data-confirm-button="Delete note" data-confirm-cancel="Keep note">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md border border-brand-red/50 px-5 text-[14px] font-semibold text-brand-red transition hover:bg-brand-red/[.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-red">Delete note</button>
            </form>
        @endif
    </section>
</x-portal-layout>
