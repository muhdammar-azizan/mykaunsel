{{-- The Platform Admin bell. On a normal page load only the unread COUNT is read (one indexed query, done here); the panel's contents are
     fetched when the bell is opened, and the count is refreshed by light polling (resources/js/notification-bell.js). --}}
@php
    $count = auth()->check() ? app(\App\Services\NotificationCenter::class)->unreadCount(auth()->user()) : 0;
    $badge = $count > 9 ? '9+' : (string) $count;
@endphp

<div class="relative"
     x-data="notificationBell(@js(['count' => $count, 'countUrl' => route('platform.notifications.count'), 'panelUrl' => route('platform.notifications.panel'), 'readAllUrl' => route('platform.notifications.read-all'), 'csrf' => csrf_token()]))"
     @keydown.escape.window="close(true)" @click.outside="close()">

    <button type="button" x-ref="bell" @click="toggle()" aria-haspopup="dialog" aria-controls="notification-panel" :aria-expanded="open.toString()" aria-expanded="false"
            aria-label="{{ $count > 0 ? "Notifications, {$badge} unread" : 'Notifications' }}" :aria-label="label()"
            class="group relative grid h-10 w-10 place-items-center rounded-full border border-brand-ink/10 bg-cream text-brand-ink shadow-[0_2px_6px_-2px_rgba(14,42,51,.25)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_16px_-6px_rgba(14,42,51,.35)] focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 4-2 5-2 7h16c0-2-2-3-2-7z"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0"/></svg>
        {{-- The number itself is the badge (capped at 9+), so it is never colour alone. Hidden at zero. --}}
        <span data-bell-badge aria-hidden="true" x-text="badge()" :class="{ 'hidden': count < 1 }"
              class="{{ $count > 0 ? '' : 'hidden' }} absolute -right-1 -top-1 grid h-[19px] min-w-[19px] place-items-center rounded-full border-2 border-cream bg-brand-red px-1 text-[10.5px] font-bold leading-none text-white">{{ $count > 0 ? $badge : '' }}</span>
        <span role="tooltip" x-show="! open" class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-brand-ink px-2.5 py-1 text-[12px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100">Notifications</span>
    </button>

    <div id="notification-panel" x-ref="panel" x-show="open" x-cloak role="dialog" aria-modal="false" aria-labelledby="notification-panel-title" tabindex="-1"
         @keydown.tab="trap($event)"
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="scale-100 opacity-100" x-transition:leave-end="scale-95 opacity-0"
         class="absolute right-0 top-[52px] z-50 w-[380px] max-w-[calc(100vw-1.5rem)] origin-top-right overflow-hidden rounded-2xl bg-white shadow-[0_20px_44px_-14px_rgba(14,42,51,.45)] focus:outline-none">

        <div class="flex items-center justify-between gap-3 border-b border-brand-ink/10 px-5 py-3">
            <h2 id="notification-panel-title" class="text-[15px] font-bold">Notifications</h2>
            <button type="button" @click="markAll()" x-show="count > 0" class="rounded text-[12.5px] font-semibold text-brand-blue hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">Mark all as read</button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto" aria-live="polite">
            <p x-show="loading && ! html" class="px-5 py-10 text-center text-[13px] text-brand-ink/60">Loading…</p>
            <p x-show="failed" class="px-5 py-10 text-center text-[13px] font-semibold text-brand-red">Could not load your notifications. Close this and try again.</p>
            <div x-show="! failed" x-html="html"></div>
        </div>

        <a href="{{ route('platform.notifications') }}" class="block border-t border-brand-ink/10 px-5 py-3 text-center text-[13px] font-semibold text-brand-blue transition hover:bg-brand-ink/5 focus:outline-none focus-visible:bg-brand-ink/5 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue">View all notifications</a>
    </div>
</div>
