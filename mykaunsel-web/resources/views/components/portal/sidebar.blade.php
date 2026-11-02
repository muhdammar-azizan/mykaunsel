@props(['items' => []])

{{-- Large screens: a full height column with the logo at the top. Small screens: a drawer opened by the menu button. --}}
<aside class="fixed inset-y-0 left-0 z-50 flex w-[240px] -translate-x-full flex-col border-r border-[#EAEAEA] bg-[#FAFAFA] pb-6 shadow-2xl transition-transform lg:static lg:z-30 lg:translate-x-0 lg:shadow-none"
       :class="menuOpen ? 'translate-x-0' : ''">
    <a href="{{ route('dashboard') }}" aria-label="MyKaunsel" class="flex h-[64px] shrink-0 items-center border-b border-[#EAEAEA] px-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue">
        <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="" class="h-8 w-auto">
    </a>
    {{-- Find a page: a button here, a small panel over the page when opened (click it or press F). Enter goes to the first match. --}}
    <div class="relative shrink-0 px-2 pt-3" x-data="{ q: '', open: false, pages: @js(collect($items)->whereNotNull('url')->map(fn ($i) => ['label' => $i['label'], 'url' => $i['url']])->values()->all()),
            get found() { const q = this.q.trim().toLowerCase(); return q === '' ? this.pages : this.pages.filter(p => p.label.toLowerCase().includes(q)); },
            show() { this.open = true; this.$nextTick(() => this.$refs.find.focus()); },
            hide() { this.open = false; this.q = ''; } }"
         @keydown.window="if (event.key.toLowerCase() === 'f' && !event.ctrlKey && !event.metaKey && !event.altKey && !open && !event.target.closest('input, textarea, select, [contenteditable]')) { event.preventDefault(); show(); }"
         @click.outside="hide()">
        <button type="button" @click="show()" class="flex h-[40px] w-full items-center gap-2.5 rounded-lg border border-brand-ink/15 bg-white px-3 text-left text-[14px] text-brand-ink/55 transition hover:border-brand-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
            <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <span class="flex-1">Find</span>
            <kbd class="rounded-md border border-brand-ink/15 bg-brand-ink/[.04] px-1.5 text-[12px] font-medium text-brand-ink/60">F</kbd>
        </button>

        <div x-show="open" x-cloak @keydown.escape.stop="hide()" class="absolute left-2 top-3 z-50 w-[340px] max-w-[calc(100vw-1rem)] overflow-hidden rounded-2xl border border-brand-ink/10 bg-white text-brand-ink shadow-[0_16px_36px_-12px_rgba(14,42,51,.45)]">
            <div class="flex items-center gap-2.5 border-b border-brand-ink/10 px-3.5 py-2.5">
                <svg class="shrink-0 text-brand-ink/60" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <label for="portal-find" class="sr-only">Find a page</label>
                <input id="portal-find" x-ref="find" type="text" x-model="q" @keydown.enter.prevent="if (found.length) window.location = found[0].url" autocomplete="off" placeholder="Find a page"
                       class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[14px] placeholder:text-brand-ink/45 focus:outline-none focus:ring-0">
                <kbd class="rounded-md border border-brand-ink/15 bg-brand-ink/[.04] px-1.5 text-[11.5px] font-medium text-brand-ink/60">Esc</kbd>
            </div>
            <div class="max-h-[320px] overflow-y-auto p-1.5">
                <template x-for="p in found" :key="p.url"><a :href="p.url" class="block rounded-lg px-3 py-2 text-[14px] hover:bg-brand-ink/5" x-text="p.label"></a></template>
                <p x-show="found.length === 0" class="px-3 py-3 text-[13.5px] text-brand-ink/60">No page found.</p>
            </div>
        </div>
    </div>

    <nav class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto scroll-subtle px-2 pt-3" aria-label="Portal">
        @foreach ($items as $item)
            @php
                $icon = '<svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.e($item['icon']).'"/></svg>';
            @endphp
            @if ($item['url'])
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                   class="flex min-h-[36px] items-center gap-3 rounded-md px-3 text-[14px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue {{ $item['active'] ? 'bg-brand-ink/[.07] font-semibold text-brand-ink' : 'text-brand-ink/75 hover:bg-brand-ink/[.05] hover:text-brand-ink' }}">
                    {!! $icon !!}<span class="flex-1">{{ $item['label'] }}</span>
                </a>
            @else
                <div class="flex min-h-[36px] cursor-not-allowed items-center gap-3 rounded-md px-3 text-[14px] text-brand-ink/45" aria-disabled="true" title="Coming soon">
                    {!! $icon !!}<span class="flex-1">{{ $item['label'] }}</span>
                    <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-brand-ink/55">Soon</span>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
<div x-show="menuOpen" x-cloak class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="menuOpen = false"></div>
