@props([
    'name',
    'action',
    'title',
    'textareaId',
    'submitLabel',
    'busyLabel',
    'placeholder',
    'label' => 'Reason',
    'rows' => 4,
    'bag' => null,
    'templates' => [],
    'tone' => 'danger', // danger (red button) | primary (blue)
])

{{--
    Modal that needs a written reason (reject, suspend…). Quick-reason chips fill the box; the button stays off until the
    reason is at least 10 characters (the server checks again). The slot is the text shown under the title.
    Re-opens by itself when the server sent back an error for "reason" (in the $bag error bag, if given).
--}}
<x-modal :name="$name" maxWidth="lg" :show="$errors->getBag($bag ?? 'default')->has('reason')" focusable>
    <form method="POST" action="{{ $action }}" x-data="{ busy: false, reason: @js(old('reason', '')) }" @submit="busy = true" class="p-6">
        @csrf
        <h2 class="text-[18px] font-extrabold tracking-tight">{{ $title }}</h2>
        {{ $slot }}

        @if ($templates)
            <div class="mt-4 flex flex-wrap gap-2" aria-label="Quick reasons">
                @foreach ($templates as $templateLabel => $text)
                    <button type="button" @click="reason = @js($text)"
                            class="rounded-full border border-brand-ink/15 px-3 py-1 text-[12px] font-semibold text-brand-ink/70 transition hover:border-brand-blue hover:text-brand-blue">{{ $templateLabel }}</button>
                @endforeach
            </div>
        @endif

        <label for="{{ $textareaId }}" class="mt-4 block text-[12.5px] font-semibold text-brand-ink/70">{{ $label }}</label>
        <textarea id="{{ $textareaId }}" name="reason" x-model="reason" rows="{{ $rows }}" maxlength="1000" required
                  class="mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20"
                  placeholder="{{ $placeholder }}"></textarea>
        @error('reason', $bag ?? 'default')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
        <p class="mt-1 text-right text-[11.5px] text-brand-ink/45"><span x-text="reason.length">0</span>/1000</p>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="$dispatch('close-modal', '{{ $name }}')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
            <button type="submit" :disabled="busy || reason.trim().length < 10" class="inline-flex min-h-[42px] items-center rounded-full {{ $tone === 'primary' ? 'bg-brand-blue hover:bg-brand-deep' : 'bg-brand-red hover:opacity-90' }} px-6 text-[13.5px] font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-40">
                <span x-show="!busy">{{ $submitLabel }}</span><span x-show="busy" x-cloak>{{ $busyLabel }}</span>
            </button>
        </div>
    </form>
</x-modal>
