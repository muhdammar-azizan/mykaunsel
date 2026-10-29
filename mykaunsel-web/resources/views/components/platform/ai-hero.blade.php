@props(['title', 'subtitle' => null, 'placeholder' => 'Ask anything', 'suggestions' => []])

{{-- The "Ask anything" box is switched off until there is something real behind it: it sends nothing anywhere and returns no answers.
     The quick-action pills are ordinary links ($suggestions: label, url, icon). --}}
<div>
    <h1 class="mt-4 text-center text-[28px] font-extrabold leading-tight tracking-tightest">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-center text-[13.5px] text-brand-ink/55">{{ $subtitle }}</p>
    @endif

    <div class="mx-auto mt-5 flex w-full max-w-[600px] flex-col items-center rounded-[28px] border border-white/60 bg-[rgba(245,243,238,.65)] px-6 py-6 text-center shadow-[0_20px_44px_-18px_rgba(14,42,51,.28),0_2px_8px_-2px_rgba(14,42,51,.08)] backdrop-blur-xl">

        <div role="group" aria-label="{{ $placeholder }} (coming soon)"
             class="mt-5 flex h-16 w-full max-w-[560px] items-center gap-3 rounded-full border-[1.5px] border-brand-ink/10 bg-white/70 pl-5 pr-2 shadow-[0_6px_20px_-8px_rgba(14,42,51,.12)]">
            <svg class="shrink-0 text-brand-blue/50" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
            <label for="ai-ask" class="sr-only">{{ $placeholder }}: coming soon</label>
            <input id="ai-ask" type="text" disabled aria-disabled="true" placeholder="{{ $placeholder }} — coming soon"
                   class="h-full min-w-0 flex-1 cursor-not-allowed border-0 bg-transparent p-0 text-[13px] placeholder:text-brand-ink/45 focus:outline-none focus:ring-0">
            <button type="button" disabled aria-disabled="true" aria-label="Attach (coming soon)" class="grid h-9 w-9 shrink-0 cursor-not-allowed place-items-center rounded-full text-brand-ink opacity-40">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="button" disabled aria-disabled="true" aria-label="Send (coming soon)" class="grid h-11 w-11 shrink-0 cursor-not-allowed place-items-center rounded-full bg-[#4A7DFF] opacity-40">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </div>

        <p class="mt-2 text-[11.5px] font-semibold text-brand-ink/55">Coming soon</p>

        <nav class="mt-4 flex max-w-[460px] flex-wrap justify-center gap-2.5" aria-label="Quick actions">
            @foreach ($suggestions as $item)
                <a href="{{ $item['url'] }}"
                   class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-brand-ink/15 bg-cream px-[18px] py-2.5 text-[12.5px] font-semibold shadow-[0_2px_6px_-2px_rgba(14,42,51,.12)] transition duration-150 hover:-translate-y-0.5 hover:bg-brand-blue/5 hover:shadow-[0_8px_16px_-6px_rgba(14,42,51,.2)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
