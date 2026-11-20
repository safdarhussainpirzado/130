/**
 * NHMP 130 Unified CRM Service Worker (Desk Scope)
 *
 * Implements Web Push API event handling, same-origin relative path navigation,
 * and P1/P2 interaction pinning.
 *
 * Header delivery: Cache-Control: no-cache
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

    // Strict same-origin relative URL validation
    let targetUrl = '/';
    if (payload.data && typeof payload.data.url === 'string') {
        const candidate = payload.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes('://')) {
            targetUrl = candidate;
        }
    }

    // requireInteraction is strictly limited to P1 / Critical / Emergency help calls
    const isP1 = !!(
        payload.data && (
            payload.data.requireInteraction === true ||
            payload.data.priority === 'critical' ||
            payload.data.priority === 'emergency' ||
            payload.data.priority === 'p1'
        )
    );

    const options = {
        body: body,
        icon: payload.icon || '/favicon-192x192.png',
        badge: payload.badge || '/favicon-32x32.png',
        tag: payload.tag || 'nhmp-130-alert',
        data: Object.assign({}, payload.data || {}, { url: targetUrl }),
        requireInteraction: isP1,
        vibrate: isP1 ? [300, 100, 300, 100, 300] : [200, 100, 200],
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    let targetPath = '/';
    if (event.notification.data && typeof event.notification.data.url === 'string') {
        const candidate = event.notification.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes('://')) {
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
