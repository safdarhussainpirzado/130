/**
 * NHMP Intelligent Adaptive Polling Manager
 * 
 * Features:
 * - Tab visibility detection: pauses or slows down (>=60s) when document is hidden
 * - Resumes immediately with one fetch when tab becomes visible (visibilitychange)
 * - Adds +/- 10% jitter to prevent thundering herds across multiple operator desks
 * - Exponential backoff on errors, 429, or 5xx responses (capped at 60s), resets on success
 * - Strict overlap prevention: skips tick if previous asynchronous request is still in-flight
 * - Clean lifecycle: AbortController support and automatic teardown on page unload
 *
 * @param {Function} fn Async callback to execute. If it returns a Response (with .ok / .status) or throws, backoff is adjusted.
 * @param {Object} [options]
 * @param {number} [options.interval=10000] Visible polling interval in milliseconds
 * @param {number|string|null} [options.hiddenInterval=60000] Hidden polling interval ('skip', 0, or ms)
 * @param {number} [options.jitter=0.1] Fractional jitter (0.1 = +/- 10%)
 * @param {boolean} [options.backoff=true] Enable exponential backoff on failure
 * @param {number} [options.maxBackoff=60000] Maximum interval during backoff in ms
 * @param {number} [options.initialDelay=0] Initial delay before first tick in ms
 * @param {boolean} [options.immediate=false] Whether to run immediately on start()
 * @returns {Object} Controller: { start(), stop(), trigger(), isRunning(), getStats() }
 */
(function (root, factory) {
    if (typeof exports === 'object' && typeof module !== 'undefined') {
        module.exports = factory();
    } else if (typeof define === 'function' && define.amd) {
        define(factory);
    } else {
        var g = typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : (typeof self !== 'undefined' ? self : root));
        if (g) {
            g.nhmpPoll = factory();
        }
    }
})(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : this), function () {
    return function nhmpPoll(fn, options) {
        var opts = options || {};
        var baseInterval = typeof opts.interval === 'number' ? opts.interval : 10000;
        var hiddenInterval = opts.hiddenInterval !== undefined ? opts.hiddenInterval : 60000;
        var jitterRatio = typeof opts.jitter === 'number' ? opts.jitter : 0.1;
        var enableBackoff = opts.backoff !== false;
        var maxBackoff = typeof opts.maxBackoff === 'number' ? opts.maxBackoff : 60000;
        var initialDelay = typeof opts.initialDelay === 'number' ? opts.initialDelay : 0;
        var runImmediate = opts.immediate === true;

        var timerId = null;
        var running = false;
        var inFlight = false;
        var consecutiveErrors = 0;
        var totalRequests = 0;
        var totalErrors = 0;
        var abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        function isHidden() {
            return typeof document !== 'undefined' && document.hidden;
        }

        function calculateDelay() {
            var hidden = isHidden();
            if (hidden) {
                if (hiddenInterval === 'skip' || hiddenInterval === null || hiddenInterval === 0 || hiddenInterval === false) {
                    return null; // pause when hidden
                }
                return typeof hiddenInterval === 'number' ? hiddenInterval : 60000;
            }

            var delay = baseInterval;
            if (enableBackoff && consecutiveErrors > 0) {
                var factor = Math.pow(2, Math.min(consecutiveErrors, 6)); // max 64x, capped by maxBackoff
                delay = Math.min(maxBackoff, baseInterval * factor);
            }

            // Apply +/- jitter
            if (jitterRatio > 0) {
                var jitterRange = delay * jitterRatio;
                var randomDelta = (Math.random() * 2 - 1) * jitterRange;
                delay = Math.max(500, Math.round(delay + randomDelta));
            }

            return delay;
        }

        function scheduleNext(customDelay) {
            if (!running) return;
            if (timerId !== null) {
                clearTimeout(timerId);
                timerId = null;
            }

            var nextDelay = customDelay !== undefined ? customDelay : calculateDelay();
            if (nextDelay === null) {
                // Tab is hidden and hiddenInterval is 'skip' -> wait for visibilitychange
                return;
            }

            timerId = setTimeout(function () {
                executeTick();
            }, nextDelay);
        }

        async function executeTick() {
            if (!running) return;
            if (inFlight) {
                // Overlap protection: previous request still running, reschedule
                scheduleNext();
                return;
            }

            inFlight = true;
            totalRequests++;

            try {
                var result = await fn(abortController ? abortController.signal : undefined);
                // If the function returned a Response or object with ok/status, check status
                var isFailure = false;
                if (result && typeof result === 'object') {
                    if (result.ok === false || (result.status >= 400 && result.status !== 304)) {
                        isFailure = true;
                    }
                }

                if (isFailure) {
                    consecutiveErrors++;
                    totalErrors++;
                } else {
                    consecutiveErrors = 0; // reset backoff on success
                }
            } catch (err) {
                // AbortError is not considered a server failure
                if (err && err.name === 'AbortError') {
                    inFlight = false;
                    return;
                }
                consecutiveErrors++;
                totalErrors++;
            } finally {
                inFlight = false;
                if (running) {
                    scheduleNext();
                }
            }
        }

        function onVisibilityChange() {
            if (!running) return;

            if (isHidden()) {
                // Tab transitioned to hidden: if skipping, clear running timer
                if (hiddenInterval === 'skip' || hiddenInterval === null || hiddenInterval === 0 || hiddenInterval === false) {
                    if (timerId !== null) {
                        clearTimeout(timerId);
                        timerId = null;
                    }
                } else {
                    // Reschedule with hiddenInterval
                    scheduleNext(typeof hiddenInterval === 'number' ? hiddenInterval : 60000);
                }
            } else {
                // Tab transitioned to visible: trigger immediate fetch and resume visible interval
                if (timerId !== null) {
                    clearTimeout(timerId);
                    timerId = null;
                }
                executeTick();
            }
        }

        function onPageUnload() {
            stop();
            if (abortController) {
                try {
                    abortController.abort();
                } catch (e) {}
            }
        }

        function start() {
            if (running) return;
            running = true;
            consecutiveErrors = 0;

            if (typeof document !== 'undefined') {
                document.addEventListener('visibilitychange', onVisibilityChange);
            }
            if (typeof window !== 'undefined') {
                window.addEventListener('beforeunload', onPageUnload, { once: true });
            }

            if (runImmediate || initialDelay === 0) {
                executeTick();
            } else {
                scheduleNext(initialDelay);
            }
        }

        function stop() {
            running = false;
            if (timerId !== null) {
                clearTimeout(timerId);
                timerId = null;
            }
            if (typeof document !== 'undefined') {
                document.removeEventListener('visibilitychange', onVisibilityChange);
            }
        }

        function trigger() {
            if (!running) return;
            if (timerId !== null) {
                clearTimeout(timerId);
                timerId = null;
            }
            executeTick();
        }

        // Auto-start by default
        start();

        return {
            start: start,
            stop: stop,
            trigger: trigger,
            isRunning: function () { return running; },
            getStats: function () {
                return {
                    totalRequests: totalRequests,
                    totalErrors: totalErrors,
                    consecutiveErrors: consecutiveErrors,
                    inFlight: inFlight,
                    isHidden: isHidden()
                };
            }
        };
    };
});
