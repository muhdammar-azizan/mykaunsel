// The Platform Admin notification bell: the unread count, the panel, and light polling.
//
// - On a normal page load the count is already in the HTML. The panel's contents are fetched only when the bell is opened.
// - The count is refreshed every 60 seconds, only while the tab is visible. Errors back off (60s, 2min, 4min … at most 15min); 401, 403 and
//   419 (signed out, not allowed, session expired) stop the polling for good. No websockets.
// - Everything the panel shows is HTML built and escaped on the server; nothing from the server is ever put into the page as script.

const STOP_STATUSES = [401, 403, 419];

const jsonHeaders = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/**
 * Polls one URL for {count}. All the outside world is passed in, so the logic can be tested without a browser.
 */
export function createPoller({
    url,
    onCount,
    onStop = () => {},
    intervalMs = 60000,
    maxDelayMs = 900000,
    fetchFn = (...args) => fetch(...args),
    setTimer = (fn, ms) => setTimeout(fn, ms),
    clearTimer = (id) => clearTimeout(id),
    isVisible = () => document.visibilityState === 'visible',
}) {
    let failures = 0;
    let timer = null;
    let stopped = false;
    let inFlight = false;

    const delay = () => Math.min(intervalMs * 2 ** failures, maxDelayMs);

    const stop = () => {
        if (stopped) return;
        stopped = true;
        clearTimer(timer);
        timer = null;
        onStop();
    };

    const schedule = () => {
        if (stopped) return;
        clearTimer(timer);
        timer = setTimer(tick, delay());
    };

    async function poll() {
        if (stopped || inFlight) return;
        inFlight = true;

        try {
            const response = await fetchFn(url, { headers: jsonHeaders, credentials: 'same-origin', cache: 'no-store' });

            // A redirect (to the sign-in page) means the session is gone, just like 401.
            if (STOP_STATUSES.includes(response.status) || response.redirected) {
                stop();
                return;
            }

            if (!response.ok) {
                failures += 1;
                return;
            }

            const data = await response.json();

            if (data && Number.isInteger(data.count) && data.count >= 0) {
                onCount(data.count);
            }

            failures = 0;
        } catch (error) {
            failures += 1;
        } finally {
            inFlight = false;
        }
    }

    async function tick() {
        timer = null;
        if (stopped) return;

        // A hidden tab does not ask the server anything; it just checks again later.
        if (isVisible()) {
            await poll();
        }

        schedule();
    }

    return {
        start: schedule,
        stop,
        poll,
        /** The tab came back into view: ask now instead of waiting for the next tick. */
        async wake() {
            if (stopped || !isVisible()) return;
            await poll();
            schedule();
        },
        get stopped() {
            return stopped;
        },
        get failures() {
            return failures;
        },
        get nextDelay() {
            return delay();
        },
    };
}

/**
 * The Alpine component of the bell (Alpine.data('notificationBell', notificationBell)).
 *
 * @param {{count: number, countUrl: string, panelUrl: string, readAllUrl: string, csrf: string}} config
 */
export function notificationBell(config) {
    return {
        count: config.count,
        open: false,
        loading: false,
        failed: false,
        stopped: false,
        html: '',
        poller: null,

        init() {
            this.poller = createPoller({
                url: config.countUrl,
                onCount: (count) => {
                    this.count = count;
                },
                onStop: () => {
                    // Signed out or not allowed any more: the polling has stopped for good.
                    this.stopped = true;
                },
            });
            this.poller.start();

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.poller.wake();
            });
        },

        label() {
            if (this.count < 1) return 'Notifications';
            return `Notifications, ${this.count > 9 ? '9+' : this.count} unread`;
        },

        badge() {
            return this.count > 9 ? '9+' : String(this.count);
        },

        async toggle() {
            if (this.open) {
                this.close(true);
                return;
            }

            this.open = true;
            await this.load();
            this.$nextTick(() => this.$refs.panel && this.$refs.panel.focus());
        },

        close(returnFocus = false) {
            if (!this.open) return;
            this.open = false;
            if (returnFocus) this.$nextTick(() => this.$refs.bell && this.$refs.bell.focus());
        },

        /** Loads the panel's contents. Only called when the bell is opened. */
        async load() {
            this.loading = true;
            this.failed = false;

            try {
                const response = await fetch(config.panelUrl, { headers: { ...jsonHeaders, Accept: 'text/html' }, credentials: 'same-origin', cache: 'no-store' });

                if (STOP_STATUSES.includes(response.status) || response.redirected) {
                    this.poller.stop();
                    this.failed = true;
                    return;
                }

                if (!response.ok) throw new Error('panel');

                this.html = await response.text();

                const unread = Number.parseInt(response.headers.get('X-Unread-Count') ?? '', 10);
                if (Number.isInteger(unread)) this.count = unread;
            } catch (error) {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },

        async markAll() {
            try {
                const response = await fetch(config.readAllUrl, {
                    method: 'POST',
                    headers: { ...jsonHeaders, 'X-CSRF-TOKEN': config.csrf },
                    credentials: 'same-origin',
                });

                if (STOP_STATUSES.includes(response.status) || response.redirected) {
                    this.poller.stop();
                    this.failed = true;
                    return;
                }

                if (!response.ok) throw new Error('read-all');

                this.count = 0;
                await this.load();
            } catch (error) {
                this.failed = true;
            }
        },

        /** Keeps Tab and Shift+Tab inside the open panel. */
        trap(event) {
            const focusable = [...this.$refs.panel.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])')]
                .filter((el) => el.offsetParent !== null);

            if (focusable.length === 0) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && (active === first || active === this.$refs.panel)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}
