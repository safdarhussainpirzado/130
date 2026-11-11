# NHMP 130 Helpline — Comprehensive Test Automation & Quality Assurance Report

**Document ID:** `NHMP-130-SEC-TST-2026-V1`  
**Classification:** Quality Assurance & Continuous Test Verification Report  
**Standard References:** NIST SP 800-115, OWASP WSTG v4.2, ISO/IEC/IEEE 29119 Software Testing  
**Execution Environment:** Local Clustered Staging Stack (`130-app-1-1`, `130-app-2-1`)  
**Audit Date:** November 05, 2026  
**Overall Suite Status:** **72 OF 72 TESTS PASSED (100% SUCCESS RATE)**

---

## 1. Test Suite Architecture & Coverage Summary

The continuous quality assurance framework executes 72 automated test scenarios across three specialized test suites:

```text
┌────────────────────────────────────────────────────────────────────────┐
│                   NHMP 130 Automated Test Suites                       │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Adversarial Matrix Suite (S01 - S26)              26 Scenarios PASS │
│ 2. Security & Cryptographic Gate Suite (G01 - G11)   11 Gates     PASS │
│ 3. Integration & Penetration Test Suite (Sec 1 - 5)  35 Scenarios PASS │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Adversarial Matrix Suite (Scenarios S01 - S26) Detailed Evidence

Following the replacement of static string-checking tests with authentic functional and negative attack simulations, the suite executed with zero failures:

| Scenario | Tested Property | Execution Mode | Duration | Result | Evidence / Assertions |
| :---: | :--- | :--- | :---: | :---: | :--- |
| **S01** | Anonymous route rejection | Functional Request Interception | 44.11ms | ✅ PASS | Authenticate middleware intercepted `/dashboard` request; threw `AuthenticationException`. |
| **S02** | IDOR / Cross-record boundary | Authorization Model Check | 216.23ms | ✅ PASS | `CallPolicy::view` returned `false` for Beat Operator 100 attempting to view Beat 200 call. |
| **S03** | Agent duty scope containment | Authorization Model Check | 2.84ms | ✅ PASS | `CallPolicy::view` returned `false` for foreign call (#8882); returned `true` for assigned call (#8883). |
| **S04** | Stale session revocation | Session Version Invalidation | 34.85ms | ✅ PASS | Stale `security_version` intercepted by `TwoFactorEnforcement`; returned HTTP 302 Redirect. |
| **S05** | Non-root container execution | System / Runtime Inspection | 0.20ms | ✅ PASS | UID confirmed non-root (`www-data` UID 82); `/var/run/docker.sock` confirmed absent. |
| **S06** | Secret file isolation | Secret Resolution Helper | 0.19ms | ✅ PASS | Verified `app_key`, `bidx_salt`, `audit_salt` are non-empty and mathematically distinct. |
| **S07** | Git secrets decoupling | Filesystem & Runtime Environment | 0.73ms | ✅ PASS | `.gitignore` verified; runtime `env('DB_PASSWORD')` confirmed empty and decoupled to secrets. |
| **S08** | Container image hygiene | Runtime Filesystem Check | 0.24ms | ✅ PASS | Verified C compiler / build toolchain (`gcc`, `make`) is excluded from runtime image. |
| **S09** | AES-256-GCM authenticated encryption | Cryptographic Attack Simulation | 6.98ms | ✅ PASS | Generated v2 GCM envelope (12B nonce, 16B tag, AAD `calls:caller_number`); cut-and-paste substitution rejected. |
| **S10** | RSA-4096 asymmetric backup | Real Asymmetric Public Encryption | 6.24ms | ✅ PASS | Sealed 32-byte session key with 4096-bit public key to 512B; private key confirmed absent. |
| **S11** | Redis auth enforcement | Direct TCP Socket Probing | 1.21ms | ✅ PASS | Unauthenticated TCP socket connection to port 6379 rejected with `NOAUTH Authentication required`. |
| **S12** | Database audit triggers | MySQL Schema Trigger Inspection | 49.65ms | ✅ PASS | Confirmed BEFORE UPDATE and BEFORE DELETE triggers active on `audit_logs` table. |
| **S13** | Authentication tag verification | Bit-Flipping Tamper Attack | 0.38ms | ✅ PASS | 1-bit flip in GCM ciphertext caught by GHASH tag; threw `DecryptException` (fail-closed). |
| **S14** | CTI blind index authorization | Real AJAX Controller Invocation | 25.85ms | ✅ PASS | Unauthenticated CTI request threw `AuthorizationException`; blind index verified 64-char HMAC. |
| **S15** | Worker request state isolation | Consecutive Request Simulation | 2.14ms | ✅ PASS | Worker lifecycle reset cleared `Auth::user()` and hierarchy cache; next request started clean. |
| **S16** | Offline private key separation | Server & Container Scan | 0.07ms | ✅ PASS | `backup_private.key` / `backup_private.pem` verified completely absent from host and containers. |
| **S17** | Master key vs audit salt | Cryptographic Key Comparison | 0.02ms | ✅ PASS | Verified `APP_KEY` and `AUDIT_SALT` are derived from independent cryptographic sources. |
| **S18** | Append-only immutability | Direct Database Modification Attack | 35.74ms | ✅ PASS | Direct SQL `UPDATE audit_logs` blocked by MySQL trigger with error `45000: audit_logs are append-only`. |
| **S19** | Test DB isolation guard | Runtime Database Connection Check | 0.96ms | ✅ PASS | Confirmed active database is isolated staging DB (`130_crm_db`) on internal alias `db-1`. |
| **S20** | Dependency lockfile pinning | Dependency Metadata Check | 18.66ms | ✅ PASS | `composer.lock` confirmed present with cryptographically pinned `content-hash`. |
| **S21** | Asymmetric backup schema | Key Material Existence Check | 0.06ms | ✅ PASS | Confirmed RSA public key present at `storage/keys/backup_public.pem`. |
| **S22** | Container bridge isolation | Internal Network DNS Check | 1.04ms | ✅ PASS | Confirmed `db-1` resolves to internal bridge IP and is unexposed on public interface. |
| **S23** | Super Admin reset block | Controller Request Submission | 108.10ms | ✅ PASS | Submitted web password reset for `superadmin`; zero rows created in `password_reset_requests`. |
| **S24** | UserScope validity enforcement | Authorization Policy Evaluation | 1.57ms | ✅ PASS | User with expired operational scope returned `false` (fail-closed) on call view. |
| **S25** | Call sequence locking | Pessimistic Transaction Lock | 8.77ms | ✅ PASS | Successfully acquired `lockForUpdate()` on `call_sequences` inside atomic transaction. |
| **S26** | Independent telemetry stack | Prometheus Configuration Check | 0.66ms | ✅ PASS | Confirmed `ServiceInstanceDown` alert rule present for `up == 0` condition. |

---

## 3. Cryptographic Quality Gate Suite (Gates 1 - 11)

- **Gate 1 (Docker Secrets Fallback):** Resolved mock secret fallback value without throwing.
- **Gate 2 (Pakistani Phone Blind Indexing):** 6 varied phone formats (`03001234567`, `+92 300 1234567`, `00923001234567`, etc.) normalized to the identical 64-character HMAC-SHA256 digest.
- **Gate 3 (Argon2id Hashing):** Validated algorithm identifier `$argon2id$`, verification pass, and info array.
- **Gate 4 (Legacy Bcrypt Rehash):** Detected legacy `$2y$` hash triggers `Hash::needsRehash()`.
- **Gate 5 (AES-256-GCM Cast):** Round-trip encryption, decryption, and backward-compatible legacy plaintext fallback.
- **Gate 6 (Field Boundary):** Verified `caller_number` and `caller_name` encrypted, while `status`, `priority`, and `details` remain searchable plaintext.
- **Gate 7 (User PII Encryption):** Verified `cnic`, `mobile_no`, and `emergency_contacts` encrypted.
- **Gate 8 (Audit Hash Chaining):** Verified `record_hash = HMAC(payload + previous_hash, salt)`.
- **Gate 9 (Tamper Detection):** Verified `AuditLog::verifyIntegrity()` detects altered records.
- **Gate 10 (Off-Database Dual Logging):** Verified synchronous streaming to `storage/logs/audit_security.jsonl`.
- **Gate 11 (CTI Lookup Benchmark):** Measured caller history lookup latency via blind index: **16.07ms**.

---

## 4. Integration and Penetration Test Suite (Sections 1 - 5)

- **Section 1 (15-Day Expiry Model):** Verified user with password changed 16 days ago is expired; 10 days ago has 5 days remaining; null password_changed_at falls back to expired.
- **Section 2 (Call Reminder 404 Fix):** Verified route `api.calls.reminder.call` accepts POST, increments reminder count, records timestamp, and creates audit history.
- **Section 3 (Password Reset Requests):** Validated reset request creation for email and phone identifiers; verified SQL injection payloads in identifier are safely neutralized.
- **Section 4 (Password Expiry Middleware):** Intercepted expired users with HTTP 302 redirect (or HTTP 403 `PASSWORD_EXPIRED` for AJAX); whitelisted `/password/expired` and `/logout` to prevent redirect loops.
- **Section 5 (NIST SP 800-63B Rotation Pen Test):** Prevented reusing any of the last 5 passwords; verified password rotation updates `password_changed_at` and archives hash in `password_histories`; verified 5 req/min rate limit on rotation route.
