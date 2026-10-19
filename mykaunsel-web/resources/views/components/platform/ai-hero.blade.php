@props(['title', 'subtitle' => null, 'placeholder' => 'Ask anything', 'suggestions' => []])

{{-- Design only for now: the send button does nothing until the AI API is wired in (see @submit). --}}
<div x-data="{
        q: '',
        timer: null,
        i: 0,
        prompts: @js(collect($suggestions)->pluck('prompt')->map(fn ($p) => \Illuminate\Support\Str::ucfirst($p))->all() ?: [$placeholder]),
        init() {
            setInterval(() => { if (!this.q) this.i = (this.i + 1) % this.prompts.length; }, 3200);
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
    }">
    <h1 class="mt-4 text-center text-[28px] font-extrabold leading-tight tracking-tightest">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-center text-[13.5px] text-brand-ink/55">{{ $subtitle }}</p>
    @endif

    <div class="mx-auto mt-5 flex w-full max-w-[600px] flex-col items-center rounded-[28px] border border-white/60 bg-[rgba(245,243,238,.65)] px-6 py-6 text-center shadow-[0_20px_44px_-18px_rgba(14,42,51,.28),0_2px_8px_-2px_rgba(14,42,51,.08)] backdrop-blur-xl">

        <form @submit.prevent
              class="group mt-5 flex h-16 w-full max-w-[560px] items-center gap-3 rounded-full border-[1.5px] border-brand-ink/10 bg-white pl-5 pr-2 shadow-[0_6px_20px_-8px_rgba(14,42,51,.12)] transition-all duration-300 hover:border-[#4A7DFF] hover:shadow-[0_0_40px_2px_rgba(74,125,255,.35),0_14px_30px_-10px_rgba(74,125,255,.3)] focus-within:border-[#4A7DFF] focus-within:shadow-[0_0_40px_2px_rgba(74,125,255,.3),0_14px_30px_-10px_rgba(74,125,255,.3)]">
            <svg class="shrink-0 text-brand-blue" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
            <label for="ai-ask" class="sr-only">Ask anything about the platform</label>
            {{-- Rotating placeholder: each prompt slides up and fades in/out, hidden once the user types. --}}
            <div class="relative h-full flex-1">
                <input id="ai-ask" x-ref="input" x-model="q" type="text" aria-label="{{ $placeholder }}"
                       class="h-full w-full border-0 bg-transparent p-0 text-[13px] focus:outline-none focus:ring-0">
                <template x-for="(p, idx) in prompts" :key="idx">
                    <span x-show="i === idx && !q" aria-hidden="true"
                          x-transition:enter="transition duration-500 ease-out"
                          x-transition:enter-start="translate-y-3 opacity-0"
                          x-transition:enter-end="translate-y-0 opacity-100"
                          x-transition:leave="transition duration-300 ease-in"
                          x-transition:leave-start="translate-y-0 opacity-100"
                          x-transition:leave-end="-translate-y-3 opacity-0"
                          class="pointer-events-none absolute inset-0 flex items-center text-left text-[13px] text-brand-ink/45"
                          x-text="p"></span>
                </template>
            </div>
            <button type="button" aria-label="Attach" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-brand-ink transition hover:bg-brand-ink/5">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="submit" aria-label="Send" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#4A7DFF] transition hover:scale-105">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <p class="mt-2 flex items-center gap-1.5 text-[11.5px] text-brand-ink/45">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg>
            Powered by AI — ask in plain language to search or filter platform data
        </p>

        <div class="mt-4 flex max-w-[460px] flex-wrap justify-center gap-2.5" role="group" aria-label="Quick actions">
            @foreach ($suggestions as $item)
                <button type="button" @click="type(@js($item['prompt']))"
                        class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-brand-ink/15 bg-cream px-[18px] py-2.5 text-[12.5px] font-semibold shadow-[0_2px_6px_-2px_rgba(14,42,51,.12)] transition duration-150 hover:-translate-y-0.5 hover:bg-brand-blue/5 hover:shadow-[0_8px_16px_-6px_rgba(14,42,51,.2)]">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</div>
