@props(['notes', 'action', 'placeholder' => 'Write a note for the other admins.'])

{{-- Internal notes between Platform Admins. Never shown to the applicant. $action: POST url taking {body}; errors come from the "note" bag. --}}
<x-platform.panel id="notes" title="Internal notes" class="scroll-mt-6">
    <p class="mt-0.5 text-[12.5px] text-brand-ink/55">Only Platform Admins can see these. The applicant never does.</p>

    <form method="POST" action="{{ $action }}" class="mt-3" x-data="{ body: @js(old('body', '')), busy: false }" @submit="busy = true">
        @csrf
        <label for="note-body" class="sr-only">New note</label>
        <textarea id="note-body" name="body" x-model="body" rows="3" maxlength="2000" required
                  placeholder="{{ $placeholder }}"
                  class="block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20"></textarea>
        @error('body', 'note')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
        <div class="mt-2 flex justify-end">
            <button type="submit" :disabled="busy || body.trim().length === 0"
                    class="inline-flex min-h-[38px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40">Add note</button>
        </div>
    </form>

    @if ($notes->isEmpty())
        <p class="mt-4 rounded-xl bg-brand-ink/[.04] px-4 py-5 text-center text-[13px] text-brand-ink/50">No notes yet.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($notes as $note)
                <li class="rounded-xl border border-brand-ink/10 bg-cream px-4 py-3">
                    @if ($note->body === '')
                        {{-- A complaint's notes are emptied by its retention rule: who wrote it and when stay, the words go. --}}
                        <p class="text-[13px] italic text-brand-ink/50">The words of this note were erased under the retention rule.</p>
                    @else
                        <p class="whitespace-pre-line break-words text-[13.5px] leading-relaxed">{{ $note->body }}</p>
                    @endif
                    <p class="mt-2 text-[11.5px] text-brand-ink/50" title="{{ $note->created_at->format('j M Y, g:i A') }}">
                        {{ $note->author?->name ?? 'Deleted user' }} · {{ $note->created_at->diffForHumans() }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</x-platform.panel>
