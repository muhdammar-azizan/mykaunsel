@props([
    'items',
    'open',
    'endpoint',
    'closedText' => 'Closed — this application has been decided.',
])

{{--
    Review checklist: a shared reference for all Platform Admins, never blocks a decision.
    $items: list of ['key', 'label', 'hint', 'checked', 'by', 'at'].
    $endpoint: PATCH url taking {key, checked}; it answers {by, at}. $open: false makes the boxes read-only.
--}}
<x-platform.panel {{ $attributes }}>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-[14px] font-bold">Review checklist</h2>
        <p class="text-[12.5px] text-brand-ink/55">
            {{ $open ? 'Saves automatically and is shared with other admins. For reference only — it does not block approval.' : $closedText }}
        </p>
    </div>

    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
        @foreach ($items as $item)
            <li x-data="{
                    on: @js($item['checked']),
                    by: @js($item['by']),
                    at: @js($item['at']),
                    state: 'idle',
                    timer: null,
                    async toggle() {
                        const next = !this.on;
                        const prev = { on: this.on, by: this.by, at: this.at };
                        this.on = next;
                        this.state = 'saving';
                        clearTimeout(this.timer);
                        try {
                            const res = await fetch(@js($endpoint), {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                body: JSON.stringify({ key: @js($item['key']), checked: next }),
                            });
                            if (!res.ok) throw new Error('save failed');
                            const data = await res.json();
                            this.by = data.by;
                            this.at = data.at;
                            this.state = 'saved';
                        } catch (e) {
                            Object.assign(this, prev);
                            this.state = 'error';
                        }
                        this.timer = setTimeout(() => this.state = 'idle', 2200);
                    },
                }">
                <label class="flex items-start gap-3 rounded-xl border px-4 py-3 transition {{ $open ? 'cursor-pointer hover:border-brand-blue/40' : 'cursor-default opacity-70' }}"
                       :class="on ? 'border-brand-blue/30 bg-brand-blue/[.04]' : 'border-brand-ink/10 bg-white'">
                    <input type="checkbox" class="mt-0.5 h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-blue focus:ring-brand-blue"
                           :checked="on" @change="toggle()" @disabled(! $open)>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[14px] font-semibold">{{ $item['label'] }}</span>
                        <span class="block text-[12px] text-brand-ink/50">{{ $item['hint'] }}</span>
                        <span x-show="on && by" x-cloak class="mt-1 block text-[11.5px] text-brand-blue">Checked by <span x-text="by"></span> · <span x-text="at"></span></span>
                        <span class="mt-1 block text-[11.5px]" aria-live="polite">
                            <span x-show="state === 'saving'" x-cloak class="text-brand-ink/45">Saving…</span>
                            <span x-show="state === 'error'" x-cloak class="font-semibold text-brand-red">Couldn't save — try again</span>
                        </span>
                    </span>
                </label>
            </li>
        @endforeach
    </ul>
</x-platform.panel>
