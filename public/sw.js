/**
 * NHMP 130 Unified CRM Service Worker (Desk Scope)
 *
 * Implements W3C Web Push event handling, strict same-origin navigation,
 * and high-priority OS desktop alert banner display.
 *
 * Zero third-party scripts. Fail-closed same-origin routing.
 */

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};
    try {
        payload = event.data.json();
    } catch (e) {
        payload = {
            title: 'NHMP 130 Alert',
            body: event.data.text(),
        };
    }

    // Payload hygiene & bounded lengths
    const title = (payload.title || 'NHMP 130 Operations Alert').substring(0, 80);
    const body = (payload.body || 'New operational activity requires your attention.').substring(0, 200);

    // Strict same-origin relative URL validation (fallback to /dashboard)
    let targetUrl = '/dashboard';
    if (payload.data && typeof payload.data.url === 'string') {
        const candidate = payload.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes(':') && !candidate.includes('\\')) {
            targetUrl = candidate;
        }
    }

    // requireInteraction is strictly pinned to P1 / P2 help calls
    const isP1OrP2 = Boolean(
        payload.data && (
            payload.data.requireInteraction === true ||
            Number(payload.data.priority) === 1 ||
            Number(payload.data.priority) === 2
        )
    );

    const options = {
        body: body,
        icon: payload.icon || '/favicon-192x192.png',
        badge: payload.badge || '/favicon-32x32.png',
        tag: payload.tag || 'nhmp-130-alert',
        data: Object.assign({}, payload.data || {}, { url: targetUrl }),
        requireInteraction: isP1OrP2 || payload.data?.requireInteraction === true,
        renotify: true,
        vibrate: isP1OrP2 ? [300, 100, 300, 100, 300, 100, 400] : [200, 100, 200],
    };

    // Ask all open browser tabs to play acoustic alarm and spoken voice alert (VOICE-1)
    const broadcastVoiceAlarm = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
        for (const client of clientList) {
            client.postMessage({
                type: 'TRIGGER_VOICE_ALARM',
                payload: {
                    id: payload.data?.call_id ? ('call-' + payload.data.call_id + '-r' + (payload.data.repeat || 0)) : (payload.tag || null),
                    title: title,
                    message: body,
                    priority: payload.data?.priority || 3,
                    speak_text: payload.data?.speak_text || body,
                    alarm: Boolean(payload.data?.alarm !== false),
                }
            });
        }
    });

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(title, options),
            broadcastVoiceAlarm
        ])
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Strict same-origin relative URL validation (fallback to /dashboard)
    let targetPath = '/dashboard';
    if (event.notification.data && typeof event.notification.data.url === 'string') {
        const candidate = event.notification.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes(':') && !candidate.includes('\\')) {
            targetPath = candidate;
        }
    }

    // Construct target URL strictly anchored to the current origin
    const fullTargetUrl = new URL(targetPath, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // If an open window is already on the target URL, bring it to focus
            for (const client of clientList) {
                if (client.url === fullTargetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // Otherwise focus any open CRM window and navigate it, or open a new window
            if (clientList.length > 0 && 'focus' in clientList[0] && 'navigate' in clientList[0]) {
                clientList[0].focus();
                return clientList[0].navigate(fullTargetUrl);
            }
            if (clients.openWindow) {
                return clients.openWindow(fullTargetUrl);
            }
        })
    );
});
