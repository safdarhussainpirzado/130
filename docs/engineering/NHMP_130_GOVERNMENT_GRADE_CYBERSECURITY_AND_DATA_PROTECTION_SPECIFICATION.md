# NHMP 130 Helpline — Government-Grade Cybersecurity & Data Protection Specification

**Document ID:** `NHMP-130-SEC-SPEC-2026-V1`  
**Classification:** Official Technical Specification / Controlled Engineering Artifact  
**Target Environment:** NHMP 130 Staging (`100.108.239.107`) & Production (`100.124.232.124`)  
**Hosting Environment:** National Telecommunication Corporation (NTC) Government Data Center  
**Operational Scope:** Application Layer, Container Perimeter, Database Schema, and Key Isolation  
**Effective Date:** November 2026  

---

## 1. Executive Summary & Operational Context

The National Highways & Motorway Police (NHMP) 130 Helpline is a mission-critical emergency dispatch and citizen safety system. It operates 24 hours a day, 7 days a week, handling highway emergencies, distress calls, medical assists, and "Tiger" mobile patrol dispatches across Pakistan's national motorways and highways.

### 1.1 The Operational Scale vs. Theoretical Security
The active database (`130_crm_db`) holds:
- **709,834+ Emergency Call Records** in the `calls` table.
- **1,012,207+ Authentication & Audit Records** in `login_logs` and `audit_logs`.
- **Sub-10ms CTI Lookup Requirement:** When a citizen dials 130, the Computer Telephony Integration (CTI) system queries the caller's history using `idx_calls_caller_created`. Any cryptographic architecture that introduces network latency (such as an external HTTP microservice for row decryption) or breaks database indexing will result in UI freezes, dropped calls, and catastrophic delays during emergency dispatches.

### 1.2 The Hosting Reality: Application-Layer Ownership
NHMP's infrastructure is hosted within the National Telecommunication Corporation (NTC) Government Data Center. The engineering team possesses **direct control of the Virtual Machine (VM) and Application Container layer**, but **does NOT have physical datacenter access, rack space control, or physical hardware provisioning authority**.

Consequently:
1. **Physical Hardware Security Modules (HSMs) are physically unfeasible** on Day 1 without bureaucratic procurement cycles spanning 12–24 months.
2. **All defensive controls must be enforced rigorously at the Application, Container, Database Schema, and Reverse-Proxy layers.**
3. Security must be achieved through **Assume-Breach Engineering**: ensuring that even if an attacker compromises a server or steals database dumps, citizen PII remains strongly encrypted, credentials remain uncrackable, backups remain ransomware-proof, and audit trails remain tamper-evident.

---

## 2. National CERT Advisory Audit (NCA-19.211026)

On October 2026, the National Cyber Emergency Response Team (NCERT) of Pakistan issued Advisory **NCA-19.211026** regarding two critical vulnerabilities in the PHP runtime:

| Field | Detail |
| :--- | :--- |
| **Advisory ID** | `NCA-19.211026` |
| **Vulnerabilities** | `CVE-2026-109618` and `CVE-2026-109619` |
| **Severity** | **Critical** (CVSS v4.0 Base Score: **9.2 / 10**) |
| **Attack Vector** | Malicious HTTP requests to internet-facing PHP applications; multipart/form-data request processing and session upload-progress handling. |
| **Impact** | Potential unauthenticated Remote Code Execution (RCE), web-shell deployment, data exfiltration. |
| **Affected Scope** | Federal & Provincial Government portals, Critical Information Infrastructure (CII). |

### 2.1 Verification & Compliance Status for NHMP 130
1. **Upstream Runtime Patched:**
   - NCERT Advisory Section 2 states the emergency fix for the PHP 8.3 branch is version **`8.3.36`**.
   - Inspection of `Dockerfile` (Stage 4) confirms:
     ```dockerfile
     FROM php:8.3.36-fpm-alpine
     ```
     **NHMP 130 is already built on the official patched release (`8.3.36`).**

2. **Mandatory Compensating Hardening (NCERT Section 3):**
   To eliminate attack surface from legacy or unconfirmed exploit variations (such as the "PHP2Shell" chain), the following directives are enforced in `/usr/local/etc/php/conf.d/security.ini`:
   ```ini
   ; NCERT NCA-19.211026 Mandatory Hardening
   session.upload_progress.enabled = Off
   session.use_strict_mode = On
   expose_php = Off
   ```

3. **Reverse-Proxy Request Filtering:**
   Nginx and HAProxy strictly enforce body size limits (`client_max_body_size 20M`) and reject invalid multipart boundary payloads before requests reach the PHP runtime.

---

## 3. Architecture Runtime Status: Octane vs. PHP-FPM / Nginx

The repository contains artifacts relating to both PHP-FPM and RoadRunner / Octane:

| Component | Status | Location / Configuration | Active in Production / Staging? |
| :--- | :--- | :--- | :--- |
| **PHP-FPM** | **ACTIVE RUNTIME** | `Dockerfile`, `nginx-1.conf`, `nginx-2.conf`, `docker-compose.yml` | **YES.** Ports `app-1:9000` and `app-2:9000` receive all dynamic requests via Nginx FastCGI backends. |
| **Nginx** | **ACTIVE RUNTIME** | `web-1`, `web-2` services in `docker-compose.yml` | **YES.** Nginx terminates HTTP from HAProxy, serves static assets from `/public/build`, and reverse-proxies FastCGI. |
| **HAProxy** | **ACTIVE RUNTIME** | `130-haproxy-1` (`haproxy/haproxy.cfg`) | **YES.** Edge load-balancer terminating public TLS (80/443), health-checking `web-1` and `web-2`. |
| **Laravel Octane / RoadRunner** | **PROTOTYPE / STRESS TEST** | `.rr.yaml`, `app/Octane/roadrunner_worker.php` | **NO.** RoadRunner is maintained as a high-concurrency prototype. It is not currently active in `docker-compose.yml`. |

### 3.1 Octane Adoption Assessment
`app/Octane/roadrunner_worker.php` was engineered to benchmark 1,000,000 call concurrency and includes state-isolation guards (`Auth::forgetGuards()`, scope resets). However, because the current production environment relies on standard FastCGI state reset between requests, **PHP-FPM 8.3.36 behind Nginx remains the authoritative, verified runtime**. 

Any migration to Laravel Octane in production will require a separate release gate verification to ensure that multi-tenant session and duty-scope state (`UserScope`, `Office`) never leaks across concurrent requests in long-lived PHP worker processes.

---

## 4. Key Management & Secrets Architecture via Docker Secrets

In accordance with the zero-NTC-dependency constraint, secret management is decoupled from plaintext `.env` files and hardcoded Docker defaults.

### 4.1 Threat Model Addressed
- **Threat:** An attacker obtains a read-only local file inclusion (LFI) vulnerability or directory traversal and downloads `/var/www/html/.env`.
- **Mitigation:** The `.env` file does **not** contain master cryptographic keys or database root passwords. Sensitive keys are mounted as read-only files at `/run/secrets/` by the Docker container engine.

### 4.2 Docker Secrets Architecture
The following secrets are defined and injected into the application and infrastructure services:

```
HOST (Protected Storage: /etc/nhmp130/secrets/ or .docker/secrets/)
  ├── app_key.txt          (Laravel APP_KEY: AES-256 master key)
  ├── bidx_salt.txt        (HMAC-SHA256 salt for deterministic caller search)
  ├── db_password.txt      (MySQL operational user password)
  ├── db_root_password.txt (MySQL root administrative password)
  └── redis_password.txt   (Redis authentication token)
       │
       ▼ (Mounted as tmpfs / read-only volumes)
CONTAINER RUNTIME (/run/secrets/* with 0444/0400 permissions)
  ├── /run/secrets/app_key
  ├── /run/secrets/bidx_salt
  ├── /run/secrets/db_password
  ├── /run/secrets/db_root_password
  └── /run/secrets/redis_password
```

### 4.3 Container Bootstrapping (`entrypoint.sh`)
Before Laravel caches configuration, `entrypoint.sh` inspects `/run/secrets/` and exports corresponding environment variables into the PHP-FPM execution context:

```sh
if [ -d "/run/secrets" ]; then
    for secret in /run/secrets/*; do
        if [ -f "$secret" ]; then
            var_name=$(basename "$secret" | tr '[:lower:]' '[:upper:]')
            export "$var_name"="$(cat "$secret")"
        fi
    done
fi
```

### 4.4 Framework Secret Resolution Helper
In `app/Helpers/helpers.php`, a universal fallback helper ensures seamless local, staging, and container operation:

```php
if (!function_exists('docker_secret')) {
    function docker_secret(string $name, mixed $default = null): mixed
    {
        $path = "/run/secrets/{$name}";
        if (file_exists($path) && is_readable($path)) {
            return trim((string) file_get_contents($path));
        }
        return env(strtoupper($name), $default);
    }
}
```

---

## 5. Selective Field-Level Encryption & High-Performance Search

### 5.1 Strict Data Boundary: Personal Information ONLY
To maintain database query speed, reporting efficiency, and prevent operational bottlenecks:

| Information Category | Specific Fields | Encryption Policy | Technical Rationale |
| :--- | :--- | :--- | :--- |
| **Citizen Identity (PII)** | `calls.caller_number`<br>`calls.caller_name`<br>`users.cnic`<br>`users.mobile_no` | **ENCRYPTED** (AES-256-GCM) + **Blind Index** | Prevents caller tracking and identity exposure if database dumps or backups are stolen. |
| **Citizen Safety Contacts** | `commuter_profiles.emergency_contacts`<br>`commuter_profiles.vehicle_profiles` | **ENCRYPTED** (AES-256-GCM serialized JSON) | Protects next-of-kin contact numbers and vehicle profiles. |
| **Call Operational Details** | `calls.call_number`<br>`calls.details`<br>`calls.status`<br>`calls.priority`<br>`calls.vehicle_no`<br>`calls.km_marker_text`<br>`calls.carriageway_id`<br>`calls.office_id`<br>`calls.created_at`<br>`calls.call_start_time`<br>`calls.response_time_sec` | **UNENCRYPTED** (Plaintext in DB) | **CRITICAL:** High-frequency dashboard filtering, aggregated rollups (`call_hourly_rollups`), dispatch metrics, and spatial reporting must execute in milliseconds without decrypting 709,000+ records. |

### 5.2 The Caller Search Problem & Deterministic Blind Index Solution
Standard AES-256-GCM uses randomized Initialization Vectors (IVs) / nonces. Encrypting `"03001234567"` twice yields two completely different ciphertexts. Therefore, standard SQL exact queries (`WHERE caller_number = '03001234567'`) fail.

**The Solution:** A dedicated HMAC-SHA256 Blind Index column (`caller_number_bidx`):

```
                       Citizen Phone Number: "03001234567"
                                        │
             ┌──────────────────────────┴──────────────────────────┐
             ▼                                                     ▼
    AES-256-GCM (Random IV)                               HMAC-SHA-256 (Secret Salt)
             │                                                     │
             ▼                                                     ▼
`calls.caller_number`                                  `calls.caller_number_bidx`
  Type: VARCHAR(512)                                     Type: VARCHAR(64)
  Storage: Unreadable Ciphertext                         Index: idx_calls_bidx_created
  Purpose: At-rest PII confidentiality                   Purpose: Sub-5ms exact CTI lookup
```

#### Blind Index Helper (`app/Helpers/helpers.php`):
```php
if (!function_exists('phone_blind_index')) {
    function phone_blind_index(?string $phone): ?string
    {
        if (empty($phone)) return null;
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if (empty($digits)) return null;
        $salt = config('app.bidx_salt') ?: docker_secret('bidx_salt');
        return hash_hmac('sha256', $digits, $salt);
    }
}
```

#### Eloquent Implementation (`app/Models/Call.php`):
```php
protected function casts(): array
{
    return [
        'caller_number'    => 'encrypted',
        'caller_name'      => 'encrypted',
        // Operational details remain unencrypted
        'is_reminder_call' => 'boolean',
        'followup_needed'  => 'boolean',
    ];
}

protected static function booted(): void
{
    static::saving(function (Call $call): void {
        if ($call->isDirty('caller_number') && !empty($call->caller_number)) {
            $call->caller_number_bidx = phone_blind_index($call->caller_number);
        }
    });
}
```

#### Optimized Controller Lookup (`app/Http/Controllers/CallController.php`):
```php
$searchBidx = phone_blind_index($inputNumber);

$recentCalls = Call::where('caller_number_bidx', $searchBidx)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(); // Automatically decrypted into plaintext by Eloquent casts
```

### 5.3 Column Width Expansion Requirements
Standard plaintext phone columns are `VARCHAR(20)`. An AES-256-GCM encrypted payload contains base64-encoded initialization vectors, ciphertext, and authentication tags totaling 180–250 characters.

**Mandatory Schema Migration (`database/migrations/2026_11_04_233000_add_security_encryption_and_blind_indexes.php`):**
1. Expand `calls.caller_number` to `VARCHAR(512)`.
2. Expand `calls.caller_name` to `VARCHAR(512)`.
3. Add `calls.caller_number_bidx` `VARCHAR(64)` with composite index `(caller_number_bidx, created_at)`.
4. Expand `users.cnic` and `users.mobile_no` to `VARCHAR(512)` and add `cnic_bidx` and `mobile_no_bidx`.

---

## 6. Password Storage: Argon2id Baseline

### 6.1 Cryptographic Standard
In strict alignment with OWASP recommendations and government cybersecurity standards:
- **Algorithm:** `Argon2id` (Memory-hard password hashing resistant to GPU/ASIC cracking).
- **Memory Cost:** `19456 KiB` (~19 MiB).
- **Time Cost:** `2 iterations`.
- **Parallelism:** `1 thread`.

### 6.2 Configuration (`config/hashing.php`)
```php
return [
    'driver' => env('HASH_DRIVER', 'argon2id'),
    'argon' => [
        'memory'  => env('HASH_ARGON_MEMORY', 19456),
        'threads' => env('HASH_ARGON_THREADS', 1),
        'time'    => env('HASH_ARGON_TIME', 2),
        'verify'  => true,
    ],
    'rehash_on_login' => true,
];
```

### 6.3 Zero-Downtime Rehash Lifecycle
No manual migration script is required for existing user passwords:
1. When a user (call operator, supervisor, administrator) logs in with their existing password, Laravel validates the existing Bcrypt hash.
2. Because `rehash_on_login` is enabled and `driver` is `argon2id`, Laravel automatically detects that the hash needs an upgrade (`Hash::needsRehash()`) and transparently saves an Argon2id hash to the database.

---

## 7. Network Perimeter & Container Lockdown

In `docker-compose.yml`, internal data stores are locked down to prevent lateral network movement:

| Service | Pre-Remediation Port Binding | Remediated Port Binding | Security Impact |
| :--- | :--- | :--- | :--- |
| **MySQL Primary (`db-1`)** | `0.0.0.0:3306:3306` | `127.0.0.1:3309:3306` | Port 3306 closed to public network. Only accessible locally on host via authenticated SSH tunnel. |
| **Redis Cache (`redis`)** | `0.0.0.0:6379:6379` | **Removed from host** (internal only) | Redis accessible solely via internal Docker bridge `crm-net`. Password authentication required. |
| **MinIO Storage (`minio`)** | `0.0.0.0:9000:9000` | `127.0.0.1:9000:9000` | S3 API bound strictly to loopback. Anonymous public bucket policy eliminated. |
| **Grafana (`grafana`)** | `0.0.0.0:3000:3000` | `127.0.0.1:3000:3000` | Monitoring dashboard restricted to host loopback. |
| **HAProxy (`haproxy`)** | `80:80`, `443:443` | `80:80`, `443:443` | Sole public ingress. Stats page bound strictly to `127.0.0.1:8404`. |

---

## 8. Ransomware-Proof Asymmetric Backups

To defend against scenarios where a server is fully compromised and the attacker attempts to destroy or hold database dumps for ransom:

### 8.1 Asymmetric Encryption Mechanism
1. A 4096-bit RSA or Age cryptographic keypair is generated on an **offline administrative workstation**.
2. **Only the Public Key (`backup_public.key`)** is placed on the production server.
3. The automated backup script executes:
   ```bash
   mysqldump --single-transaction --quick -u "$DB_USER" -p"$DB_PASS" 130_crm_db \
       | gzip \
       | age -r "$PUBLIC_KEY" > /backups/130_crm_$(date +%Y%m%d_%H%M%S).sql.gz.enc
   ```
4. **Result:** An attacker with full root access on the production host **cannot decrypt the backup files**, because the private decryption key does not exist anywhere on the server.

### 8.2 Offsite WORM Storage (Write-Once, Read-Many)
Encrypted backup artifacts are synchronized to an independent storage node configured with Object Lock (immutability). Even if a malicious actor executes `rm -rf /backups/*` on production, historical offsite backups cannot be deleted or modified.

---

## 9. Implementation Roadmap & Staging Verification Gates

```
[STAGE 1: Container & Network Perimeter Lockdown]
  ├── Update docker-compose.yml: Remove 0.0.0.0 bindings for MySQL, Redis, MinIO
  ├── Add NCERT security.ini: Disable session.upload_progress, enable strict mode
  └── Enforce non-default mandatory variables (:? traps) in compose

[STAGE 2: Docker Secrets Integration]
  ├── Create secrets configuration and host storage (/etc/nhmp130/secrets/)
  ├── Update entrypoint.sh to export secrets dynamically on container boot
  └── Implement docker_secret() and phone_blind_index() helpers

[STAGE 3: Database Schema & Blind Indexing Migration]
  ├── Execute migration on staging disposable database:
  │     - Expand calls.caller_number, caller_name to VARCHAR(512)
  │     - Add calls.caller_number_bidx with composite index (bidx, created_at)
  │     - Expand users.cnic, mobile_no to VARCHAR(512) with blind index columns
  ├── Populate blind indexes for historical records via background job
  └── Benchmark lookup speed: Verify CTI popup responds in < 10ms

[STAGE 4: Model Casts & Controller Integration]
  ├── Activate encrypted casts on Call, User, and CommuterProfile
  ├── Update CallController to query caller_number_bidx for phone searches
  └── Verify that call details, statuses, and reporting queries remain unencrypted

[STAGE 5: Automated Security Verification & Release Sign-Off]
  ├── Run PHPUnit security test suite:
  │     - FieldEncryptionAndDockerSecretsTest
  │     - TwoFactorSecurityTest
  │     - DatabaseIsolationGuardTest
  └── Formal release sign-off for staging candidate
```

---

## 10. Document Authority & Approval

| Role | Entity | Status | Date |
| :--- | :--- | :--- | :--- |
| **Lead Application Architect** | NHMP 130 Engineering Team | Drafted & Formulated | 2026-11-04 |
| **Information Security Lead** | Cyber Security & Systems Audit | Technical Baseline Verified | 2026-11-04 |
| **Authorizing Official** | National Highways & Motorway Police | Formal Implementation Mandate | Pending Stage 5 Verification |
