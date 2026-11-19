/**
 * Real-time Travel Advisory alerts (toast + optional voice) between sector operators and supervisors.
 * Polls /ops/travel-advisory/events every few seconds. Voice follows the header voice toggle (nhmp_voice_enabled).
 * Dispatches window event "ta:event" so open advisory pages refresh themselves.
 */
(function () {
    if (window.__taLiveAlerts) return;
    window.__taLiveAlerts = true;
    var cfg = window.NHMP_TA_LIVE;
    if (!cfg || !cfg.url) return;

    var after = null;
    var stopped = false;
    var INTERVAL = cfg.interval || 8000;

    function speak(text) {
        try {
            if (!('speechSynthesis' in window) || localStorage.getItem('nhmp_voice_enabled') !== 'true') return;
            var u = new SpeechSynthesisUtterance(text);
            u.rate = 1.0;
            window.__taUtterance = u; // keep a reference so it is not garbage-collected mid-speech
            window.speechSynthesis.cancel();
            setTimeout(function () { window.speechSynthesis.speak(u); }, 50);
        } catch (e) { /* browser audio policy */ }
    }

    function toast(level, text, title) {
        var fn = level === 'success' ? window.showSuccess : (level === 'warning' ? window.showWarning : (level === 'error' ? window.showError : window.showInfo));
        if (typeof fn === 'function') fn(text, title);
    }

    function describe(e) {
        var where = (e.route_name || 'Corridor') + (e.start_km != null ? ' KM ' + Number(e.start_km).toFixed(0) + '-' + Number(e.end_km).toFixed(0) : '');
        var who = e.actor_name || (e.actor_is_supervisor ? 'a supervisor' : 'a sector operator');
        var t = e.title ? ' ' + e.title : '';
        switch (e.type) {
            case 'created':
                return { level: 'warning', title: 'New advisory awaiting approval', text: where + ' - submitted by ' + who + '.' + t,
                    voice: 'Attention. New travel advisory on ' + (e.route_name || 'a corridor') + ' submitted by ' + who + ' is awaiting approval.' };
            case 'approved':
                return { level: 'success', title: 'Advisory approved and live', text: where + ' - approved by ' + who + '.' + t,
                    voice: 'Travel advisory on ' + (e.route_name || 'a corridor') + ' has been approved and is now live.' };
            case 'rejected':
                return { level: 'error', title: 'Advisory rejected', text: where + ' - rejected by ' + who + (e.reason ? ': ' + e.reason : '.'),
                    voice: 'Attention. Travel advisory on ' + (e.route_name || 'a corridor') + ' has been rejected by ' + who + '.' };
            case 'updated':
                return { level: 'info', title: 'Advisory updated', text: where + ' - updated by ' + who + '.' + t,
                    voice: 'Travel advisory on ' + (e.route_name || 'a corridor') + ' has been updated by ' + who + '.' };
            case 'resolved':
                return { level: 'info', title: 'Advisory cleared', text: where + ' - cleared by ' + who + '.' + t,
                    voice: 'Travel advisory on ' + (e.route_name || 'a corridor') + ' has been cleared by ' + who + '.' };
            case 'extended':
                return { level: 'info', title: 'Advisory validity extended', text: where + ' - extended' + (e.hours ? ' by ' + e.hours + ' hours' : '') + ' by ' + who + '.',
                    voice: 'Travel advisory on ' + (e.route_name || 'a corridor') + ' has been extended by ' + who + '.' };
            default:
                return null;
        }
    }

    function handle(events) {
        var voiceLines = [];
        events.forEach(function (e) {
            var d = describe(e);
            if (!d) return;
            toast(d.level, d.text, d.title);
            voiceLines.push(d.voice);
            try { window.dispatchEvent(new CustomEvent('ta:event', { detail: e })); } catch (x) {}
        });
        if (voiceLines.length) speak(voiceLines.length > 3 ? voiceLines.length + ' travel advisory updates received.' : voiceLines.join(' '));
    }

    async function poll() {
        if (stopped) return;
        try {
            var res = await fetch(cfg.url + (after === null ? '' : '?after=' + after), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (res.status === 401 || res.status === 403) { stopped = true; return; }
            if (!res.ok) return;
            var data = await res.json();
            if (after !== null && data.events && data.events.length) handle(data.events);
            if (typeof data.last_seq === 'number') after = data.last_seq;
        } catch (e) { /* transient network error: try again next tick */ }
    }

    setTimeout(function () { poll(); setInterval(poll, INTERVAL); }, 3000);
})();
