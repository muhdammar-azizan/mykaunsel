{{-- Command palette. Opened by the header search button or Ctrl/Cmd+K (state: searchOpen on <body>). --}}
@php
    $items = collect(config('platform-nav'))
        ->flatMap(fn ($section) => collect($section['items'])->map(fn ($item) => [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'group' => $section['group'] ?? 'General',
            'url' => $item['route'] ? route($item['route']) : null,
        ]))
        ->values();
@endphp

<div x-data="{
        q: '',
        sel: 0,
        items: @js($items),
        get results() {
            const q = this.q.trim().toLowerCase();
            return q ? this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(q)) : this.items;
        },
        move(d) { this.sel = Math.max(0, Math.min(this.sel + d, this.results.length - 1)); },
        go() { const i = this.results[this.sel]; if (i && i.url) window.location = i.url; },
    }"
     x-effect="if (searchOpen) { q = ''; sel = 0; $nextTick(() => $refs.input.focus()); }"
     @keydown.window.escape="searchOpen = false"
     @keydown.window.ctrl.k.prevent="searchOpen = true"
     @keydown.window.meta.k.prevent="searchOpen = true"
     x-show="searchOpen" x-cloak
     class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]"
     role="dialog" aria-modal="true" aria-label="Search platform">

    <div x-show="searchOpen" x-transition.opacity.duration.200ms @click="searchOpen = false"
         class="absolute inset-0 bg-brand-deep/50 backdrop-blur-sm"></div>

    <div x-show="searchOpen"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="translate-y-2 scale-95 opacity-0"
         x-transition:enter-end="translate-y-0 scale-100 opacity-100"
         x-transition:leave="transition duration-150 ease-in"
         x-transition:leave-start="scale-100 opacity-100"
         x-transition:leave-end="scale-95 opacity-0"
         class="relative w-full max-w-[560px] overflow-hidden rounded-2xl bg-white shadow-[0_30px_60px_-20px_rgba(0,0,0,.45)]">

        <div class="flex items-center gap-3 border-b border-brand-ink/10 px-5 py-4">
            <svg class="shrink-0 text-brand-ink/50" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input x-ref="input" x-model="q" @input="sel = 0"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   type="text" placeholder="Search organizations, counselors, complaints..." aria-label="Search platform"
                   class="flex-1 border-0 bg-transparent p-0 text-[15px] text-brand-ink placeholder:text-brand-ink/40 focus:outline-none focus:ring-0">
            <kbd class="hidden rounded-md border border-brand-ink/15 px-1.5 py-0.5 text-[11px] font-medium text-brand-ink/50 sm:block">Esc</kbd>
        </div>

        <div class="scroll-subtle max-h-[50vh] overflow-y-auto p-2">
            <p class="px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wide text-brand-ink/40" x-text="q.trim() ? 'Results' : 'Quick access'"></p>

            <template x-for="(item, idx) in results" :key="item.label">
                <a :href="item.url || null" @mouseenter="sel = idx" @click="if (!item.url) $event.preventDefault()"
                   :class="[sel === idx ? 'bg-brand-ink/5' : '', item.url ? '' : 'cursor-not-allowed opacity-60']"
                   class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 transition-colors">
                    <svg class="shrink-0 text-brand-blue" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path :d="item.icon"/></svg>
                    <span class="flex-1 text-[13.5px] font-medium text-brand-ink" x-text="item.label"></span>
                    <span class="text-[11.5px] text-brand-ink/40" x-text="item.group"></span>
                    <span x-show="!item.url" class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-brand-ink/55">Soon</span>
                    <svg x-show="item.url" class="shrink-0 text-brand-ink/35" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </template>

            <p x-show="results.length === 0" class="px-3 py-8 text-center text-[13px] text-brand-ink/50">
                No results for "<span x-text="q"></span>"
            </p>
        </div>

        <div class="flex items-center gap-4 border-t border-brand-ink/10 bg-cream px-5 py-2.5 text-[11.5px] text-brand-ink/50">
            <span><kbd class="font-semibold">↑↓</kbd> navigate</span>
            <span><kbd class="font-semibold">↵</kbd> open</span>
            <span class="ml-auto"><kbd class="font-semibold">Ctrl K</kbd> to search anywhere</span>
        </div>
    </div>
</div>
