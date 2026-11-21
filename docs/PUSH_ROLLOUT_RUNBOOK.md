# NHMP 130 Desk CRM - Production Push Notification Rollout Runbook

This runbook contains exact, copy-paste operational steps for rolling out the Unified Desk Push Notification feature to production.

> [!IMPORTANT]
> **Production Rule**: Execute these steps ONLY after explicit approval from Safdar.
> All steps are zero-downtime, fail-closed, and support immediate single-command rollback without database rewrites.

---

## 1. Container Image Build & Dependency Verification

Build the production Octane image using `Dockerfile.octane`. The build automatically runs `composer install --no-dev` against `composer.lock`, ensuring `minishlink/web-push` and `google/auth` are baked into the image.

### 1.1 Create Clean Git Archive & Build Image
```bash
# On build machine or deploy node:
TAG="phase2-push-$(git rev-parse --short HEAD)"
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

### 1.3 Export / Transfer Image & Set Override Variable
If deploying to multiple production nodes via Docker save/load:
```bash
# Save and transfer
docker save "${IMAGE_NAME}" | gzip > "/tmp/${IMAGE_NAME}.tar.gz"
# On target node:
docker load < "/tmp/${IMAGE_NAME}.tar.gz"

# In production environment or deploy shell:
export NHMP_IMAGE="${IMAGE_NAME}"
```

---

## 2. Secrets Management & VAPID Key Generation

Secrets are stored in `/var/www/html/secrets` inside the container (or mounted host directory). Permissions MUST be `0600` (read/write by owner only).

### 2.1 Set Up Placeholder Secrets (Deploy Mode)
On the production host app directory:
```bash
mkdir -p secrets
chmod 700 secrets

# Create placeholder files with mode 600 if they do not yet exist
touch secrets/vapid_private_key secrets/firebase_credentials.json
chmod 600 secrets/vapid_private_key secrets/firebase_credentials.json
```

### 2.2 Generate Production RFC 8292 VAPID Keys Inside Container
Run the artisan command inside the running app container. It creates `secrets/vapid_private_key` (mode 0600) and prints ONLY the public key to console:
```bash
docker exec -it 130-app-octane-1-1 php artisan push:generate-vapid
```
**Expected Output**:
```text
✔ VAPID keypair successfully generated.

Public Key (Safe to place in .env as VAPID_PUBLIC_KEY):
BN2k...<65-byte base64url uncompressed public key>...

Private key written securely to [/var/www/html/secrets/vapid_private_key] (mode 0600).
The private key was NOT printed to console to prevent shoulder-surfing and log leakage.
```

### 2.3 Update `.env` with VAPID Public Key
Append the printed public key to `.env` on both app nodes:
```bash
# Edit .env or run:
echo 'VAPID_PUBLIC_KEY="<public-key-printed-above>"' >> .env
```

---

## 3. Database Migrations & MySQL 8.4 Group Replication Analysis

All push migrations are 100% additive, idempotent, and guarded by `Schema::hasTable` checks.

### 3.1 Verify Migration SQL with Dry-Run
Execute pretend migration to inspect the exact DDL:
```bash
docker exec -it 130-app-octane-1-1 php artisan migrate --pretend --force
```

**Captured Pretend Output**:
```text
  2026_11_21_010000_create_push_subscriptions_table ..........................  
  ⇂ select exists (select 1 from information_schema.tables where table_schema = schema() and table_name = 'push_subscriptions' and table_type in ('BASE TABLE', 'SYSTEM VERSIONED')) as `exists`  
  ⇂ create table `push_subscriptions` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `office_id` bigint unsigned null, `device_type` varchar(32) not null default 'web_crm', `provider` varchar(32) not null default 'webpush', `token_hash` varchar(64) not null, `endpoint` text not null, `public_key` text null, `auth_token` text null, `user_agent` varchar(255) null, `is_active` tinyint(1) not null default '1', `last_used_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci'  
  ⇂ alter table `push_subscriptions` add constraint `push_subscriptions_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade  
  ⇂ alter table `push_subscriptions` add constraint `push_subscriptions_office_id_foreign` foreign key (`office_id`) references `offices` (`id`) on delete set null  
  ⇂ alter table `push_subscriptions` add index `push_subscriptions_user_id_is_active_index`(`user_id`, `is_active`)  
  ⇂ alter table `push_subscriptions` add index `push_subscriptions_office_id_is_active_index`(`office_id`, `is_active`)  
  ⇂ alter table `push_subscriptions` add index `push_subscriptions_provider_is_active_index`(`provider`, `is_active`)  
  ⇂ alter table `push_subscriptions` add unique `push_subscriptions_token_hash_unique`(`token_hash`)  
  2026_11_21_010100_create_failed_jobs_table .................................  
  ⇂ select exists (select 1 from information_schema.tables where table_schema = schema() and table_name = 'failed_jobs' and table_type in ('BASE TABLE', 'SYSTEM VERSIONED')) as `exists`  
  ⇂ create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` text not null, `queue` text not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci'  
  ⇂ alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`)
```

### 3.2 MySQL 8.4 Group Replication (GR) Safety Statement
- **Explicit Primary Keys**: Every created table defines `id bigint unsigned not null auto_increment primary key`. MySQL GR strictly requires primary keys on all tables.
- **Storage Engine**: Tables use InnoDB (`default character set utf8mb4`). GR forbids non-transactional engines (MyISAM).
- **Zero Existing Table DDL**: No existing production tables (`calls`, `users`, `offices`, `support_requests`) are altered, locked, or modified. Zero schema locks on core business entities.

### 3.3 Apply Migrations
```bash
docker exec -it 130-app-octane-1-1 php artisan migrate --force
```

---

## 4. Start Push Worker & Verify Healthcheck

The push queue worker processes jobs on the dedicated `push` queue and emits a heartbeat key to Redis every 60 seconds (`push:worker:heartbeat`, TTL 180s).

### 4.1 Launch Worker Container
```bash
docker compose -f docker-compose.yml -f docker-compose.octane.yml up -d push-worker
```

### 4.2 Validate Worker Health Before Enabling Feature
Run the healthcheck mode of `push:status`:
```bash
docker exec -it 130-app-octane-1-1 php artisan push:status --health
```
**Expected Output**:
```text
OK: Push worker active (heartbeat 12s ago, queue depth: 0, failed: 0)
```
*(Exit code 0 confirms Redis connectivity, fresh heartbeat <= 180s, and healthy queue depth).*

---

## 5. Phased Rollout Procedure (Dark -> Canary -> Full)

### Phase A: Dark Launch (Default)
In `.env` on all nodes:
```ini
PUSH_ENABLED=false
```
- Background worker runs and monitors queue.
- Subscriptions can be registered or pruned.
- Zero outbound push dispatches are triggered (`PushDispatcher::isEnabled()` returns false and short-circuits instantly).

### Phase B: Canary Launch (Single Supervisor + Single Sector User)
Test with targeted accounts before enabling across the whole department:
1. Temporarily enable push for the canary test:
   ```bash
   sed -i 's/^PUSH_ENABLED=false/PUSH_ENABLED=true/' .env
   docker exec -it 130-app-octane-1-1 php artisan config:clear
   docker exec -it 130-app-octane-2-1 php artisan config:clear
   ```
2. Have Canary Supervisor (User ID e.g. `12`) and Canary Sector Operator (User ID e.g. `34`) click the Bell icon in Desk CRM to subscribe.
3. Verify subscriptions in DB:
   ```bash
   docker exec -it 130-app-octane-1-1 php artisan tinker --execute="echo App\Models\PushSubscription::active()->count();"
   ```
4. Send targeted test notifications:
   ```bash
   docker exec -it 130-app-octane-1-1 php artisan push:test --user=12
   docker exec -it 130-app-octane-1-1 php artisan push:test --user=34
   ```
5. Confirm both users receive physical desktop alert banners.

### Phase C: Full Enablement
Keep `PUSH_ENABLED=true` active in `.env`.
All operations desks (Sector, Beat, Supervisor) receive real-time push alerts for P1 calls, advisory approvals, and support tickets.

### Rollback Procedure (Single Command)
If any anomaly, browser issue, or operational defect is detected:
```bash
# 1. Disable push flag in .env immediately:
sed -i 's/^PUSH_ENABLED=true/PUSH_ENABLED=false/' .env

# 2. Clear cached config on both Octane app nodes:
docker exec -it 130-app-octane-1-1 php artisan config:clear
docker exec -it 130-app-octane-2-1 php artisan config:clear

# 3. Stop the push worker (optional, worker is harmless when queue is empty):
docker compose -f docker-compose.yml -f docker-compose.octane.yml stop push-worker
```
> [!NOTE]
> Database migrations (`push_subscriptions`, `failed_jobs`) remain in place. They are purely additive and do not affect any existing CRM functionality.

---

## 6. Nginx `/sw.js` Zero-Downtime Cache Configuration

Browsers must never cache `/sw.js` from stale disk storage. The Nginx server configuration routes `/sw.js` with `no-cache, no-store, must-revalidate`.

### 6.1 Nginx Location Block (Mounted Config)
Ensure the following block is present in `/etc/nginx/conf.d/default.conf` or sites-enabled:
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
# Node 1:
docker exec -it 130-web-1 nginx -t && docker exec -it 130-web-1 nginx -s reload

# Node 2:
docker exec -it 130-web-2 nginx -t && docker exec -it 130-web-2 nginx -s reload
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
   `/var/www/html/secrets/firebase_credentials.json`
2. Set strict permissions:
   ```bash
   chmod 600 secrets/firebase_credentials.json
   chown www-data:www-data secrets/firebase_credentials.json
   ```
3. Update `.env`:
   ```ini
   FIREBASE_CREDENTIALS="secrets/firebase_credentials.json"
   ```
4. Verify FCM initialization via health check:
   ```bash
   docker exec -it 130-app-octane-1-1 php artisan push:status
   ```
   *Look for: `Firebase Cloud Messaging: READY (Credentials present, project: <id>)*.
