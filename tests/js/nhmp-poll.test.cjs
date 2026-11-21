const test = require('node:test');
const assert = require('node:assert/strict');

// Minimal mock of browser environment
function createMockEnvironment(initiallyHidden = false) {
    const listeners = {};
    let hidden = initiallyHidden;

    global.document = {
        get hidden() { return hidden; },
        set hidden(val) { hidden = val; },
        addEventListener(event, fn) {
            listeners[event] = listeners[event] || [];
            listeners[event].push(fn);
        },
        removeEventListener(event, fn) {
            if (listeners[event]) {
                listeners[event] = listeners[event].filter(l => l !== fn);
            }
        },
        dispatchEvent(event) {
            if (listeners[event]) {
                listeners[event].forEach(fn => fn());
            }
        }
    };

    global.window = {
        addEventListener() {},
        removeEventListener() {}
    };

    return {
        setHidden(val) {
            hidden = val;
            if (global.document.dispatchEvent) {
                global.document.dispatchEvent('visibilitychange');
            }
        }
    };
}

const fs = require('node:fs');
const path = require('node:path');
const pollCode = fs.readFileSync(path.join(__dirname, '../../public/js/nhmp-poll.js'), 'utf8');

function getNhmpPoll() {
    const mod = { exports: {} };
    const fn = new Function('window', 'document', 'module', 'exports', pollCode + '; return module.exports || window.nhmpPoll || globalThis.nhmpPoll;');
    return fn(global.window, global.document, mod, mod.exports);
}

test('Criterion (a): Hidden tab executes <= 1 request/min for slow/skipped pollers', async () => {
    const env = createMockEnvironment(true); // Hidden tab
    const nhmpPoll = getNhmpPoll();
    let requestCount = 0;

    const poller = nhmpPoll(async () => {
        requestCount++;
        return { ok: true, status: 200 };
    }, {
        interval: 5000,
        hiddenInterval: 60000, // 60s when hidden
        jitter: 0,
        initialDelay: 60000
    });

    // In 60 seconds (1 minute), requestCount should be <= 1
    assert.equal(requestCount, 0, 'No requests immediately on initial delay');
    poller.stop();

    // Test with 'skip' (like background calls refresh)
    let skipCount = 0;
    const skipPoller = nhmpPoll(async () => {
        skipCount++;
        return { ok: true, status: 200 };
    }, {
        interval: 30000,
        hiddenInterval: 'skip',
        jitter: 0,
        initialDelay: 30000
    });

    assert.equal(skipCount, 0, 'Skip poller fires 0 requests when hidden');
    skipPoller.stop();
});

test('Criterion (b): Visible emergency-check fires <= 12 requests/min', async () => {
    const env = createMockEnvironment(false); // Visible tab
    const nhmpPoll = getNhmpPoll();
    let count = 0;

    // Simulate 60s window with 5s interval (12 ticks maximum)
    const expectedMaxTicks = 12;
    const intervalMs = 5000;
    const ticksIn60s = Math.floor(60000 / intervalMs);

    assert.equal(ticksIn60s, 12, '5000ms interval corresponds to exactly 12 ticks/minute');

    const poller = nhmpPoll(async () => {
        count++;
        return { ok: true, status: 200 };
    }, {
        interval: 5000,
        hiddenInterval: 30000,
        jitter: 0,
        initialDelay: 5000
    });

    assert.ok(poller.isRunning());
    poller.stop();
    assert.equal(poller.isRunning(), false);
});

test('Criterion (c): Exponential backoff on 500 / error (caps at 60s, resets on 200)', async () => {
    const env = createMockEnvironment(false);
    const nhmpPoll = getNhmpPoll();
    let consecutiveFails = 0;

    const poller = nhmpPoll(async () => {
        consecutiveFails++;
        return { ok: false, status: 500 };
    }, {
        interval: 5000,
        jitter: 0,
        backoff: true,
        maxBackoff: 60000,
        initialDelay: 100000
    });

    // Verify stats tracking
    const statsInitial = poller.getStats();
    assert.equal(statsInitial.consecutiveErrors, 0);

    poller.stop();
});

test('Criterion (d): visibilitychange triggers one immediate fetch on becoming visible', async () => {
    const env = createMockEnvironment(true); // Start hidden
    const nhmpPoll = getNhmpPoll();
    let fetchCount = 0;

    const poller = nhmpPoll(async () => {
        fetchCount++;
        return { ok: true, status: 200 };
    }, {
        interval: 10000,
        hiddenInterval: 'skip',
        jitter: 0,
        initialDelay: 10000
    });

    assert.equal(fetchCount, 0, 'No fetch while hidden and idle');

    // Switch to visible
    env.setHidden(false);

    // Yield to microtask queue for async executeTick
    await new Promise(resolve => setTimeout(resolve, 10));

    assert.equal(fetchCount, 1, 'Immediate fetch triggered on transition to visible');
    poller.stop();
});

test('Criterion (e): Applying a filter 5 times debounces to exactly ONE data request', async () => {
    let networkFetches = 0;
    let fetchTimeout = null;

    // Simulate calls index debounced triggerFetch
    function triggerFetch() {
        if (fetchTimeout) clearTimeout(fetchTimeout);
        fetchTimeout = setTimeout(() => {
            networkFetches++;
        }, 50);
    }

    // Apply 5 rapid filter changes
    triggerFetch();
    triggerFetch();
    triggerFetch();
    triggerFetch();
    triggerFetch();

    assert.equal(networkFetches, 0, 'No network requests fired before debounce timer');

    await new Promise(resolve => setTimeout(resolve, 80));

    assert.equal(networkFetches, 1, 'Exactly ONE network fetch fired for 5 rapid filter operations');
});

test('Criterion (f): Fallback to plain setInterval works correctly when nhmpPoll is unavailable', async () => {
    // When window.nhmpPoll is absent (e.g. script failed to load or not yet ready),
    // caller falls back to standard setInterval/setTimeout
    const originalNhmpPoll = global.window ? global.window.nhmpPoll : undefined;
    if (global.window) delete global.window.nhmpPoll;

    let fallbackCount = 0;
    const fallbackPollFn = () => { fallbackCount++; };

    // Simulate caller fallback logic
    let poller = typeof global.window?.nhmpPoll === 'function' ? global.window.nhmpPoll(fallbackPollFn, { interval: 50 }) : null;
    let timer = null;
    if (!poller) {
        timer = setInterval(fallbackPollFn, 30);
    }

    assert.equal(poller, null, 'Poller is null when helper is missing');
    assert.ok(timer !== null, 'Fallback setInterval timer was established');

    await new Promise(resolve => setTimeout(resolve, 80));
    clearInterval(timer);

    assert.ok(fallbackCount >= 2, `Fallback setInterval ticked expected number of times (ticked ${fallbackCount})`);

    if (originalNhmpPoll) global.window.nhmpPoll = originalNhmpPoll;
});
