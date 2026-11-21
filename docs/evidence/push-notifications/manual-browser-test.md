# NHMP 130 Desk CRM - Push Notifications Manual Browser Verification Runbook

## Executive Summary & Test Environment Context

In accordance with PUSH-4 requirements, this document outlines the exact 10-minute manual verification procedure for desktop web push notifications in staging and production environments.

### Why Automated Headless Chromium Cannot Fully Complete Push Display in CI
Automated execution of `PushManager.subscribe()` in standard headless Chromium within containerized / WSL environments encounters two environmental barriers:
1. **Push Service Network Requirement**: W3C Push API implementations in Chrome/Chromium delegate message delivery to Google Firebase Cloud Messaging endpoints (`fcm.googleapis.com`). In private sandboxes or offline test containers without public Google Push service access, `pushManager.subscribe()` fails closed (`NotAllowedError: Push service error`).
2. **OS Notification Daemon**: Displaying physical desktop notification banners (`registration.showNotification()`) requires a native OS desktop shell (D-Bus notification daemon on Linux, Action Center on Windows, Notification Center on macOS), which is absent in headless CI runners.

All underlying business logic, encryption, payload sanitization, same-origin URL validation, and Service Worker lifecycle handlers are covered by automated unit/integration tests:
- `tests/Unit/Notifications/WebPushRoundTripTest.php` (RFC 8291 AES-GCM encryption)
- `tests/sw_url_fallback_test.cjs` (Service Worker same-origin URL enforcement)
- `tests/sw_service_worker_lifecycle_test.cjs` (Service Worker push & click lifecycle events)

---

## 10-Minute Manual Verification Procedure

### Prerequisites
- Desktop Browser: Google Chrome, Microsoft Edge, Mozilla Firefox, or Brave (recent version).
- CRM Environment: Staging or production deployment with valid HTTPS certificate.
- Desk User Account: Operator or Supervisor user credentials.
- Terminal Access: SSH access to app container for artisan commands.

---

### Step 1: Pre-flight Verification (1 minute)
On the server/container terminal, run the read-only rollout checklist:
```bash
./scripts/deploy/push_rollout_checklist.sh
php artisan push:status --health
```
**Expected Outcome**:
- `Minishlink\WebPush\WebPush` is loaded.
- `secrets/vapid_private_key` exists (mode 600).
- `push:status --health` prints `OK: Push worker active`.

---

### Step 2: Open Desk CRM & Inspect Service Worker (1 minute)
1. Open your browser and navigate to `https://<crm-domain>/dashboard`.
2. Open Developer Tools (`F12` or `Ctrl+Shift+I` / `Cmd+Option+I`).
3. Navigate to **Application** -> **Service Workers** (in Firefox: `about:debugging` -> This Firefox).
4. Verify that `/sw.js` is registered:
   - Source: `https://<crm-domain>/sw.js`
   - Status: `#... activated and is running`
   - Update on reload: Unchecked in normal testing.

---

### Step 3: Grant Permission & Subscribe (1 minute)
1. Locate the Notification Bell icon in the Desk CRM navigation header or sidebar.
2. Click the Bell icon.
3. The browser will present the native permission prompt:
   > *"https://<crm-domain> wants to: Show notifications"*
4. Click **Allow**.
5. Observe the UI:
   - The Bell icon highlights as active (color changes, badge/tooltip indicates *"Desk notifications enabled"*).
   - In the DevTools **Network** tab, inspect the request:
     - Endpoint: `POST /web-push/subscribe`
     - Status: `200 OK`
     - Response: `{"success": true}`

---

### Step 4: Verify Database Storage & Encryption (1 minute)
In your database console or via `tinker`:
```sql
SELECT id, user_id, provider, token_hash, is_active, public_key, auth_token, created_at
FROM push_subscriptions
ORDER BY id DESC LIMIT 1;
```
**Expected Outcome**:
- `user_id` matches your logged-in user ID.
- `provider` is `'webpush'`.
- `is_active` is `1`.
- `public_key` and `auth_token` contain encrypted JSON blobs (Laravel ciphertext starting with `{"iv":"...`), verifying they are NOT stored in plaintext.

---

### Step 5: Trigger Test Push Notification (2 minutes)
On the app node terminal, send a harmless test push notification targeting your user ID:
```bash
php artisan push:test --user=<your-user-id>
```
**Expected Terminal Output**:
```text
✔ Queued test push notification for <Your Name> (User #<id>) on queue [push].
Targeting 1 active subscription(s) across registered browser/FCM endpoints.
Ensure your push-worker is running to process the queued job: php artisan queue:work --queue=push
```

**Expected Browser / OS Outcome**:
Within 1–3 seconds, a native OS notification banner appears in the desktop notification tray:
- **Title**: `🚨 NHMP 130: Test Notification`
- **Body**: `Desk push notifications are active and functioning correctly.`
- **Icon**: NHMP official emblem (`/favicon-192x192.png`).
- **Sound / Vibration**: Standard OS desktop notification chime.

---

### Step 6: Test Notification Click Navigation (1 minute)
1. Click the notification banner.
2. **Expected Behavior**:
   - The notification banner immediately closes.
   - The browser window is brought to the foreground (focused).
   - The page navigates to `/dashboard` (or focuses the already open `/dashboard` tab without reloading unnecessary resources).

---

### Step 7: Test High-Priority Operational Call Dispatch (2 minutes)
1. On the CRM, create a new Help Call with `priority = 1` (P1 Critical) assigned to the operator's beat.
2. Save the call.
3. Observe the desktop notification banner:
   - **Title**: `🚨 NHMP 130 [P1 Critical]: Pending Help CRM-2026-XXXXX`
   - **Body**: Contains Beat name and Type.
   - **Behavior**: Persistent banner (`requireInteraction: true`), does NOT auto-dismiss until clicked or manually closed by operator.
   - **Click Action**: Clicking the banner navigates directly to the call details screen `/calls/CRM-2026-XXXXX`.

---

### Step 8: Unsubscribe & Cleanup (1 minute)
1. Click the Notification Bell icon in the CRM navigation to toggle notifications OFF.
2. In the DevTools **Network** tab:
   - Endpoint: `POST /web-push/unsubscribe`
   - Status: `200 OK`
3. Verify in DB:
   - Subscription record is updated to `is_active = 0` or deleted.
4. Run `php artisan push:test --user=<your-user-id>` again:
   - Terminal notes 0 active subscriptions.
   - No browser notification is received.

---

## Troubleshooting Checklist

| Issue | Root Cause | Solution |
| :--- | :--- | :--- |
| **No permission prompt appears on bell click** | Browser permissions set to "Block" previously | Click the tune/padlock icon in the URL bar, set Notifications to "Allow" or "Ask", reload. |
| **Notification prompt allowed, but no banner displays** | OS Focus Assist / Do Not Disturb enabled | On Windows: turn off Focus Assist in Action Center. On macOS: disable Do Not Disturb in Control Center. |
| **`POST /web-push/subscribe` returns 419 or 401** | Session expired or CSRF token mismatch | Refresh the page and re-authenticate. |
| **Test command queues job but banner never arrives** | `push-worker` container not running or wrong queue | Run `docker compose -f docker-compose.octane.yml ps push-worker` or check `Redis::llen('queues:push')`. |
| **Notification arrives but click does nothing** | Browser blocking window focus / uncontrolled SW | Ensure `/sw.js` has `clients.claim()` and page was reloaded after SW update. |
