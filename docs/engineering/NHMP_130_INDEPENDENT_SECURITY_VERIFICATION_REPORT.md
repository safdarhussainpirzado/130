# NHMP 130 Helpline — Independent Verification of Adversarial Security Results & Evidence-Driven Audit Report

**Document ID:** `NHMP-130-SEC-IND-VERIFY-2026-V1`  
**Classification:** Official Government Cybersecurity Independent Verification & Validation (IV&V) Report  
**Operational Target:** National Highways & Motorway Police (NHMP) 130 Emergency Helpline  
**Target Environment:** Local Staging Environment (`DESKTOP-BU2GH1K` / Docker Compose Clustered Stack)  
**Assessing Authority:** Independent Cybersecurity Verification Body (MIND A Adversarial / MIND B Architecture)  
**Standard References:** NIST CSF 2.0, NIST SP 800-53 Rev. 5, NIST SP 800-115, NIST SP 800-38D, NIST SP 800-207 (Zero Trust), OWASP ASVS v4.0.3, OWASP WSTG v4.2  
**Audit Execution Date:** November 05, 2026  
**Status:** **PROVISIONALLY ACCEPTED WITH FINDINGS — NOT CERTIFIED PRODUCTION-READY**

---

## 1. Executive Summary & Verification Methodology

A secondary, evidence-driven, independent verification was performed on the previously reported results:
- **Adversarial Matrix Suite (S01–S26):** Reported 26/26 PASS.
- **Security & Cryptographic Gate Suite:** Reported 11/11 PASS.
- **Integration & Penetration Test Suite:** Reported 35/35 PASS.

### Empirical Verdict
The automated test passes are **provisionally accepted as unit/integration execution results only**. They **DO NOT** constitute proof of security effectiveness or production readiness. 

Our second-stage investigation identified **5 Critical & High Architectural Deficiencies**, **12 Static/Weak Test Assertions (tests passing by source string checks without exercising controls)**, and **Key Isolation Gaps** that must be remediated prior to national production certification.

### Verification Classification Status
In strict compliance with the Final Acceptance Rule, all controls are categorized as follows:
- **Independently Verified (True Resilience Proven):** 8 Scenarios (S02, S03, S04, S11, S12, S13, S23, S24).
- **Partially Verified (Control Exists but Has Bypass / Edge Limitations):** 7 Scenarios (S01, S06, S09, S14, S17, S18, S25).
- **Weak / False-Positive Prone (Merely Checks Existence / Source Strings):** 9 Scenarios (S05, S07, S08, S10, S16, S19, S20, S21, S22).
- **Unverified in Active Runtime (Test Evaluated Offline / Inert Code):** 2 Scenarios (S15 RoadRunner Worker, S26 Monitoring Failure Alerting).

---

## 2. Environment & Container Identity Baseline (Read-Only Inspection)

Inspection commenced with strict non-intrusive, read-only interrogation to definitively separate the staging target from national production:

| Parameter | Staging Inspection Value | Production Safeguard Status |
| :--- | :--- | :--- |
| **Host System** | `DESKTOP-BU2GH1K` (Linux 6.6.137+ / WSL2 x86_64) | Isolated local staging workstation |
| **Local Interfaces** | `eth0: 192.168.218.178/20`, `lo: 127.0.0.1`, `10.255.255.254` | Production IP (`100.124.232.124`) strictly absent |
| **Application Node 1** | Container ID: `2d4cca69ff93` (`130-app-1-1`) | PHP-FPM 8.3.36, Non-root `www-data` (UID 82) |
| **Application Node 2** | Container ID: `03b6659bea2f` (`130-app-2-1`) | PHP-FPM 8.3.36, Non-root `www-data` (UID 82) |
| **Database Primary** | Container ID: `11218edf8363` (`130-db-1-1`) | MySQL 8.4 LTS, Port 3306 bound to `127.0.0.1:3309` |
| **Redis Cache/Queue** | Container ID: `ce1578e17b66` (`130-redis-1`) | Redis 8.6, `--requirepass` enforced, `127.0.0.1:6379` |
| **Edge Reverse Proxy** | Container ID: `66e49582a126` (`130-haproxy-1`) | HAProxy 3.3.8, Ports 80, 443, 8404 (stats) |

**Verification Rule Confirmed:** No production servers were contacted; all commands executed locally against isolated containers with synthetic data.

---

## 3. Cryptographic Key Custody & Exposure Assessment (Priority 0)

An exhaustive investigation was conducted into the reported key sync operations:
```bash
docker cp storage/keys 130-app-1-1:/var/www/html/storage/
docker cp storage/keys 130-app-2-1:/var/www/html/storage/
```

### 3.1 Material Copied vs. Material Isolated
1. **Public Keys Copied:**
   - File: `/var/www/html/storage/keys/backup_public.pem`
   - Algorithm: RSA 4096-bit Public Key (PEM format, 800 bytes).
   - Permission: Mode `0644`, owner `1000:1000`, readable by `www-data` (UID 82).
   - Purpose: Asymmetric encryption of offline database backup dumps (`scripts/backup_asymmetric.sh`).
2. **Private Key Status (Verified Offline):**
   - File: `backup_private.key` / `backup_private.pem`.
   - Result of `find /home/mrpirzado -name "backup_private*"`: **Exit code 1 / Zero files found**.
   - Result inside `130-app-1-1` & `130-app-2-1`: **Zero private backup keys present**.
   - **Conclusion:** An application-layer compromise of `app-1` or `app-2` **CANNOT** decrypt backup archives. The private decryption key is strictly offline.

### 3.2 Secrets Mount Analysis (`/run/secrets/`)
Direct inspection of container mounts revealed:
```text
/home/mrpirzado/projects/130/docker/secrets/app_key.txt           -> /run/secrets/app_key
/home/mrpirzado/projects/130/docker/secrets/audit_salt.txt        -> /run/secrets/audit_salt
/home/mrpirzado/projects/130/docker/secrets/bidx_salt.txt         -> /run/secrets/bidx_salt
/home/mrpirzado/projects/130/docker/secrets/db_password.txt       -> /run/secrets/db_password
/home/mrpirzado/projects/130/docker/secrets/db_root_password.txt  -> /run/secrets/db_root_password  <-- CRITICAL FINDING
/home/mrpirzado/projects/130/docker/secrets/minio_root_password.txt -> /run/secrets/minio_root_password
/home/mrpirzado/projects/130/docker/secrets/redis_password.txt    -> /run/secrets/redis_password
```

#### Confirmed Exposure Finding SEC-KEY-01 (High):
- **Finding:** `db_root_password` is mounted into both `130-app-1-1` and `130-app-2-1`.
- **Impact:** The application executes as `crm_user` using `db_password`. Mounting `db_root_password` violates least-privilege (NIST SP 800-53 AC-6). An attacker executing code inside the container can read `/run/secrets/db_root_password` and gain full administrative ownership of MySQL.
- **Remediation:** Remove `db_root_password` from `app-1` and `app-2` secret declarations in `docker-compose.yml`.

---

## 4. Cryptographic Implementation Reality Check (AES-256 Validation)

The system claimed implementation of **AES-256-GCM** authenticated encryption. Inspection of [config/app.php:L100](file:///home/mrpirzado/projects/130/config/app.php#L100) and [GracefulEncrypted.php](file:///home/mrpirzado/projects/130/app/Casts/GracefulEncrypted.php) revealed a significant architectural mismatch:

### 4.1 Cipher Algorithm Mismatch
- **Configured Cipher:** `'cipher' => 'AES-256-CBC'` (Not AES-256-GCM).
- **Mechanism:** Laravel's standard `Encrypter` uses AES-256-CBC with an HMAC-SHA256 Encrypt-then-MAC signature over `base64(iv . value)`.
- **Deviations from Specification:**
  - Nonce size is 128-bit (16-byte CBC IV via `random_bytes(16)`), not 96-bit GCM nonce.
  - Authentication tag is a 256-bit HMAC-SHA256 digest, not a 128-bit GCM GHASH tag.
  - **No Associated Authenticated Data (AAD):** The ciphertext is **NOT bound** to the record ID, column name, or database table.

### 4.2 Empirical Attack Verification: Cut-and-Paste Ciphertext Substitution
Because AES-256-CBC has no AAD binding, we executed a negative test in staging:
```php
$cast = new \App\Casts\GracefulEncrypted();
$call1 = new \App\Models\Call(['id' => 101]);
$call2 = new \App\Models\Call(['id' => 202]);

$cipher1 = $cast->set($call1, 'caller_number', '03001111111', []);
$substitutedResult = $cast->get($call2, 'caller_number', $cipher1, []);
// Result: Successfully decrypted as '03001111111'!
```
- **Vulnerability:** An adversary with database write access can copy the encrypted `caller_number` from Call 101 into Call 202 without tripping any integrity or authentication tag check.

### 4.3 Empirical Attack Verification: Silent Fallback on Corrupted Data
In [GracefulEncrypted.php:L32-43](file:///home/mrpirzado/projects/130/app/Casts/GracefulEncrypted.php#L32-L43):
```php
} catch (DecryptException $e) {
    return $value; // Legacy plaintext fallback
}
```
When ciphertext was tampered with in staging (flipping base64 bytes):
- **Observed Behavior:** Instead of raising an alert or throwing a security exception, `GracefulEncrypted` caught `DecryptException` and returned the raw tampered base64 string (`eyJpdiI6IlRh...`) as the caller's phone number!
- **Impact:** Corrupted records bypass detection and are silently displayed to operators or written back to logs.

---

## 5. Blind Index & Bulk Decryption Assessment (S14)

### 5.1 Endpoint Surface Analysis
We analyzed every route returning call data:
1. `GET /api/lookup-caller` (`CallController::searchCallerHistory`):
   - Authenticated: Yes (`can:calls.api_lookup`, `viewAny, Call`).
   - Query: Uses `caller_number_bidx = phone_blind_index($num)`.
   - Scope: Strictly scoped by user's organizational boundary (`applyScopeVisibility`).
   - Rate/Volume Bound: Strictly limited to `limit(5)`.
2. `GET /calls/export` (`CallController::exportCsv`):
   - Streams decrypted CSV records.
   - Authorization: `CallPolicy::export` requires `calls.export` permission AND national admin / supervisor role. Ordinary call center agents receive HTTP 403 Forbidden.
3. `GET /calls` (`CallController::index`):
   - Paginated display of calls (default 25/50 per page).
   - Scoped by agent/duty scope.

### 5.2 Confirmed Flaw in Main Call Search
Inspection of [CallController.php:L123-125](file:///home/mrpirzado/projects/130/app/Http/Controllers/CallController.php#L123-L125) revealed:
```php
} elseif (preg_match('/^(\+?92|0)?(3[0-9]{2,})$/', $s, $m)) {
    $phonePrefix = '0' . $m[2];
    $query->where('calls.caller_number', 'like', "{$phonePrefix}%");
}
```
- **Flaw:** The main search bar in the web UI executes `WHERE calls.caller_number LIKE '0300%'`.
- **Result:** Because `caller_number` in MySQL contains encrypted base64 ciphertext (`eyJpdiI6...`), searching by phone number in the main UI **FAILS to return encrypted records**. The blind index `caller_number_bidx` was only implemented in the CTI AJAX popup (`searchCallerHistory`), leaving the main application search broken for encrypted callers!

---

## 6. Runtime Worker Reality: PHP-FPM vs. RoadRunner (S15)

The previous dossier reported that S15 validated request-lifecycle state isolation in a long-lived worker.

### Empirical Reality:
1. `docker exec 130-app-1-1 ps aux` confirmed:
   - Process 1: `php-fpm: master process (/usr/local/etc/php-fpm.conf)`
   - Processes 33-40: `php-fpm: pool www`
   - **RoadRunner is NOT executing in the container.**
2. Test S15 in `run_adversarial_matrix_suite.php` merely ran:
   ```php
   $workerFile = file_get_contents(base_path('app/Octane/roadrunner_worker.php'));
   assert(str_contains($workerFile, 'Auth::forgetGuards()'));
   ```
3. **Verdict:** This was a **static string check on an unexecuted file**. In PHP-FPM, request isolation is provided by the PHP-FPM worker recycle model, not `roadrunner_worker.php`. S15 is classified as **UNVERIFIED in active runtime**.

---

## 7. Infrastructure Isolation & Attack Path Analysis

Empirical network probing was executed from inside `130-app-1-1`:

### 7.1 Single Flat Bridge Network (`crm-net`)
Probing from `130-app-1-1` yielded:
```text
[OPEN] db-1:3306            (Primary MySQL)
[OPEN] db-2:3306            (Replica MySQL)
[OPEN] db-3:3306            (Replica MySQL)
[OPEN] redis:6379           (Redis In-Memory Store)
[OPEN] minio:9000           (MinIO S3 Storage)
[OPEN] prometheus:9090      (Prometheus Server)       <-- REACHABLE
[OPEN] grafana:3000         (Grafana Telemetry)       <-- REACHABLE
[OPEN] haproxy:8404         (HAProxy Stats Socket)    <-- REACHABLE
[OPEN] mysql-exporter:9104  (MySQL Telemetry Exporter)<-- REACHABLE
[OPEN] node-exporter:9100   (Host OS Metrics Exporter)<-- REACHABLE
```
- **Finding SEC-NET-01 (Medium):** All containers share a flat bridge network (`crm-net`). An attacker compromising `app-1` can connect directly to Prometheus, Grafana, exporters, HAProxy stats, and MySQL replicas.
- **Remediation:** Segment networks into `crm-frontend-net`, `crm-backend-net`, and `crm-monitoring-net`.

### 7.2 MySQL Privilege Escalation via `crm_user` Grants
Inspection of grants on `130-db-1-1`:
```sql
GRANT ALL PRIVILEGES ON `130_crm_db`.* TO `crm_user`@`%`
```
- **Finding SEC-DB-01 (High):** `crm_user` possesses `ALL PRIVILEGES` on `130_crm_db`, which includes `TRIGGER` and `DROP`.
- **Attack Path:** An attacker with database execution can issue `DROP TRIGGER audit_logs_no_update;` and proceed to overwrite or delete audit log rows, completely bypassing the immutability control.
- **Remediation:** Revoke `ALL PRIVILEGES` from `crm_user`. Grant only `SELECT, INSERT, UPDATE, DELETE` on operational tables, and `SELECT, INSERT` on `audit_logs`. Triggers must be owned by `root`.

---

## 8. Backup & Audit Resilience Assessment

### 8.1 Audit Trail Hash Chain Concurrency Forking
Inspection of [AuditLog.php:L43-44](file:///home/mrpirzado/projects/130/app/Models/AuditLog.php#L43-L44):
```php
$lastHash = static::latest('id')->value('record_hash');
$previousHash = $lastHash ?: hash('sha256', 'NHMP_130_AUDIT_GENESIS_2026');
```
- **Concurrency Flaw:** When concurrent requests execute across `app-1` and `app-2`, both processes query `latest('id')` without a pessimistic lock (`lockForUpdate`).
- **Result:** Both rows inherit the same `previous_hash`, creating a **fork in the hash chain**. When `AuditLog::verifyIntegrity()` runs, it reports a broken chain false-positive.
- **Remediation:** Acquire an atomic sequence lock or use a dedicated database sequence table for audit chaining.

### 8.2 Telemetry Alerting Gaps
Inspection of [monitoring/prometheus/alert_rules.yml](file:///home/mrpirzado/projects/130/monitoring/prometheus/alert_rules.yml):
- **Finding SEC-MON-01 (Medium):** There are **NO `InstanceDown` (`up == 0`) alert rules**. If an attacker kills `mysql-exporter`, `node-exporter`, or stops log streaming, Prometheus registers `up == 0` but fires zero alerts.

---

## 9. Comprehensive S01–S26 Evidence Matrix

The table below documents the empirical evaluation across all 26 adversarial scenarios:

| ID | Security Property Tested | Actual Test Executed | Target Code / Object | Real Attack vs Control Check | Expected Behavior | Observed Behavior | Evidence / Result | Status | Limitations & Untested Bypass Paths |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **S01** | Anonymous route protection | Route collection inspection | `routes/web.php` (`dashboard`, `calls.index`) | Control existence check | Protected by `auth` middleware | Route gathering contains `auth` | `assert($isProtected)` PASS | **Partially Verified** | Did not send raw HTTP requests without cookies to confirm 302/401 handling across all HTTP verbs. |
| **S02** | Cross-record IDOR/BOLA | Synthetic beat operator accessing foreign beat call | `CallPolicy::view` | Real authorization logic test | Policy returns `false` | Returned `false` | Policy rejected cross-beat call (0.22ms) | **Independently Verified** | Tested model policy in isolation; did not test direct API controller model-binding bypass. |
| **S03** | Agent scope containment | Synthetic agent accessing another agent's assigned call | `CallPolicy::view` | Real authorization logic test | Policy returns `false` on foreign, `true` on own | Correctly distinguished ownership | Returned `false` on foreign, `true` on own (1.41ms) | **Independently Verified** | Did not test re-assignment privilege escalation via `updateStatus`. |
| **S04** | Session invalidation on credential change | Stale session version injected into middleware | `TwoFactorEnforcement` middleware | Real request interception | Session terminated (302/401) | Returns HTTP 302 Redirect | Session flushed on stale version (70.32ms) | **Independently Verified** | Did not test Redis session revocation concurrency across multiple simultaneous requests. |
| **S05** | Non-root container & socket isolation | Checked `posix_geteuid()` and `docker.sock` | Linux UID, `/var/run/docker.sock` | Control existence check | UID != 0, socket absent | UID 82 (`www-data`), socket absent | Returned true (0.3ms) | **Weak Test** | Root filesystem is writable (`ReadonlyRootfs=false`); Linux capabilities not dropped. |
| **S06** | Distinct salts for bidx vs audit | Checked secret values via `docker_secret` | `/run/secrets/bidx_salt`, `audit_salt` | Configuration integrity check | Salts are distinct non-empty keys | Keys differ | `bidx_salt !== audit_salt` PASS | **Partially Verified** | Did not test entropy of salts or key rotation procedures. |
| **S07** | Git secrets decoupling | Regex check on `.gitignore` and `.env` | `.gitignore`, `.env` | Static file string check | No plaintext passwords in repo | Matches decoupling regex | Returned true (0.02ms) | **Weak Test** | Checks only file text; does not search git commit history for legacy leaked secrets. |
| **S08** | Container image hygiene | Checked for `gcc` and `make` in container | `/usr/bin/gcc`, `/var/www/html/.docker` | Control existence check | Compilers excluded from image | Dev tools absent | Returned true (0.05ms) | **Weak Test** | Excludes GCC but includes Alpine package manager (`apk`), which could fetch tools if egress is open. |
| **S09** | AES-256 PII encryption | Cast encrypted phone and checked ciphertext payload | `GracefulEncrypted`, `Call` | Real cryptographic test | Ciphertext != plain; contains IV, MAC | Base64 JSON envelope verified | JSON envelope has IV, value, MAC | **Partially Verified** | Cipher is AES-CBC not GCM; lacks AAD; vulnerable to cut-and-paste record substitution. |
| **S10** | Asymmetric backup encryption | Checked `openssl pkeyutl` in backup script | `scripts/backup_asymmetric.sh` | Static script check | Uses asymmetric public key | String found in script | Returned true (0.59ms) | **Weak Test** | Only checks script text; does not execute test backup and verify ciphertext randomness. |
| **S11** | Redis auth enforcement | Direct TCP socket connection without auth | `redis:6379` | Real network attack test | Redis returns `NOAUTH` error | `NOAUTH Authentication required` | Direct TCP socket received `NOAUTH` | **Independently Verified** | Redis ACLs not segmented by command; application has access to `FLUSHALL` and `CONFIG`. |
| **S12** | Database audit triggers | Queried `SHOW TRIGGERS` in MySQL | `130_crm_db.audit_logs` | Real database schema check | BEFORE UPDATE/DELETE triggers exist | Triggers confirmed | UPDATE & DELETE triggers present | **Independently Verified** | `crm_user` has `TRIGGER` grant and can drop these triggers if database credentials leak. |
| **S13** | Cryptographic MAC verification | Tampered ciphertext string passed to `decryptString` | `Crypt::decryptString` | Real negative crypto test | Throws `DecryptException` | Throws `DecryptException` | MAC verification failure detected | **Independently Verified** | While `Crypt` throws, `GracefulEncrypted` catches it and returns tampered data as plaintext. |
| **S14** | CTI blind index lookup | Method reflection and string checks on controller | `CallController::searchCallerHistory` | Static code inspection | Limits to 5, uses `caller_number_bidx` | Method and strings exist | Returned true (0.38ms) | **Weak Test** | Did not test HTTP query; main web UI search does not use blind index. |
| **S15** | RoadRunner worker isolation | String check on `roadrunner_worker.php` | `app/Octane/roadrunner_worker.php` | Static script check | Resets state between requests | Strings found | Returned true (0.57ms) | **Unverified** | Active runtime is PHP-FPM; RoadRunner is not running in the container. |
| **S16** | Offline private key separation | Verified absence of `backup_private.pem` | Container and host paths | Real file absence check | Private key absent on server | Private key not found | Search returned null | **Partially Verified** | Confirmed absent on server, but formal offline custody procedure is undocumented. |
| **S17** | Master key vs audit salt decoupling | Compared `config('app.key')` vs `audit_salt` | Configuration objects | Real comparison check | Keys are distinct | Keys differ | `appKey !== auditSalt` PASS | **Partially Verified** | Checks decoupling but does not verify cryptographic strength of salt. |
| **S18** | Direct DB update on audit logs | Executed `UPDATE audit_logs SET action='tampered'` | `130_crm_db.audit_logs` | Real database attack test | Rejected with SQLSTATE 45000 | MySQL error 45000: append-only | Trigger blocked update (42.73ms) | **Independently Verified** | `storage/logs/audit_security.jsonl` is writable and can be deleted by `www-data`. |
| **S19** | Test DB isolation guard | Checked `TestCase.php` for `PROTECTED_DATABASES` | `tests/TestCase.php` | Static file string check | Prevents test run on prod DB | Strings found | Returned true (0.81ms) | **Weak Test** | String check only; did not execute `php artisan test --database=production` to test guard. |
| **S20** | Dependency lockfile pinning | Verified `content-hash` in `composer.lock` | `composer.lock` | File metadata check | Hash pinned | Hash present | `content-hash` verified | **Weak Test** | Verifies hash exists; does not audit dependencies against CVE database (`composer audit`). |
| **S21** | Asymmetric backup schema | Checked presence of `backup_public.pem` | `storage/keys/backup_public.pem` | Real file check | Public key exists | Key present | File exists (0.12ms) | **Weak Test** | Checks file existence; does not perform end-to-end asymmetric encryption and test restore. |
| **S22** | Bridge network isolation | String check in compose / internal DNS | `crm-net`, `gethostbyname('db-1')` | Static check & DNS lookup | Network isolated | Resolves internal IP | DNS resolved (1.37ms) | **Weak Test** | Bridge is flat; app container can communicate with all database, exporter, and telemetry containers. |
| **S23** | Super Admin web password reset block | Submitted `/forgot-password` for `superadmin` | `PasswordResetLinkController` | Real application attack test | No reset request created | No row in `password_reset_requests` | Request rejected for superadmin (119ms) | **Independently Verified** | Does not test username enumeration via differing error messages or response timing. |
| **S24** | UserScope expiry enforcement | Tested policy with empty `activeScopes` | `CallPolicy::view` | Real authorization logic test | Returns `false` (fail-closed) | Returned `false` | Denied access on expired scope (1.37ms) | **Independently Verified** | Did not test clock manipulation or time-zone offset bypasses. |
| **S25** | Sequence race condition lock | Checked `DB::transaction` in `CallService.php` | `app/Services/CallService.php` | Static code inspection | Transaction and retry loop present | Strings found | Returned true (2.26ms) | **Weak Test** | String check only; did not execute concurrent call creations to test lock contention. |
| **S26** | Telemetry stack existence | Checked compose service names | `docker-compose.yml`, Prometheus DNS | Static compose check | Exporters defined | Services declared | Returned true (0.51ms) | **Unverified** | Prometheus lacks `InstanceDown` rules; alert pipeline fails silently if exporters die. |

---

## 10. Prioritized Remediation Register

| Priority | Finding ID | Vulnerability / Defect Description | Severity | Impact | Owner | NIST SP 800-53 | Remediation Criteria |
| :---: | :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **P0** | **SEC-KEY-01** | `db_root_password` mounted into application containers | **HIGH** | App compromise yields full MySQL root | Platform Eng | **AC-6, IA-5** | Remove `db_root_password` from `app-1` and `app-2` in `docker-compose.yml`. |
| **P0** | **SEC-CRY-01** | `GracefulEncrypted` returns raw corrupted ciphertext on MAC failure | **HIGH** | Data corruption / tampering silently masked | App Security | **SC-13, SI-7** | Throw `IntegrityViolationException` when base64 payload fails decryption; never return corrupted ciphertext. |
| **P0** | **SEC-CRY-02** | Lack of AAD binding in field encryption | **HIGH** | Cut-and-paste ciphertext substitution across calls | Cryptography | **SC-13, SC-28** | Migrate to AES-256-GCM with AAD bound to `table:record_id:column`. |
| **P1** | **SEC-DB-01** | `crm_user` has `ALL PRIVILEGES` including `TRIGGER` and `DROP` | **HIGH** | Attacker can drop audit immutability triggers | DBA / Data Eng | **AC-6, AU-9** | Restrict `crm_user` to DML only; audit triggers owned by `root`. |
| **P1** | **SEC-SRC-01** | Main call UI search does not query blind index | **HIGH** | Encrypted citizen records impossible to search in UI | App Dev | **SI-10, SC-8** | Update `CallController::applyContextFilters` to use `phone_blind_index` when search query is numeric. |
| **P1** | **SEC-NET-01** | Flat Docker bridge (`crm-net`) exposes monitoring and replicas | **MEDIUM** | Lateral movement to metrics and replica databases | Infra Eng | **SC-7, AC-4** | Split into `crm-frontend-net`, `crm-backend-net`, `crm-metrics-net`. |
| **P2** | **SEC-AUD-01** | Audit log hash chain forks under cross-node concurrency | **MEDIUM** | Integrity verification false-positives under load | Backend Eng | **AU-9, SC-12** | Implement atomic sequence counter in MySQL for linear audit chain. |
| **P2** | **SEC-AUD-02** | `audit_security.jsonl` is container-local and writable by `www-data` | **MEDIUM** | Compromised web process can erase local audit file | SecOps | **AU-9, AU-4** | Stream JSONL logs to remote syslog / Vector agent outside container. |
| **P2** | **SEC-MON-01** | Missing `InstanceDown` alert rules in Prometheus | **MEDIUM** | Exporter or monitoring failures go undetected | Monitoring | **SI-4, AU-6** | Add `alert: ServiceInstanceDown expr: up == 0` to `alert_rules.yml`. |
| **P3** | **SEC-TST-01** | 9 of 26 adversarial tests rely on static string checks | **LOW** | False sense of security from unexercised controls | QA / Test Eng | **CA-7, SA-11** | Replace string checks with dynamic functional attacks in test runner. |

---

## 11. Explicit List of Controls Remaining Unverified

The following controls **CANNOT** be certified as verified until dynamic, end-to-end testing is conducted:
1. **RoadRunner Multi-User Worker Isolation (S15):** The active staging environment runs PHP-FPM; RoadRunner was not evaluated under live worker execution.
2. **Prometheus Telemetry Failure Alert Delivery (S26):** Alert rules lack `up == 0` conditions; alert dispatch was not tested.
3. **Automated Asymmetric Backup Decryption & Point-in-Time Recovery (S21):** The backup creation script was inspected, but full restore of an encrypted `.tar` archive using an offline private key in an isolated test environment has not been executed.
4. **Call Sequence Concurrency Locking (S25):** Evaluated via code review only; concurrent parallel HTTP requests hammering sequence generation simultaneously have not been benchmarked under heavy lock contention.
5. **Dynamic Egress Network Filtering:** Application container egress to external internet IPs was not evaluated under active firewall restrictions.

---

## 12. Conclusion & Operational Recommendation

The NHMP 130 Helpline exhibits robust foundational defensive controls (fail-closed RBAC policies, Argon2id password hashing, deterministic HMAC blind indexing for CTI, and immutable MySQL triggers). 

However, because:
1. `db_root_password` is mounted into the application containers,
2. `GracefulEncrypted` masks tampered ciphertext and lacks AAD record binding,
3. Database credentials allow dropping audit triggers (`TRIGGER` grant),
4. The main application search fails for encrypted phone numbers,

**Final Recommendation:** **DO NOT CERTIFY FOR PRODUCTION DEPLOYMENT** until the P0 and P1 remediations in Section 10 are applied and verified in a secondary test pass.
