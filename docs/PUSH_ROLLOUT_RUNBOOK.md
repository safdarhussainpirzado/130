# NHMP 130 Desk CRM - Production Push Notification Rollout Runbook

This runbook contains exact, verified operational steps for rolling out the Unified Desk Push Notification feature to production.

> [!IMPORTANT]
> **Production Rule**: Execute these steps ONLY after explicit approval from Safdar.
> Deployments and production operations are executed exclusively by Claude.
> All steps are zero-downtime, fail-closed, and support immediate single-command rollback without database rewrites.
> Every command and path in this runbook is derived directly from repository files (`haproxy/haproxy.cfg`, `docker-compose.octane.yml`, `.rr.prod.yaml`, `config/services.php`, `routes/web.php`) or confirmed production facts.

---

## Production Topology & Architecture Facts

Production uses a high-availability multi-container architecture orchestrated via Docker Compose:
- **Routing & Load Balancer**: Container `130-haproxy-1`
  - Runtime Socket: `/tmp/haproxy.sock` (inside `130-haproxy-1` container, per `haproxy/haproxy.cfg` line 4: `stats socket /tmp/haproxy.sock mode 660 level admin`).
  - Backend Name: `app_nodes` (per `haproxy/haproxy.cfg` line 48).
  - Server Names: `web-octane-1` and `web-octane-2` (per `haproxy/haproxy.cfg` lines 56-57).
  - Runtime Command Format: `echo "..." | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock`.
  - State Inspection: `echo "show servers state" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock | awk '/web-octane/{print $4,$6,$7}'` (expect `2 0` = up, no admin state).
- **Web Nodes (Nginx)**: Containers `130-web-octane-1-1`, `130-web-octane-2-1`.
- **App Nodes (Laravel Octane / RoadRunner)**: Containers `130-app-octane-1-1`, `130-app-octane-2-1`.
- **Background Workers**: Container `130-scheduler-1`, and the new worker container `130-push-worker-1` (service name: `push-worker`).
- **Health Check Endpoint**: `/up` (verified from `haproxy/haproxy.cfg` line 50: `http-check send meth GET uri /up ver HTTP/1.1 hdr Host localhost`, `docker-compose.octane.yml` line 53: `exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);`, and defined in `routes/web.php` line 29).
- **Database & Cache**: MySQL 8.4 Group Replication (port 3309), Redis (queue & cache).
- **Environment Configuration**: Production does **NOT** use a `.env` file and does **NOT** deploy with `docker-compose.octane.yml` from the repository. All environment variables, image tags, and secret paths live in the host compose overlay file:
  `/root/octane.rcNN.yml` (e.g. `/root/octane.rc26.yml`) combined with base `/home/mrpirzado/projects/130/docker-compose.yml`.
- **Queue & Tables**: `QUEUE_CONNECTION=redis` is already configured in production; `jobs` and `failed_jobs` tables already exist.

---

## 1. Container Image Build & Dependency Verification

Build the production Octane image using `Dockerfile.octane`. The build automatically runs `composer install --no-dev` against `composer.lock`, ensuring `minishlink/web-push` and `google/auth` are baked into the image.

### 1.1 Create Clean Git Archive & Build Image
```bash
# On build machine or deploy node:
TAG="phase2-rc26"
IMAGE_NAME="130-app-octane:${TAG}"

echo "Building release image: ${IMAGE_NAME}..."
git archive HEAD | docker build -t "${IMAGE_NAME}" -f Dockerfile.octane -
```

### 1.2 Verify Embedded Dependencies Inside Built Image
Run a disposable container check to confirm both classes exist:
```bash
docker run --rm "${IMAGE_NAME}" php -r '
    $w = class_exists("Minishlink\\WebPush\\WebPush");
    $g = class_exists("Google\\Auth\\Credentials\\ServiceAccountCredentials");
    echo "WebPush: " . ($w ? "FOUND\n" : "MISSING\n");
    echo "GoogleAuth: " . ($g ? "FOUND\n" : "MISSING\n");
    exit(($w && $g) ? 0 : 1);
'
```
**Expected Output**:
```text
WebPush: FOUND
GoogleAuth: FOUND
```

### 1.3 Export / Transfer Image to Production Host
```bash
# Save and compress image
docker save "${IMAGE_NAME}" | gzip > "/tmp/${IMAGE_NAME}.tar.gz"

# Transfer to production host (via rsync/scp):
rsync -avzP "/tmp/${IMAGE_NAME}.tar.gz" root@10.1.2.62:/tmp/

# On production host:
docker load < "/tmp/${IMAGE_NAME}.tar.gz"
```

---

## 2. Secrets Management & VAPID Key Generation

Secrets are stored in `/root/secrets` on the host (VERIFY ON PROD: host secret directory per production setup) and mounted into `/run/secrets/<name>` inside containers. Permissions MUST be `0600` (read/write by owner only).

### 2.1 Set Up Placeholder Secrets on Production Host
On the production host:
```bash
mkdir -p /root/secrets
chmod 700 /root/secrets

# Create placeholder files with mode 600 if they do not yet exist
touch /root/secrets/vapid_private_key /root/secrets/firebase_credentials.json
chmod 600 /root/secrets/vapid_private_key /root/secrets/firebase_credentials.json
```

### 2.2 Generate Production RFC 8292 VAPID Keys Inside Running App Container
Run the artisan command inside the running app container. It creates `secrets/vapid_private_key` (mode 0600) and prints ONLY the public key to console:
```bash
docker exec -i 130-app-octane-1-1 php artisan push:generate-vapid
```
**Expected Output**:
```text
✔ VAPID keypair successfully generated.

Public Key (Safe to place in compose overlay as VAPID_PUBLIC_KEY):
BN2k...<65-byte base64url uncompressed public key>...

Private key written securely to [/var/www/html/secrets/vapid_private_key] (mode 0600).
The private key was NOT printed to console to prevent shoulder-surfing and log leakage.
```

### 2.3 Add VAPID & Push Variables to Production Overlay (`/root/octane.rc26.yml`)
In `/root/octane.rc26.yml`, define the secrets and push settings under `environment:` using the exact environment variable names from `config/services.php` and `docker-compose.octane.yml`:

```yaml
secrets:
  # Host secret file paths (VERIFY ON PROD: adjust path if secrets live in /root/secrets/ or ./secrets/)
  vapid_private_key:
    file: /root/secrets/vapid_private_key
  firebase_credentials:
    file: /root/secrets/firebase_credentials.json

services:
  app-octane-1:
    image: 130-app-octane:phase2-rc26
    secrets:
      - vapid_private_key
      - firebase_credentials
    environment:
      PUSH_ENABLED: "false"           # Dark launch by default (short-circuits push dispatcher)
      PUSH_FCM_ENABLED: "false"       # Enable when Firebase credentials JSON is provided
      VAPID_PUBLIC_KEY: "<public-key-generated-above>"
      VAPID_PRIVATE_KEY_FILE: "/run/secrets/vapid_private_key"
      VAPID_SUBJECT: "mailto:admin@130.nhmp.gov.pk"
      FIREBASE_CREDENTIALS_FILE: "/run/secrets/firebase_credentials"
      FIREBASE_PROJECT_ID: "nhmp-130-crm"

  app-octane-2:
    image: 130-app-octane:phase2-rc26
    secrets:
      - vapid_private_key
      - firebase_credentials
    environment:
      PUSH_ENABLED: "false"
      PUSH_FCM_ENABLED: "false"
      VAPID_PUBLIC_KEY: "<public-key-generated-above>"
      VAPID_SUBJECT: "mailto:admin@130.nhmp.gov.pk"
      VAPID_PRIVATE_KEY_FILE: "/run/secrets/vapid_private_key"
      FIREBASE_CREDENTIALS_FILE: "/run/secrets/firebase_credentials"
      FIREBASE_PROJECT_ID: "nhmp-130-crm"

  push-worker:
    image: 130-app-octane:phase2-rc26
    restart: unless-stopped
    command: ["php", "artisan", "queue:work", "redis", "--queue=push", "--tries=3", "--timeout=30", "--max-time=3600"]
    secrets:
      - vapid_private_key
      - firebase_credentials
    environment:
      PUSH_ENABLED: "false"
      PUSH_FCM_ENABLED: "false"
      VAPID_PUBLIC_KEY: "<public-key-generated-above>"
      VAPID_SUBJECT: "mailto:admin@130.nhmp.gov.pk"
      VAPID_PRIVATE_KEY_FILE: "/run/secrets/vapid_private_key"
      FIREBASE_CREDENTIALS_FILE: "/run/secrets/firebase_credentials"
      FIREBASE_PROJECT_ID: "nhmp-130-crm"
```

---

## 3. Roll App Nodes Behind HAProxy Drain & Run Additive Migrations

### 3.1 Zero-Downtime Rolling Update of App Nodes
Roll one node at a time using HAProxy runtime socket API on `130-haproxy-1` (`/tmp/haproxy.sock`):

```bash
# ==============================================================================
# ROLL NODE 1
# ==============================================================================

# 1. Drain node 1 in HAProxy (backend: app_nodes, server: web-octane-1)
echo "set server app_nodes/web-octane-1 state drain" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock

# 2. Wait ~20 s after drain for existing in-flight connections to complete
sleep 20

# 3. Recreate app-octane-1 with new image and overlay
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d --no-deps app-octane-1

# 4. Wait ~8 s after the change for container initialization
sleep 8

# 5. Verify Octane health check (/up returns 200, matching haproxy.cfg line 50)
docker exec -i 130-app-octane-1-1 php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"

# 6. Re-enable node 1 in HAProxy
echo "set server app_nodes/web-octane-1 state ready" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock

# 7. Wait ~20 s
sleep 20

# 8. Verify HAProxy server state (expect "2 0" = up, no admin state)
echo "show servers state" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock | awk '/web-octane/{print $4,$6,$7}'

# ==============================================================================
# ROLL NODE 2
# ==============================================================================

# 9. Drain node 2 in HAProxy (backend: app_nodes, server: web-octane-2)
echo "set server app_nodes/web-octane-2 state drain" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock

# 10. Wait ~20 s after drain
sleep 20

# 11. Recreate app-octane-2 with new image and overlay
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d --no-deps app-octane-2

# 12. Wait ~8 s after the change
sleep 8

# 13. Verify Octane health check
docker exec -i 130-app-octane-2-1 php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"

# 14. Re-enable node 2 in HAProxy
echo "set server app_nodes/web-octane-2 state ready" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock

# 15. Wait ~20 s
sleep 20

# 16. Verify HAProxy server state (expect "2 0" for both servers)
echo "show servers state" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock | awk '/web-octane/{print $4,$6,$7}'
```

### 3.2 Execute Additive Database Migrations
> [!IMPORTANT]
> `php artisan migrate --force` must run **ONLY AFTER** the new image is running on the app node.
> The old image does not contain the migration files.

On production, `failed_jobs` already exists and is guarded by `Schema::hasTable` (no-op). Only `push_subscriptions` will be created:
```bash
# Verify pretend migration
docker exec -i 130-app-octane-1-1 php artisan migrate --pretend --force

# Apply migration
docker exec -i 130-app-octane-1-1 php artisan migrate --force
```

**MySQL 8.4 Group Replication (GR) Safety Statement**:
- `push_subscriptions` defines an explicit `id bigint unsigned not null auto_increment primary key`.
- Storage engine is InnoDB (`utf8mb4`).
- Zero existing tables are modified or locked.

### 3.3 Hot-Reload vs Image Roll (Operational Note)
RoadRunner runs with `opcache.validate_timestamps=0` in production.
- **For an Image Roll (Full Deploy)**: Recreate the container with `docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d --no-deps app-octane-N` (as shown in Step 3.1).
- **For Hot-Reload (Replacing code in a running container without recreate)**:
  ```bash
  docker exec -i 130-app-octane-1-1 php artisan route:cache
  docker exec -i 130-app-octane-1-1 php artisan view:cache
  docker exec -i 130-app-octane-1-1 rr reset -c /var/www/html/.rr.prod.yaml
  ```
  (and repeat on node 2).

---

## 4. Start Push Worker & Verify Healthcheck

The push queue worker processes jobs on the dedicated `push` queue. Because `push-worker` is a **NEW** service, no HAProxy drain is required to launch it.

### 4.1 Launch Push Worker Container
```bash
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d push-worker
```

### 4.2 Validate Worker Health Before Enabling Feature
Run the healthcheck mode of `push:status`:
```bash
docker exec -i 130-app-octane-1-1 php artisan push:status --health
```
**Expected Output**:
```text
OK: Push worker active (heartbeat 12s ago, queue depth: 0, failed: 0)
```
*(Exit code 0 confirms Redis connectivity, fresh worker heartbeat <= 180s, and healthy queue depth).*

---

## 5. Phased Rollout Procedure (Dark -> Canary -> Full)

### Phase A: Dark Launch (Default)
In `/root/octane.rc26.yml`:
```yaml
PUSH_ENABLED: "false"
```
- Push worker runs, monitors queue, and writes heartbeat to Redis.
- Desk users see the bell icon and can register subscriptions in the database.
- Zero outbound push notifications are dispatched (`PushDispatcher::isEnabled()` returns false and short-circuits instantly with zero overhead).

### Phase B: Canary Launch (Single Supervisor + Single Sector User)
Test with targeted accounts before enabling across the whole department:
1. In `/root/octane.rc26.yml`, set `PUSH_ENABLED: "true"`.
2. Roll app nodes one at a time behind HAProxy drain (as shown in Step 3.1) and recreate `push-worker`.
3. Have Canary Supervisor (User ID e.g. `12`) and Canary Sector Operator (User ID e.g. `34`) click the Bell icon in Desk CRM to subscribe.
4. Verify subscriptions in DB:
   ```bash
   docker exec -i 130-app-octane-1-1 php artisan tinker --execute="echo App\Models\PushSubscription::active()->count();"
   ```
5. Send targeted test notifications:
   ```bash
   docker exec -i 130-app-octane-1-1 php artisan push:test --user=12
   docker exec -i 130-app-octane-1-1 php artisan push:test --user=34
   ```
6. Confirm both users receive physical desktop alert banners in their browser.

### Phase C: Full Enablement
Keep `PUSH_ENABLED: "true"` active in `/root/octane.rc26.yml`.
All operations desks (Sector, Beat, Supervisor) receive real-time push alerts for P1 calls, advisory approvals, and support tickets.

### Rollback Procedure (Zero Database Rewrites)
If any anomaly, browser issue, or operational defect is detected:
```bash
# 1. Update /root/octane.rc26.yml:
#    Change PUSH_ENABLED: "true" -> PUSH_ENABLED: "false"

# 2. Roll app nodes one at a time behind HAProxy drain to apply PUSH_ENABLED: "false":

# Node 1:
echo "set server app_nodes/web-octane-1 state drain" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock
sleep 20
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d --no-deps app-octane-1
sleep 8
docker exec -i 130-app-octane-1-1 php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"
echo "set server app_nodes/web-octane-1 state ready" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock
sleep 20

# Node 2:
echo "set server app_nodes/web-octane-2 state drain" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock
sleep 20
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml up -d --no-deps app-octane-2
sleep 8
docker exec -i 130-app-octane-2-1 php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"
echo "set server app_nodes/web-octane-2 state ready" | docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock
sleep 20

# 3. Stop the push worker:
docker compose -f /home/mrpirzado/projects/130/docker-compose.yml -f /root/octane.rc26.yml stop push-worker
```
> [!NOTE]
> Database migrations (`push_subscriptions`, `failed_jobs`) remain in place. They are purely additive and do not affect any existing CRM functionality.

---

## 6. Nginx `/sw.js` Zero-Downtime Cache Configuration

Browsers must never cache `/sw.js` from stale disk storage. The Nginx server configuration routes `/sw.js` with `no-cache, no-store, must-revalidate`.

### 6.1 Nginx Location Block (Mounted Config)
Ensure the following block is present in the mounted nginx config on the host (`/etc/nginx/conf.d/default.conf` or sites-enabled):
```nginx
location = /sw.js {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Pragma "no-cache";
    expires 0;
    try_files $uri /index.php?$query_string;
}
```

### 6.2 Zero-Downtime Reload Across Web Nodes
Apply the configuration to each web node one at a time using graceful reload:
```bash
# Web Node 1:
docker exec -i 130-web-octane-1-1 nginx -t && docker exec -i 130-web-octane-1-1 nginx -s reload

# Web Node 2:
docker exec -i 130-web-octane-2-1 nginx -t && docker exec -i 130-web-octane-2-1 nginx -s reload
```

---

## 7. Firebase Cloud Messaging (FCM) Setup Procedure

Web Push works independently with VAPID without Firebase. If Safdar provisions Firebase for Android / Chrome FCM integration:

### 7.1 Service Account Creation Steps for Safdar:
1. Log in to [Google Cloud Console](https://console.cloud.google.com/) or [Firebase Console](https://console.firebase.google.com/).
2. Select or create project `nhmp-130-crm`.
3. Navigate to **IAM & Admin** -> **Service Accounts**.
4. Click **Create Service Account**:
   - Name: `nhmp-130-push-dispatcher`
   - Role: **Firebase Cloud Messaging API Admin** (`roles/firebase.sdkAdminServiceAgent` or `Firebase Cloud Messaging Admin`).
   - Grant NO other permissions (least privilege principle).
5. Under **Keys**, click **Add Key** -> **Create new key** -> select **JSON**.
6. Download the key file.

### 7.2 Secure Key Placement
1. Copy the downloaded JSON file to production app nodes at:
   `/root/secrets/firebase_credentials.json` (VERIFY ON PROD: host secret path)
2. Set strict permissions:
   ```bash
   chmod 600 /root/secrets/firebase_credentials.json
   ```
3. Update `/root/octane.rc26.yml`:
   ```yaml
   FIREBASE_CREDENTIALS_FILE: "/run/secrets/firebase_credentials"
   FIREBASE_PROJECT_ID: "nhmp-130-crm"
   PUSH_FCM_ENABLED: "true"
   ```
4. Verify FCM initialization via status check:
   ```bash
   docker exec -i 130-app-octane-1-1 php artisan push:status
   ```
   *Look for: `Firebase Cloud Messaging: READY (Credentials present, project: <id>)*.
