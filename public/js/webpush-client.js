/**
 * NHMP 130 Unified CRM Web Push Client
 *
 * Implements user-initiated push notification enrollment, VAPID key exchange,
 * subscription storage, and status reflection.
 *
 * No auto-prompt on page load. Explicit user click only.
 */

(function () {
    'use strict';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    const WebPushClient = {
        swRegistration: null,
        isSubscribed: false,

        isSupported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        },

        init: async function () {
            if (!this.isSupported()) {
                this.updateUiState('unsupported');
                return;
            }

            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });

                const subscription = await this.swRegistration.pushManager.getSubscription();
                this.isSubscribed = !(subscription === null);

                if (Notification.permission === 'denied') {
                    this.updateUiState('blocked');
                } else if (this.isSubscribed) {
                    this.updateUiState('subscribed');
                } else {
                    this.updateUiState('unsubscribed');
                }
            } catch (err) {
                console.error('[WebPush] Service Worker registration failed:', err);
                this.updateUiState('error');
            }

            this.bindEvents();
        },

        bindEvents: function () {
            const toggleBtn = document.getElementById('btn-desktop-alerts-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.isSubscribed) {
                        this.unsubscribeUser();
                    } else {
                        this.subscribeUser();
                    }
                });
            }
        },

        subscribeUser: async function () {
            if (!this.isSupported()) {
                alert('Desktop alerts are not supported by this browser.');
                return;
            }

            this.updateUiState('loading');

            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.updateUiState('blocked');
                    alert('Notification permission was denied. Please allow notifications in your browser address bar settings.');
                    return;
                }

                // 1. Fetch Server VAPID Public Key
                const keyResp = await fetch('/web-push/vapid-public-key', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!keyResp.ok) {
                    throw new Error('Failed to retrieve VAPID public key from server');
                }

                const keyData = await keyResp.json();
                if (!keyData.publicKey) {
                    throw new Error('VAPID public key not configured on server');
                }

                // 2. Subscribe with Browser Push Manager
                const convertedVapidKey = urlBase64ToUint8Array(keyData.publicKey);
                const subscription = await this.swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedVapidKey
                });

                // 3. Send subscription to Laravel backend
                const subJson = subscription.toJSON();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const saveResp = await fetch('/web-push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        endpoint: subJson.endpoint,
                        keys: {
                            p256dh: subJson.keys?.p256dh,
                            auth: subJson.keys?.auth
                        }
                    })
                });

                if (!saveResp.ok) {
                    const errBody = await saveResp.json().catch(() => ({}));
                    throw new Error(errBody.error || 'Server rejected push subscription');
                }

                this.isSubscribed = true;
                this.updateUiState('subscribed');
            } catch (err) {
                console.error('[WebPush] Subscription failed:', err);
                alert('Could not enable desktop alerts: ' + err.message);
                this.updateUiState(this.isSubscribed ? 'subscribed' : 'unsubscribed');
            }
        },

        unsubscribeUser: async function () {
            this.updateUiState('loading');

            try {
                const subscription = await this.swRegistration.pushManager.getSubscription();
                if (subscription) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    // Notify server
                    await fetch('/web-push/unsubscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            endpoint: subscription.endpoint
                        })
                    }).catch((e) => console.warn('[WebPush] Server unsubscribe warning:', e));

                    await subscription.unsubscribe();
                }

                this.isSubscribed = false;
                this.updateUiState('unsubscribed');
            } catch (err) {
                console.error('[WebPush] Unsubscribe error:', err);
                this.updateUiState('subscribed');
            }
        },

        updateUiState: function (state) {
            const toggleBtn = document.getElementById('btn-desktop-alerts-toggle');
            const statusDot = document.getElementById('desktop-alerts-status-dot');
            const statusText = document.getElementById('desktop-alerts-status-text');

            if (!toggleBtn) return;

            toggleBtn.setAttribute('data-state', state);

            switch (state) {
                case 'subscribed':
                    toggleBtn.classList.remove('text-slate-400', 'text-rose-400', 'opacity-50');
                    toggleBtn.classList.add('text-emerald-400');
                    toggleBtn.title = 'Desktop Alerts: Active (Click to disable)';
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                    if (statusText) statusText.textContent = 'Alerts Active';
                    break;

                case 'unsubscribed':
                    toggleBtn.classList.remove('text-emerald-400', 'text-rose-400', 'opacity-50');
                    toggleBtn.classList.add('text-slate-400');
                    toggleBtn.title = 'Desktop Alerts: Inactive (Click to enable)';
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-slate-500';
                    if (statusText) statusText.textContent = 'Enable Alerts';
                    break;

                case 'blocked':
                    toggleBtn.classList.remove('text-emerald-400', 'text-slate-400', 'opacity-50');
                    toggleBtn.classList.add('text-rose-400');
                    toggleBtn.title = 'Desktop Alerts: Blocked in browser settings';
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-rose-500';
                    if (statusText) statusText.textContent = 'Alerts Blocked';
                    break;

                case 'unsupported':
                    toggleBtn.classList.add('opacity-40', 'cursor-not-allowed', 'text-slate-500');
                    toggleBtn.title = 'Desktop Alerts: Not supported in this browser';
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-slate-600';
                    if (statusText) statusText.textContent = 'Unsupported';
                    break;

                case 'loading':
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-amber-500 animate-ping';
                    if (statusText) statusText.textContent = 'Updating...';
                    break;
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => WebPushClient.init());
    } else {
        WebPushClient.init();
    }

    window.WebPushClient = WebPushClient;
})();
