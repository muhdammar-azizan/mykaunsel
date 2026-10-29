// Tests for the bell's polling (resources/js/notification-bell.js). Run with:  node --test tests/js
// No browser, no network: the clock, fetch and tab visibility are all passed in.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createPoller } from '../../resources/js/notification-bell.js';

/** A fake clock: timers fire only when we say so. */
function fakeClock() {
    let next = 1;
    const timers = new Map();

    return {
        setTimer(fn, ms) {
            const id = next++;
            timers.set(id, { fn, ms });
            return id;
        },
        clearTimer(id) {
            timers.delete(id);
        },
        pending: () => [...timers.values()].map((t) => t.ms),
        /** Fire the one pending timer. */
        async fire() {
            const [id, timer] = [...timers.entries()][0];
            timers.delete(id);
            await timer.fn();
        },
    };
}

const reply = (status, body = {}, extra = {}) => ({ status, ok: status >= 200 && status < 300, redirected: false, json: async () => body, ...extra });

function setup(responses, { visible = () => true } = {}) {
    const clock = fakeClock();
    const calls = [];
    const counts = [];
    let stopped = 0;
    const queue = [...responses];

    const poller = createPoller({
        url: '/platform/notifications/unread-count',
        onCount: (c) => counts.push(c),
        onStop: () => stopped++,
        fetchFn: async (url, options) => {
            calls.push({ url, options });
            const next = queue.shift();
            if (next instanceof Error) throw next;
            return next;
        },
        setTimer: clock.setTimer,
        clearTimer: clock.clearTimer,
        isVisible: visible,
    });

    return { poller, clock, calls, counts, stopped: () => stopped };
}

test('it asks every 60 seconds and reports the count', async () => {
    const { poller, clock, calls, counts } = setup([reply(200, { count: 3 }), reply(200, { count: 4 })]);

    poller.start();
    assert.deepEqual(clock.pending(), [60000]);

    await clock.fire();
    await clock.fire();

    assert.equal(calls.length, 2);
    assert.deepEqual(counts, [3, 4]);
    assert.deepEqual(clock.pending(), [60000], 'always exactly one timer waiting');
});

test('it asks for JSON from the same site with the session', async () => {
    const { poller, clock, calls } = setup([reply(200, { count: 0 })]);

    poller.start();
    await clock.fire();

    assert.equal(calls[0].options.headers.Accept, 'application/json');
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.equal(calls[0].options.cache, 'no-store');
});

for (const status of [401, 403, 419]) {
    test(`it stops for good on ${status}`, async () => {
        const { poller, clock, calls, stopped } = setup([reply(status), reply(200, { count: 9 })]);

        poller.start();
        await clock.fire();

        assert.equal(poller.stopped, true);
        assert.equal(stopped(), 1);
        assert.deepEqual(clock.pending(), [], 'no timer left');
        await poller.poll();
        await poller.wake();
        assert.equal(calls.length, 1, 'it never asks again');
    });
}

test('a redirect (to the sign-in page) stops it too', async () => {
    const { poller, clock, stopped } = setup([reply(200, {}, { redirected: true })]);

    poller.start();
    await clock.fire();

    assert.equal(poller.stopped, true);
    assert.equal(stopped(), 1);
});

test('errors back off 60s, 2m, 4m, 8m, then at most 15 minutes, and a good answer resets it', async () => {
    const { poller, clock } = setup([reply(500), new Error('offline'), reply(503), reply(500), reply(500), reply(200, { count: 1 })]);

    poller.start();
    const delays = [clock.pending()[0]];

    for (let i = 0; i < 5; i++) {
        await clock.fire();
        delays.push(clock.pending()[0]);
    }

    assert.deepEqual(delays, [60000, 120000, 240000, 480000, 900000, 900000]);
    assert.equal(poller.failures, 5);

    await clock.fire();
    assert.equal(poller.failures, 0);
    assert.deepEqual(clock.pending(), [60000], 'back to every minute after a good answer');
});

test('a bad body or a wrong count is ignored', async () => {
    const { poller, clock, counts } = setup([reply(200, { count: -1 }), reply(200, { count: 'many' }), reply(200, null), reply(200, { count: 2 })]);

    poller.start();
    for (let i = 0; i < 4; i++) await clock.fire();

    assert.deepEqual(counts, [2]);
});

test('a hidden tab does not call the server, it just keeps its place', async () => {
    let visible = false;
    const { poller, clock, calls } = setup([reply(200, { count: 5 })], { visible: () => visible });

    poller.start();
    await clock.fire();
    await clock.fire();
    assert.equal(calls.length, 0);
    assert.deepEqual(clock.pending(), [60000]);

    visible = true;
    await clock.fire();
    assert.equal(calls.length, 1);
});

test('coming back to the tab asks at once, but only if visible', async () => {
    let visible = false;
    const { poller, clock, calls, counts } = setup([reply(200, { count: 7 })], { visible: () => visible });

    poller.start();
    await poller.wake();
    assert.equal(calls.length, 0, 'still hidden');

    visible = true;
    await poller.wake();
    assert.deepEqual(counts, [7]);
    assert.deepEqual(clock.pending(), [60000]);
});

test('two requests never overlap', async () => {
    let release;
    const gate = new Promise((resolve) => (release = resolve));
    const clock = fakeClock();
    let calls = 0;
    const poller = createPoller({
        url: '/x',
        onCount: () => {},
        fetchFn: async () => {
            calls++;
            await gate;
            return reply(200, { count: 1 });
        },
        setTimer: clock.setTimer,
        clearTimer: clock.clearTimer,
        isVisible: () => true,
    });

    const first = poller.poll();
    const second = poller.poll();
    release();
    await Promise.all([first, second]);

    assert.equal(calls, 1);
});
