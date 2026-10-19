{{-- Success toast. Shows the `status` flash message for a few seconds. --}}
@if (session('status'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)"
         x-show="show" x-cloak role="status"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-3 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-6 right-6 z-[90] flex max-w-sm items-center gap-3 rounded-xl bg-brand-ink px-4 py-3 text-[13.5px] font-medium text-white shadow-2xl">
        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-500">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <span>{{ session('status') }}</span>
        <button type="button" @click="show = false" class="ml-2 text-white/60 hover:text-white" aria-label="Dismiss">✕</button>
    </div>
@endif
