/**
 * NHMP 130 CRM - Desktop Alerts (Web Push) enrollment.
 *
 * - Explicit user click only (no auto-prompt on page load).
 * - Premium confirmation modal instead of native alert()/confirm().
 * - Results are shown with the CRM toast system (notification.js) when available.
 * - Does NOT depend on window.Notification: notification.js replaces that global with the
 *   toast API, so the permission state is read from PushManager.permissionState() and the
 *   browser prompt is triggered by pushManager.subscribe().
 */
(function () {
    'use strict';

    var STYLE_ID = 'nhmp-push-modal-style';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) { out[i] = raw.charCodeAt(i); }
        return out;
    }

    function toast(type, message, title) {
        try {
            if (type === 'success' && typeof window.showSuccess === 'function') return window.showSuccess(message, title);
            if (type === 'error' && typeof window.showError === 'function') return window.showError(message, title);
            if (type === 'warning' && typeof window.showWarning === 'function') return window.showWarning(message, title);
            if (typeof window.showInfo === 'function') return window.showInfo(message, title);
        } catch (e) { /* ignore */ }
        if (window.console) console.info('[DesktopAlerts]', title || '', message);
    }

    /* ------------------------------------------------------------------ */
    /* Premium modal                                                       */
    /* ------------------------------------------------------------------ */
    function ensureStyles() {
        if (document.getElementById(STYLE_ID)) return;
        var css = [
            '.nhmp-pm-overlay{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);opacity:0;transition:opacity .2s ease}',
            '.nhmp-pm-overlay.nhmp-pm-in{opacity:1}',
            '.nhmp-pm-card{width:100%;max-width:440px;background:#fff;border-radius:28px;box-shadow:0 30px 80px -20px rgba(15,23,42,.45),0 0 0 1px rgba(148,163,184,.25);padding:28px 28px 22px;transform:translateY(12px) scale(.97);transition:transform .25s cubic-bezier(.2,.9,.3,1.2);font-family:inherit;color:#0f172a}',
            '.nhmp-pm-overlay.nhmp-pm-in .nhmp-pm-card{transform:none}',
            '.nhmp-pm-icon{width:60px;height:60px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;margin:0 0 16px;box-shadow:0 12px 24px -8px rgba(37,99,235,.55);background:linear-gradient(135deg,#2563eb,#4f46e5)}',
            '.nhmp-pm-icon.ok{background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 12px 24px -8px rgba(5,150,105,.55)}',
            '.nhmp-pm-icon.bad{background:linear-gradient(135deg,#e11d48,#f43f5e);box-shadow:0 12px 24px -8px rgba(225,29,72,.5)}',
            '.nhmp-pm-icon.warn{background:linear-gradient(135deg,#d97706,#f59e0b);box-shadow:0 12px 24px -8px rgba(217,119,6,.5)}',
            '.nhmp-pm-title{font-size:20px;line-height:1.25;font-weight:800;letter-spacing:-.01em;margin:0 0 6px}',
            '.nhmp-pm-sub{font-size:13.5px;line-height:1.55;color:#475569;margin:0 0 16px}',
            '.nhmp-pm-list{list-style:none;margin:0 0 14px;padding:0;display:grid;gap:8px}',
            '.nhmp-pm-list li{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#1e293b}',
            '.nhmp-pm-list li i{width:32px;height:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#2563eb;font-size:14px;flex:none}',
            '.nhmp-pm-note{display:flex;gap:10px;align-items:flex-start;font-size:11.5px;line-height:1.5;color:#64748b;margin:0 0 18px}',
            '.nhmp-pm-note i{color:#10b981;margin-top:2px}',
            '.nhmp-pm-steps{margin:0 0 16px;padding:12px 14px 12px 30px;border-radius:16px;background:#fff1f2;border:1px solid #fecdd3;font-size:13px;line-height:1.6;color:#881337}',
            '.nhmp-pm-actions{display:flex;gap:10px;justify-content:flex-end}',
            '.nhmp-pm-btn{appearance:none;border:0;cursor:pointer;border-radius:16px;padding:11px 20px;font-size:13px;font-weight:700;font-family:inherit;transition:transform .12s ease,box-shadow .12s ease,background .12s ease}',
            '.nhmp-pm-btn:active{transform:scale(.97)}',
            '.nhmp-pm-btn.primary{color:#fff;background:linear-gradient(135deg,#2563eb,#4f46e5);box-shadow:0 10px 20px -8px rgba(37,99,235,.6)}',
            '.nhmp-pm-btn.primary:hover{box-shadow:0 14px 26px -8px rgba(37,99,235,.7)}',
            '.nhmp-pm-btn.danger{color:#fff;background:linear-gradient(135deg,#e11d48,#f43f5e);box-shadow:0 10px 20px -8px rgba(225,29,72,.55)}',
            '.nhmp-pm-btn.ghost{color:#475569;background:#f1f5f9}',
            '.nhmp-pm-btn.ghost:hover{background:#e2e8f0}',
            '.nhmp-pm-btn[disabled]{opacity:.7;cursor:progress}',
            '.nhmp-pm-spin{display:inline-block;width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;margin-right:8px;vertical-align:-2px;animation:nhmp-pm-rot .8s linear infinite}',
            '@keyframes nhmp-pm-rot{to{transform:rotate(360deg)}}',
            '@media (prefers-reduced-motion:reduce){.nhmp-pm-overlay,.nhmp-pm-card,.nhmp-pm-btn{transition:none}.nhmp-pm-spin{animation:none}}',
            '@media (max-width:480px){.nhmp-pm-card{padding:22px 20px 18px;border-radius:24px}.nhmp-pm-actions{flex-direction:column-reverse}.nhmp-pm-btn{width:100%}}'
        ].join('\n');
        var style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = css;
        document.head.appendChild(style);
    }

    var activeModal = null;

    function closeModal() {
        if (!activeModal) return;
        var m = activeModal; activeModal = null;
        m.overlay.classList.remove('nhmp-pm-in');
        document.removeEventListener('keydown', m.onKey, true);
        setTimeout(function () { if (m.overlay.parentNode) m.overlay.parentNode.removeChild(m.overlay); if (m.prevFocus && m.prevFocus.focus) { try { m.prevFocus.focus(); } catch (e) {} } }, 200);
    }

    /**
     * cfg: { icon, tone('' | ok | bad | warn), title, sub, items:[{icon,text}], note, steps:[...],
     *        actions:[{label, kind(primary|danger|ghost), onClick(btn)->Promise|void, keepOpen}] }
     */
    function openModal(cfg) {
        ensureStyles();
        if (activeModal) { closeModal(); }

        var overlay = document.createElement('div');
        overlay.className = 'nhmp-pm-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'nhmp-pm-title');

        var card = document.createElement('div');
        card.className = 'nhmp-pm-card';

        var icon = document.createElement('div');
        icon.className = 'nhmp-pm-icon ' + (cfg.tone || '');
        icon.innerHTML = '<i class="fa-solid ' + (cfg.icon || 'fa-bell') + '"></i>';
        card.appendChild(icon);

        var title = document.createElement('h3');
        title.className = 'nhmp-pm-title'; title.id = 'nhmp-pm-title'; title.textContent = cfg.title;
        card.appendChild(title);

        if (cfg.sub) { var sub = document.createElement('p'); sub.className = 'nhmp-pm-sub'; sub.textContent = cfg.sub; card.appendChild(sub); }

        if (cfg.items && cfg.items.length) {
            var ul = document.createElement('ul'); ul.className = 'nhmp-pm-list';
            cfg.items.forEach(function (it) {
                var li = document.createElement('li');
                li.innerHTML = '<i class="fa-solid ' + it.icon + '"></i>';
                var span = document.createElement('span'); span.textContent = it.text; li.appendChild(span);
                ul.appendChild(li);
            });
            card.appendChild(ul);
        }

        if (cfg.steps && cfg.steps.length) {
            var ol = document.createElement('ol'); ol.className = 'nhmp-pm-steps';
            cfg.steps.forEach(function (s) { var li = document.createElement('li'); li.textContent = s; ol.appendChild(li); });
            card.appendChild(ol);
        }

        if (cfg.note) {
            var note = document.createElement('div'); note.className = 'nhmp-pm-note';
            note.innerHTML = '<i class="fa-solid fa-shield-halved"></i>';
            var nt = document.createElement('span'); nt.textContent = cfg.note; note.appendChild(nt);
            card.appendChild(note);
        }

        var actions = document.createElement('div'); actions.className = 'nhmp-pm-actions';
        var firstPrimary = null;
        (cfg.actions || []).forEach(function (a) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'nhmp-pm-btn ' + (a.kind || 'ghost'); b.textContent = a.label;
            b.addEventListener('click', function () {
                var res;
                try { res = a.onClick ? a.onClick(b) : null; } catch (e) { res = null; }
                if (!a.keepOpen) closeModal();
                return res;
            });
            if (!firstPrimary && (a.kind === 'primary' || a.kind === 'danger')) firstPrimary = b;
            actions.appendChild(b);
        });
        card.appendChild(actions);
        overlay.appendChild(card);

        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay && cfg.dismissible !== false) closeModal(); });
        var onKey = function (e) {
            if (e.key === 'Escape' && cfg.dismissible !== false) { e.stopPropagation(); closeModal(); }
            if (e.key === 'Tab') {
                var f = card.querySelectorAll('button:not([disabled])'); if (!f.length) return;
                var first = f[0], last = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        };
        document.addEventListener('keydown', onKey, true);

        var prevFocus = document.activeElement;
        document.body.appendChild(overlay);
        activeModal = { overlay: overlay, onKey: onKey, prevFocus: prevFocus, card: card };
        requestAnimationFrame(function () { overlay.classList.add('nhmp-pm-in'); if (firstPrimary) firstPrimary.focus(); });
        return activeModal;
    }

    /* ------------------------------------------------------------------ */
    /* Enrollment logic                                                    */
    /* ------------------------------------------------------------------ */
    var WebPushClient = {
        swRegistration: null,
        isSubscribed: false,

        isSupported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window;
        },

        permissionState: async function () {
            try {
                if (this.swRegistration && this.swRegistration.pushManager.permissionState) {
                    return await this.swRegistration.pushManager.permissionState({ userVisibleOnly: true });
                }
            } catch (e) { /* fall through */ }
            return 'prompt';
        },

        init: async function () {
            if (window.NHMP_ALERTS_NONE) { return; } // call-centre agents receive no alerts
            if (!this.isSupported()) { this.updateUiState('unsupported'); this.bindEvents(); return; }
            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                var sub = await this.swRegistration.pushManager.getSubscription();
                this.isSubscribed = !(sub === null);
                var perm = await this.permissionState();
                if (perm === 'denied') this.updateUiState('blocked');
                else if (this.isSubscribed) this.updateUiState('subscribed');
                else this.updateUiState('unsubscribed');
            } catch (err) {
                if (window.console) console.error('[WebPush] Service Worker registration failed:', err);
                this.updateUiState('error');
            }
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;
            var btn = document.getElementById('btn-desktop-alerts-toggle');
            if (!btn || btn.getAttribute('data-bound') === '1') return;
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!self.isSupported()) return self.showUnsupported();
                if (!self.swRegistration) return self.showError('Desktop alerts are still starting up. Please try again in a moment.');
                self.permissionState().then(function (perm) {
                    if (self.isSubscribed && window.NHMP_ALERTS_LOCKED) { toast('info', 'Alerts are mandatory for your role and cannot be turned off.', 'Alerts always on'); return; }
                    if (self.isSubscribed) return self.confirmDisable();
                    if (perm === 'denied') return self.showBlocked();
                    return self.confirmEnable();
                });
            });
        },

        /* ---- modals ---- */
        confirmEnable: function () {
            var self = this;
            openModal({
                icon: 'fa-bell', tone: '',
                title: 'Turn on desktop alerts?',
                sub: 'Get instant notifications on this computer, even when 130 CRM is in the background.',
                items: [
                    { icon: 'fa-triangle-exclamation', text: 'New pending helps in your area' },
                    { icon: 'fa-road', text: 'Travel advisory approvals and updates' },
                    { icon: 'fa-headset', text: 'Support ticket updates' }
                ],
                note: 'Alerts never include caller names, phone numbers or CNIC. Your browser will ask for permission next: choose "Allow". You can turn alerts off at any time.',
                actions: [
                    { label: 'Not now', kind: 'ghost' },
                    { label: 'Enable alerts', kind: 'primary', keepOpen: true, onClick: function (btn) { return self.enable(btn); } }
                ]
            });
        },

        confirmDisable: function () {
            var self = this;
            openModal({
                icon: 'fa-bell-slash', tone: 'warn',
                title: 'Turn off desktop alerts?',
                sub: 'You will stop receiving notifications on this computer while the CRM is in the background. Alerts inside the CRM keep working.',
                actions: [
                    { label: 'Keep alerts on', kind: 'ghost' },
                    { label: 'Turn off', kind: 'danger', keepOpen: true, onClick: function (btn) { return self.disable(btn); } }
                ]
            });
        },

        showBlocked: function () {
            openModal({
                icon: 'fa-ban', tone: 'bad',
                title: 'Alerts are blocked in your browser',
                sub: 'The browser is blocking notifications for this site. To turn them on:',
                steps: [
                    'Click the lock / tune icon at the left of the address bar.',
                    'Open "Site settings" (or "Permissions").',
                    'Set Notifications to "Allow", then reload this page.'
                ],
                actions: [{ label: 'Got it', kind: 'primary' }]
            });
        },

        showUnsupported: function () {
            openModal({
                icon: 'fa-circle-info', tone: 'warn',
                title: 'Desktop alerts are not available here',
                sub: 'This browser does not support desktop alerts. Please use a current version of Chrome, Edge or Firefox over HTTPS. Alerts inside the CRM continue to work.',
                actions: [{ label: 'OK', kind: 'primary' }]
            });
        },

        showError: function (message) {
            toast('error', message, 'Desktop alerts');
        },

        showSuccessModal: function () {
            var m = openModal({
                icon: 'fa-circle-check', tone: 'ok',
                title: 'Desktop alerts are on',
                sub: 'You will now receive alerts on this computer. Keep the CRM open in a tab; you can close the window and still get notified.',
                actions: [{ label: 'Done', kind: 'primary' }]
            });
            setTimeout(function () { if (activeModal === m) closeModal(); }, 3500);
        },

        /* ---- actions ---- */
        setBusy: function (btn, busy, label) {
            if (!btn) return;
            btn.disabled = busy;
            btn.innerHTML = busy ? '<span class="nhmp-pm-spin"></span>' + label : label;
        },

        enable: async function (btn) {
            var original = btn ? btn.textContent : '';
            this.setBusy(btn, true, 'Waiting for browser permission…');
            this.updateUiState('loading');
            try {
                // 1. Server VAPID public key
                var keyResp = await fetch('/web-push/vapid-public-key', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!keyResp.ok) throw new Error('The alert service is not available right now.');
                var keyData = await keyResp.json();
                if (!keyData.publicKey) throw new Error('The alert service is not configured yet.');

                // 2. Subscribe (the browser shows its permission prompt here when needed)
                var subscription = await this.swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(keyData.publicKey)
                });

                // 3. Save on the server
                var subJson = subscription.toJSON();
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var saveResp = await fetch('/web-push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ endpoint: subJson.endpoint, keys: { p256dh: subJson.keys && subJson.keys.p256dh, auth: subJson.keys && subJson.keys.auth } })
                });
                if (!saveResp.ok) {
                    var body = await saveResp.json().catch(function () { return {}; });
                    throw new Error(body.error || 'The server could not save your alert settings.');
                }

                this.isSubscribed = true;
                this.updateUiState('subscribed');
                closeModal();
                toast('success', 'Desktop alerts are now on for this computer.', 'Alerts enabled');
                this.showSuccessModal();
            } catch (err) {
                if (window.console) console.error('[WebPush] Enable failed:', err);
                this.updateUiState(this.isSubscribed ? 'subscribed' : 'unsubscribed');
                var perm = await this.permissionState();
                if (perm === 'denied' || (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError'))) {
                    closeModal(); this.updateUiState('blocked'); this.showBlocked();
                } else {
                    this.setBusy(btn, false, original || 'Enable alerts');
                    closeModal();
                    this.showError('Could not enable desktop alerts. ' + (err && err.message ? err.message : 'Please try again.'));
                }
            }
        },

        disable: async function (btn) {
            if (window.NHMP_ALERTS_LOCKED) { toast('info', 'Alerts are mandatory for your role and cannot be turned off.', 'Alerts always on'); return; }
            this.setBusy(btn, true, 'Turning off…');
            this.updateUiState('loading');
            try {
                var subscription = await this.swRegistration.pushManager.getSubscription();
                if (subscription) {
                    var csrf = document.querySelector('meta[name="csrf-token"]');
                    await fetch('/web-push/unsubscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ endpoint: subscription.endpoint })
                    }).catch(function (e) { if (window.console) console.warn('[WebPush] Server unsubscribe warning:', e); });
                    await subscription.unsubscribe();
                }
                this.isSubscribed = false;
                this.updateUiState('unsubscribed');
                closeModal();
                toast('info', 'Desktop alerts are now off for this computer.', 'Alerts turned off');
            } catch (err) {
                if (window.console) console.error('[WebPush] Disable error:', err);
                this.updateUiState('subscribed');
                closeModal();
                this.showError('Could not turn off desktop alerts. Please try again.');
            }
        },

        /* ---- header button state ---- */
        updateUiState: function (state) {
            var btn = document.getElementById('btn-desktop-alerts-toggle');
            var dot = document.getElementById('desktop-alerts-status-dot');
            var text = document.getElementById('desktop-alerts-status-text');
            if (!btn) return;
            btn.setAttribute('data-state', state);
            btn.classList.remove('opacity-40', 'cursor-not-allowed');
            switch (state) {
                case 'subscribed':
                    btn.title = 'Desktop Alerts: On (click to turn off)';
                    if (dot) dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                    if (text) text.textContent = 'Alerts On';
                    break;
                case 'unsubscribed':
                    btn.title = 'Desktop Alerts: Off (click to turn on)';
                    if (dot) dot.className = 'w-2 h-2 rounded-full bg-slate-400';
                    if (text) text.textContent = 'Enable Alerts';
                    break;
                case 'blocked':
                    btn.title = 'Desktop Alerts: Blocked in browser settings';
                    if (dot) dot.className = 'w-2 h-2 rounded-full bg-rose-500';
                    if (text) text.textContent = 'Alerts Blocked';
                    break;
                case 'unsupported':
                    btn.classList.add('opacity-40');
                    btn.title = 'Desktop Alerts: Not supported in this browser';
                    if (dot) dot.className = 'w-2 h-2 rounded-full bg-slate-500';
                    if (text) text.textContent = 'Unsupported';
                    break;
                case 'loading':
                    if (dot) dot.className = 'w-2 h-2 rounded-full bg-amber-500 animate-ping';
                    if (text) text.textContent = 'Updating…';
                    break;
                default:
                    break;
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { WebPushClient.init(); });
    } else {
        WebPushClient.init();
    }
    window.WebPushClient = WebPushClient;
})();
