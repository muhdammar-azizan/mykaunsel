{{-- The confirmation dialog (resources/js/confirm-dialog.js): "Are you sure?" before something that can not be undone. Put once in a page layout. --}}
<div x-data="{
        open: false, title: '', message: '', confirmLabel: 'Confirm', cancelLabel: 'Cancel', tone: 'danger', resolve: null, opener: null,
        show(d) {
            this.title = d.title || 'Are you sure?'; this.message = d.message || ''; this.confirmLabel = d.confirmLabel || 'Confirm'; this.cancelLabel = d.cancelLabel || 'Cancel';
            this.tone = d.tone || 'danger'; this.resolve = d.resolve; this.opener = document.activeElement; this.open = true;
            this.$nextTick(() => this.$refs.cancel.focus());
        },
        close(answer) {
            if (!this.open) return;
            this.open = false;
            if (this.resolve) this.resolve(answer);
            this.resolve = null;
            if (this.opener && this.opener.focus) this.opener.focus();
        },
     }"
     @mk-confirm.window="show($event.detail)"
     @keydown.escape.window="close(false)">
    <div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="alertdialog" aria-modal="true" aria-labelledby="mk-confirm-title" :aria-describedby="message ? 'mk-confirm-message' : null">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-brand-ink/55" @click="close(false)"></div>

        <div x-show="open" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
             class="relative w-full max-w-[420px] rounded-2xl bg-white p-6 shadow-[0_24px_60px_-20px_rgba(14,42,51,.55)]" @keydown.tab="
                const f = [...$el.querySelectorAll('button')]; const first = f[0]; const last = f[f.length - 1];
                if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); } else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); }">
            <div class="flex items-start gap-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full" :class="tone === 'danger' ? 'bg-brand-red/10 text-brand-red' : 'bg-brand-blue/10 text-brand-blue'" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></svg>
                </span>
                <div class="min-w-0 pt-0.5">
                    <h2 id="mk-confirm-title" class="text-[18px] font-extrabold leading-snug tracking-tight text-brand-ink" x-text="title"></h2>
                    <p x-show="message" id="mk-confirm-message" class="mt-1.5 text-[14px] leading-relaxed text-brand-ink/65" x-text="message"></p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button type="button" x-ref="cancel" @click="close(false)" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue" x-text="cancelLabel"></button>
                <button type="button" @click="close(true)" class="inline-flex min-h-[42px] items-center rounded-full px-6 text-[13.5px] font-semibold text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="tone === 'danger' ? 'bg-brand-red hover:brightness-110 focus-visible:ring-brand-red' : 'bg-brand-blue hover:bg-brand-deep focus-visible:ring-brand-blue'" x-text="confirmLabel"></button>
            </div>
        </div>
    </div>
</div>
