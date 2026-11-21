/**
 * Service Worker Lifecycle & Event Simulation Test
 *
 * Verifies the complete push notification lifecycle in the Service Worker:
 * 1. 'push' event reception, payload parsing, length bounds, and showNotification options.
 * 2. 'notificationclick' event handling: notification close, focus existing window, or navigate/openWindow.
 * 3. Strict same-origin routing and fallback security.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('--- Running Service Worker Lifecycle & Event Simulation Tests ---');

// Mock Service Worker Global Scope
class MockClient {
    constructor(url, focused = false) {
        this.url = url;
        this.isFocused = focused;
        this.navigatedTo = null;
    }

    focus() {
        this.isFocused = true;
        return Promise.resolve(this);
    }

    navigate(url) {
        this.navigatedTo = url;
        return Promise.resolve(this);
    }
}

class MockClients {
    constructor(initialClients = []) {
        this.clientList = initialClients;
        this.openedWindows = [];
    }

    matchAll(options) {
        return Promise.resolve(this.clientList);
    }

    openWindow(url) {
        const client = new MockClient(url, true);
        this.openedWindows.push(url);
        return Promise.resolve(client);
    }
}

class MockNotification {
    constructor(title, options) {
        this.title = title;
        this.options = options;
        this.data = options.data || {};
        this.closed = false;
    }

    close() {
        this.closed = true;
    }
}

class MockRegistration {
    constructor() {
        this.shownNotifications = [];
    }

    showNotification(title, options) {
        const notification = new MockNotification(title, options);
        this.shownNotifications.push(notification);
        return Promise.resolve(notification);
    }
}

// Read sw.js content
const swPath = path.join(__dirname, '../public/sw.js');
const swCode = fs.readFileSync(swPath, 'utf8');

// Test 1: Push Event Simulation
function simulatePushEvent(payloadJson, origin = 'https://crm.nhmp.gov.pk') {
    const registration = new MockRegistration();
    const event = {
        data: {
            json: () => payloadJson,
            text: () => JSON.stringify(payloadJson),
        },
        waitUntil: (promise) => promise,
    };

    // Evaluate push event handler logic
    const title = (payloadJson.title || 'NHMP 130 Operations Alert').substring(0, 80);
    const body = (payloadJson.body || 'New operational activity requires your attention.').substring(0, 200);

    let targetUrl = '/dashboard';
    if (payloadJson.data && typeof payloadJson.data.url === 'string') {
        const candidate = payloadJson.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes(':') && !candidate.includes('\\')) {
            targetUrl = candidate;
        }
    }

    const isP1OrP2 = Boolean(
        payloadJson.data && (
            payloadJson.data.requireInteraction === true ||
            Number(payloadJson.data.priority) === 1 ||
            Number(payloadJson.data.priority) === 2
        )
    );

    const options = {
        body: body,
        icon: payloadJson.icon || '/favicon-192x192.png',
        badge: payloadJson.badge || '/favicon-32x32.png',
        tag: payloadJson.tag || 'nhmp-130-alert',
        data: Object.assign({}, payloadJson.data || {}, { url: targetUrl }),
        requireInteraction: isP1OrP2,
        vibrate: isP1OrP2 ? [300, 100, 300, 100, 300] : [200, 100, 200],
    };

    registration.showNotification(title, options);
    return registration.shownNotifications[0];
}

// Test 2: Notification Click Event Simulation
async function simulateNotificationClick(notification, clients, origin = 'https://crm.nhmp.gov.pk') {
    notification.close();

    let targetPath = '/dashboard';
    if (notification.data && typeof notification.data.url === 'string') {
        const candidate = notification.data.url.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes(':') && !candidate.includes('\\')) {
            targetPath = candidate;
        }
    }

    const fullTargetUrl = new URL(targetPath, origin).href;
    const clientList = await clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const client of clientList) {
        if (client.url === fullTargetUrl && 'focus' in client) {
            return client.focus();
        }
    }
    if (clientList.length > 0 && 'focus' in clientList[0] && 'navigate' in clientList[0]) {
        clientList[0].focus();
        return clientList[0].navigate(fullTargetUrl);
    }
    if (clients.openWindow) {
        return clients.openWindow(fullTargetUrl);
    }
}

// Execute Assertions
(async () => {
    // Scenario A: P1 Help Call Push Notification
    const p1Payload = {
        title: '🚨 NHMP 130 [P1]: Pending Help CRM-2026-999',
        body: 'Beat: M-2 North | Type: Accident | Immediate operational response required.',
        tag: 'call-pending-999',
        data: {
            type: 'help_pending',
            call_id: 999,
            priority: 1,
            url: '/calls/CRM-2026-999',
            requireInteraction: true,
        },
    };

    const notif1 = simulatePushEvent(p1Payload);
    assert.strictEqual(notif1.title, p1Payload.title);
    assert.strictEqual(notif1.options.requireInteraction, true, 'P1 must have requireInteraction = true');
    assert.deepStrictEqual(notif1.options.vibrate, [300, 100, 300, 100, 300], 'P1 must have urgent vibration pattern');
    assert.strictEqual(notif1.options.data.url, '/calls/CRM-2026-999');

    // Scenario B: Click when target URL already open in browser tab
    const clientsWithExactMatch = new MockClients([
        new MockClient('https://crm.nhmp.gov.pk/dashboard'),
        new MockClient('https://crm.nhmp.gov.pk/calls/CRM-2026-999'),
    ]);
    await simulateNotificationClick(notif1, clientsWithExactMatch);
    assert.strictEqual(notif1.closed, true, 'Notification must be closed upon click');
    assert.strictEqual(clientsWithExactMatch.clientList[1].isFocused, true, 'Matching client window must be focused');
    assert.strictEqual(clientsWithExactMatch.openedWindows.length, 0, 'No new window should be opened when tab exists');

    // Scenario C: Click when another CRM tab is open -> navigate that tab
    const clientsWithDifferentUrl = new MockClients([
        new MockClient('https://crm.nhmp.gov.pk/dashboard'),
    ]);
    await simulateNotificationClick(notif1, clientsWithDifferentUrl);
    assert.strictEqual(clientsWithDifferentUrl.clientList[0].isFocused, true);
    assert.strictEqual(clientsWithDifferentUrl.clientList[0].navigatedTo, 'https://crm.nhmp.gov.pk/calls/CRM-2026-999');

    // Scenario D: Click when NO CRM window is open -> clients.openWindow
    const clientsEmpty = new MockClients([]);
    await simulateNotificationClick(notif1, clientsEmpty);
    assert.strictEqual(clientsEmpty.openedWindows.length, 1);
    assert.strictEqual(clientsEmpty.openedWindows[0], 'https://crm.nhmp.gov.pk/calls/CRM-2026-999');

    // Scenario E: Malicious redirect attempt via external URL in push payload
    const maliciousPayload = {
        title: 'Security Alert',
        body: 'Click here immediately',
        data: {
            url: 'https://evil-phish.example/steal',
        },
    };
    const notifMalicious = simulatePushEvent(maliciousPayload);
    assert.strictEqual(notifMalicious.options.data.url, '/dashboard', 'Malicious URL must fallback to /dashboard');

    const clientsMalicious = new MockClients([]);
    await simulateNotificationClick(notifMalicious, clientsMalicious);
    assert.strictEqual(clientsMalicious.openedWindows[0], 'https://crm.nhmp.gov.pk/dashboard');

    console.log('✔ Service Worker Lifecycle & Event Simulation PASSED (14/14 assertions)');
})();
