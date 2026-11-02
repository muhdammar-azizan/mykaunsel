@props(['title', 'subtitle' => null, 'placeholder' => 'Ask anything', 'suggestions' => []])

{{-- The "Ask anything" box. The question goes to the server (platform.assistant), which asks the model with the dashboard's counts only and answers with a
     short text and up to three links. Each pill types its prompt into the box. ($suggestions: label, prompt, icon.) --}}
<div x-data="{
        q: '',
        timer: null,
        i: 0,
        prompts: @js(collect($suggestions)->pluck('prompt')->map(fn ($p) => \Illuminate\Support\Str::ucfirst($p))->all() ?: [$placeholder]),
        shown: '',
        erasing: false,
        busy: false,
        answer: '',
        links: [],
        failed: false,
        init() {
            // Typewriter: type a prompt, pause, erase it, move to the next one.
            const tick = () => {
                const full = this.prompts[this.i];
                if (this.q) return setTimeout(tick, 400);
                if (!this.erasing && this.shown.length < full.length) {
                    this.shown = full.slice(0, this.shown.length + 1);
                    return setTimeout(tick, 55);
                }
                if (!this.erasing) { this.erasing = true; return setTimeout(tick, 1800); }
                if (this.shown.length > 0) {
                    this.shown = this.shown.slice(0, -1);
                    return setTimeout(tick, 25);
                }
                this.erasing = false;
                this.i = (this.i + 1) % this.prompts.length;
                setTimeout(tick, 350);
            };
            tick();
        },
        type(text) {
            clearTimeout(this.timer);
            this.q = '';
            this.$refs.input.focus();
            let i = 0;
            const next = () => {
                this.q = text.slice(0, i++);
                if (i <= text.length) this.timer = setTimeout(next, 22);
            };
            next();
        },
        async send() {
            const question = this.q.trim();
            if (!question || this.busy) return;
            this.busy = true; this.failed = false; this.answer = ''; this.links = [];
            try {
                const res = await fetch(@js(route('platform.assistant')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ question }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) { this.failed = true; this.answer = body.message || 'The assistant can not answer right now. Please try again in a moment.'; }
                else { this.answer = body.answer; this.links = body.links || []; }
            } catch (e) {
                this.failed = true; this.answer = 'The assistant can not answer right now. Please try again in a moment.';
            } finally {
                this.busy = false;
            }
        },
    }">
    <h1 class="mt-4 text-center text-[28px] font-extrabold leading-tight tracking-tightest">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-center text-[13.5px] text-brand-ink/55">{{ $subtitle }}</p>
    @endif

    <div class="mx-auto mt-5 flex w-full max-w-[600px] flex-col items-center rounded-[28px] border border-white/60 bg-[rgba(245,243,238,.65)] px-6 py-6 text-center shadow-[0_20px_44px_-18px_rgba(14,42,51,.28),0_2px_8px_-2px_rgba(14,42,51,.08)] backdrop-blur-xl">

        <form @submit.prevent="send()"
              class="group mt-5 flex h-16 w-full max-w-[560px] items-center gap-3 rounded-full border-[1.5px] border-brand-ink/10 bg-white pl-5 pr-2 shadow-[0_6px_20px_-8px_rgba(14,42,51,.12)] transition-all duration-300 hover:border-[#4A7DFF] hover:shadow-[0_0_40px_2px_rgba(74,125,255,.35),0_14px_30px_-10px_rgba(74,125,255,.3)] focus-within:border-[#4A7DFF] focus-within:shadow-[0_0_40px_2px_rgba(74,125,255,.3),0_14px_30px_-10px_rgba(74,125,255,.3)]">
            <svg class="shrink-0 text-brand-blue" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
            <label for="ai-ask" class="sr-only">Ask anything about the platform</label>
            {{-- Typewriter placeholder: shown while the box is empty, hidden once the admin types. --}}
            <div class="relative h-full flex-1">
                <input id="ai-ask" x-ref="input" x-model="q" type="text" maxlength="500" autocomplete="off" aria-label="{{ $placeholder }}"
                       class="h-full w-full border-0 bg-transparent p-0 text-[13px] focus:outline-none focus:ring-0">
                <span x-show="!q" aria-hidden="true" class="pointer-events-none absolute inset-0 flex items-center text-left text-[13px] text-brand-ink/45">
                    <span x-text="shown"></span><span class="ml-px inline-block h-[15px] w-px animate-pulse bg-brand-ink/50"></span>
                </span>
            </div>
            <button type="button" aria-label="Clear" @click="q = ''; answer = ''; links = []; $refs.input.focus()" x-show="q || answer" x-cloak class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-brand-ink transition hover:bg-brand-ink/5">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <button type="submit" aria-label="Send" :disabled="busy || !q.trim()" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#4A7DFF] transition hover:scale-105 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100">
                <svg x-show="!busy" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                <svg x-show="busy" x-cloak class="animate-spin" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/></svg>
            </button>
        </form>

        <div x-show="answer" x-cloak x-transition.opacity role="status" aria-live="polite" class="mt-4 w-full max-w-[560px] rounded-2xl bg-white/80 px-5 py-4 text-left shadow-[0_6px_20px_-8px_rgba(14,42,51,.12)]">
            <p class="text-[13.5px] leading-relaxed" :class="failed ? 'text-brand-red' : 'text-brand-ink'" x-text="answer"></p>
            <div x-show="links.length" class="mt-3 flex flex-wrap gap-2">
                <template x-for="link in links" :key="link.url">
                    <a :href="link.url" class="inline-flex items-center gap-1.5 rounded-full bg-brand-blue/10 px-3.5 py-1.5 text-[12.5px] font-semibold text-brand-blue transition hover:bg-brand-blue/15">
                        <span x-text="link.label"></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </template>
            </div>
        </div>

        <p class="mt-2 flex items-center gap-1.5 text-[11.5px] text-brand-ink/45">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg>
            Powered by AI. It reads platform totals only, never personal details, and can not change anything.
        </p>

        <div class="mt-4 flex max-w-[460px] flex-wrap justify-center gap-2.5" role="group" aria-label="Quick actions">
            @foreach ($suggestions as $item)
                <button type="button" @click="type(@js($item['prompt']))"
                        class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-brand-ink/15 bg-cream px-[18px] py-2.5 text-[12.5px] font-semibold shadow-[0_2px_6px_-2px_rgba(14,42,51,.12)] transition duration-150 hover:-translate-y-0.5 hover:bg-brand-blue/5 hover:shadow-[0_8px_16px_-6px_rgba(14,42,51,.2)]">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</div>
