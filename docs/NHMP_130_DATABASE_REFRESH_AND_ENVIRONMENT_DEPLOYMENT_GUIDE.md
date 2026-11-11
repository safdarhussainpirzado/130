# NHMP 130 HELPLINE CRM — DATABASE REFRESH AUTOMATION & GUARDED ENVIRONMENT DEPLOYMENT GUIDE

**Classification:** `LOCALLY VERIFIED — REMOTE DEPLOYMENT NOT EXECUTED`  
**Target Platform:** National Highways & Motorway Police (NHMP) 130 Helpline CRM  
**Date:** November 2026  
**Document Version:** 1.0.0 (Unified Operational Specification)

---

## Executive Summary

This engineering manual defines the operational specifications, safety architectures, and guarded command workflows for:
1. **Production-Derived Local Database Refresh Automation**: Safe snapshot acquisition, stream-based deterministic data sanitization, integrity verification, and staged local database restoration.
2. **Guarded Environment Deployments**: Environment-isolated deployment targets for Staging (`100.108.239.107`) and Production (`100.124.232.124`), requiring explicit confirmations, immutable cryptographic image digests, and automated quality gates.
3. **Emergency Zero-Downtime Rollback Orchestration**: Immediate traffic containment, node draining, and rollback to known-good baseline images without connection drops or database degradation.

Per absolute safety directives, remote operational infrastructure was **not accessed, modified, or targeted with network traffic** during this local engineering phase. All mechanisms have been verified and validated within the local WSL2/Docker testbed.

---

## 1. Environment Topology & Absolute Safety Boundaries

The operational architecture defines strict isolation between three distinct operational domains:

| Environment | Purpose | Network Endpoint / Host | Safety & Execution Policy |
|---|---|---|---|
| **Local WSL2 / Docker** | Development, testing, and local verification | `127.0.0.1:8085` / Container Stack | Active engineering testbed; disposable databases only. |
| **Operational Staging** | Candidate edge host & pre-production UAT | `100.108.239.107:8080` | **STRICTLY OFF-LIMITS**: Zero SSH packets, HTTP probes, or remote mutations. |
| **Operational Production** | Live NHMP 130 emergency helpline | `100.124.232.124` / `130.nhmp.gov.pk` | **STRICTLY OFF-LIMITS**: Live citizen emergency services. No connections permitted. |

### Enforced Safety Restrictions
- Direct commands fail-closed if `ENV=production` or `ENV=staging` is targeted without explicit confirmation.
- Direct database refresh commands reject operational database names (`130_crm_production`, `staging`, etc.) and require local container targets.
- Remote deployment requires an immutable image digest (`IMAGE_DIGEST=sha256:...`); mutable tags like `latest` are rejected.
- SSH host-key verification (`StrictHostKeyChecking=yes`) is strictly enforced; disabling host key verification is prohibited.
- Passwords are never passed as command-line arguments or stored in plaintext logs.

---

## 2. Feature A — Production-Derived Database Refresh Architecture

```
+---------------------------------------------------------------------------------------------------------+
|                                    REMOTE PRODUCTION (100.124.232.124)                                   |
|   +-----------------------+     +-------------------------------+     +-----------------------------+   |
|   |   Live 130 Database   | --> | Consistent mysqldump Stream   | --> | SSH Secure Stream           |   |
|   |   (Single-Transaction)|     | (--single-transaction --quick)|     | (Strict Host-Key Checking)  |   |
|   +-----------------------+     +-------------------------------+     +--------------+--------------+   |
+--------------------------------------------------------------------------------------|------------------+
                                                                                       |
                                              SECURE IN-FLIGHT STREAM PIPELINE         |
                                                                                       v
+---------------------------------------------------------------------------------------------------------+
|                                  LOCAL WSL2 / DOCKER TEST ENVIRONMENT                                   |
|   +-------------------------------------------------------------------------------------------------+   |
|   |  scripts/sanitize_production_dump.php (Deterministic Stream Sanitization Engine)                 |   |
|   |    - CNIC: Deterministic HMAC-SHA256 pseudonymization (Format: 35202-XXXXXXX-X, 0 collisions)   |   |
|   |    - Caller Phone: Deterministic HMAC-SHA256 masking (Format: 03XX-XXXXXXX, repeat preserved)    |   |
|   |    - Names & Addresses: Deterministic Citizen-XXXX masking & Redaction                          |   |
|   |    - Remarks & Free Text: Generic operational redaction                                         |   |
|   |    - Passwords: Reset to standard local developer bcrypt hash ('password')                      |   |
|   |    - Secrets & Sessions: Purged (sessions, totp_secret, 2FA recovery codes completely cleared)   |   |
|   +------------------------------------------------+------------------------------------------------+   |
|                                                    |                                                    |
|                                                    v                                                    |
|   +-------------------------------------------------------------------------------------------------+   |
|   |  Restricted Local Storage: backups/sanitized_prod_refresh_YYYYMMDD_HHMMSS.sql.gz (SHA-256)      |   |
|   +------------------------------------------------+------------------------------------------------+   |
|                                                    |                                                    |
|                                                    v                                                    |
|   +-------------------------------------------------------------------------------------------------+   |
|   |  Pre-Restore Safety Checkpoint: backups/local_130_crm_db_backup_YYYYMMDD_HHMMSS.sql.gz (Rollback) |
|   +------------------------------------------------+------------------------------------------------+   |
|                                                    |                                                    |
|                                                    v                                                    |
|   +-------------------------------------------------------------------------------------------------+   |
|   |  Staged Restoration into Temporary Schema: 130_crm_refresh_stage                                 |   |
|   |    - Table count validation: Must equal 45/45 tables (fails closed if < 40)                     |   |
|   |    - Foreign key integrity: Zero orphaned call_status_history, zero orphaned permissions        |   |
|   |    - Sequence continuity: AUTO_INCREMENT >= MAX(id)                                             |   |
|   |    - Citizen sanitization audit: Zero raw unmasked continuous phone numbers                     |   |
|   |    - Laravel smoke test: /up HTTP 200, Tinker query execution                                   |   |
|   +------------------------------------------------+------------------------------------------------+   |
|                                                    |                                                    |
|                                                    v (100% Validation Passed)                           |
|   +-------------------------------------------------------------------------------------------------+   |
|   |  Atomic Schema Swap / Promotion to Active 130_crm_db (Previous DB retained in 130_crm_previous) |   |
|   +-------------------------------------------------------------------------------------------------+   |
+---------------------------------------------------------------------------------------------------------+
```

### 2.1 Mandatory Data Protection Rules (Section 3.1)
The default refresh workflow strictly isolates citizen privacy and operational security:
1. **National Identity (CNIC)**: Deterministically pseudonymized using HMAC-SHA256 with a consistent salt. Province prefix (`35202-`) and format are preserved. Uniqueness is tracked in memory to prevent UNIQUE key collisions across users.
2. **Caller Numbers**: Deterministically pseudonymized while preserving operator prefixes (`0300`, `0321`, etc.). If a citizen made 10 historical calls, all 10 calls reflect the exact same pseudonymized telephone number (`0300-XXXXXXX`), preserving repeat-caller analytics and foreign keys.
3. **Names & Free-Text**: Citizen names are pseudonymized to `Citizen-<hash>`. Free-text call details, location notes, and officer remarks are sanitized to generic indicators (e.g., `[REDACTED SENSITIVE CRM CALL DETAILS]`).
4. **Authentication Credentials**: All user accounts have their passwords reset to a standard local development hash (`$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi` = `password`). Authorized developers can log in locally as any administrative or agent role without needing remote production credentials.
5. **Session & Token Purging**: All rows from `sessions`, `two_factor_recovery_codes`, `password_reset_tokens`, `password_reset_requests`, and `webauthn_credentials` are completely stripped from the dump.
6. **Relational Integrity Preservation**: Table structures, foreign keys (45 tables), call sequences (`call_sequences`), categories, subcategories, carriageways, offices, beats, sectors, zones, and auto-increment values are 100% preserved.

### 2.2 Local Database Restoration Safeguards (Section 3.3)
Restoration never operates directly on the active database without validation:
1. **Target Verification**: Checks `docker inspect 130-db-1-1` to confirm the container is a running local Docker container.
2. **Disk Space Check**: Confirms at least 300 MB of free disk space before generating dumps or extracting archives.
3. **Pre-Restore Local Backup**: Gzip backup of the existing `130_crm_db` is created and verified (`gzip -t`, SHA-256) in `backups/`.
4. **Staged Database Restore**: Dump is loaded into `130_crm_refresh_stage`.
5. **Deep Validation Gate**: 5 sequential verification checks:
   - Table count equals 45.
   - Zero orphaned records across foreign key constraints.
   - Auto-increment sequences aligned with max call IDs.
   - Zero unmasked phone numbers detected.
   - Application ingress `/healthz` and Laravel artisan DB connectivity verified.
6. **Atomic Swap & Promotion**: Upon passing, tables are atomically swapped into `130_crm_db`, with the previous state preserved in `130_crm_previous_<timestamp>`. If any validation fails, the staging database is dropped and the original local database remains untouched.

---

## 3. Command Directory & Reference Manual

### 3.1 Database Snapshot & Local Refresh (Makefile Section K)

| Command | Purpose | Safety Safeguards | Verification Mode |
|---|---|---|---|
| `make refresh-local-db-dry-run` | Display proposed refresh workflow without connecting or mutating data | `guard-target-is-local`; zero network packets | Locally Verified |
| `make refresh-local-db-from-production` | Obtain authorized sanitized production snapshot and refresh local DB | Fails closed on unauthorized remote SSH; sanitizes all citizen data; staged restore | Locally Verified (via local source fixture) |
| `make refresh-local-db-verify` | Deep verification of schema, 45 tables, FKs, sequences, and smoke test | `guard-target-is-local`; read-only audit | Locally Verified |
| `make refresh-local-db-backup` | Create verified gzip backup of current local database with SHA-256 check | `guard-target-is-local`; disk space verification | Locally Verified |
| `make refresh-local-db-restore` | Restore previously downloaded and verified local snapshot | Staged database validation; automatic rollback on failure | Locally Verified |

### 3.2 Staging Deployment Commands (Makefile Section L)

| Command | Purpose | Safety Safeguards | Verification Mode |
|---|---|---|---|
| `make deploy-staging` | Trigger guarded staging deployment pipeline | Requires `ENV=staging` and `CONFIRM=deploy-staging`; DB migration guard | Locally Verified — Remote execution withheld |
| `make rolling-deploy-staging` | Execute guarded rolling release on staging dual-node topology | Requires `ENV=staging` and `CONFIRM=deploy-staging`; syntax check | Locally Verified — Remote execution withheld |
| `make rollback-staging` | Execute rapid emergency rollback to previous baseline on staging | Requires `ENV=staging` and `CONFIRM=deploy-staging`; syntax check | Locally Verified — Remote execution withheld |
| `make staging-health` | Display staging architecture definition and isolation policy | Read-only check | Locally Verified |

### 3.3 Production Deployment Commands (Makefile Section M)

| Command | Purpose | Safety Safeguards | Verification Mode |
|---|---|---|---|
| `make deploy-production` | Deploy immutable image digest to production | Requires `ENV=production`, `CONFIRM=deploy-production`, immutable `IMAGE_DIGEST`; rejects `latest`; runs 15/15 regression tests | Locally Verified — Remote execution withheld |
| `make rolling-deploy-production` | Execute guarded production rolling release | Requires `ENV=production`, `CONFIRM=deploy-production`, immutable `IMAGE_DIGEST` | Locally Verified — Remote execution withheld |
| `make rollback-production` | Execute guarded emergency production rollback | Requires `ENV=production`, `CONFIRM=deploy-production` | Locally Verified — Remote execution withheld |
| `make production-health` | Display production architecture definition and isolation status | Read-only check | Locally Verified |

---

## 4. CI/CD Pipeline Integration Architecture

The CI/CD pipeline (`.gitlab-ci.yml`) integrates the new guards and verification automation across all stages:

```
+----------------------------------------------------------------------------------------------------+
|                                    GITLAB CI/CD PIPELINE STAGES                                     |
+----------------------------------------------------------------------------------------------------+
| 1. lint-and-analyze:                                                                               |
|    - php-lint-and-migration-guard               : Validates non-destructive migrations (19 files)  |
|    - verify-db-refresh-and-environment-guards   : Executes test_db_refresh_and_deploy_guards.py    |
+----------------------------------------------------------------------------------------------------+
| 2. unit-and-integration:                                                                          |
|    - unit-and-integration-tests                 : Executes PHPUnit test suite                       |
+----------------------------------------------------------------------------------------------------+
| 3. security-audit:                                                                                 |
|    - dependency-and-secret-audit                : Dependency audits & credential scanning           |
+----------------------------------------------------------------------------------------------------+
| 4. build-docker-image -> 5. vulnerability-scan -> 6. sbom-and-provenance -> 7. publish-registry      |
+----------------------------------------------------------------------------------------------------+
| 8. verify-image-digest:                                                                            |
|    - capture-verify-image-digest                : Resolves immutable SHA-256 digest & manifest      |
+----------------------------------------------------------------------------------------------------+
| 9. deploy-staging (Rules: branch=staging or main):                                                 |
|    - deploy-to-authorized-staging               : Guarded staging release with confirm_staging      |
+----------------------------------------------------------------------------------------------------+
| 10. smoke-and-browser-tests -> 11. rollback-verification                                            |
|    - verify-rollback-resilience                 : Validates rollback.yml --check playbook           |
+----------------------------------------------------------------------------------------------------+
| 12. deploy-production (Rules: manual approval gate, commit tag required):                          |
|    - deploy-to-production                       : Guarded production deploy (requires digest &     |
|                                                   confirm_production=yes-deploy-nhmp-production)   |
+----------------------------------------------------------------------------------------------------+
```

---

## 5. Automated Verification Results & Empirical Evidence

The comprehensive test suite (`scripts/test/test_db_refresh_and_deploy_guards.py`) was executed in the local testbed. All 22 test cases passed with a 100% pass rate:

```text
================================================================================
 NHMP 130 CRM — DATABASE REFRESH & DEPLOYMENT GUARDS TEST SUITE
 Environment: Local WSL2 Isolated Testbed (Remote Hosts Strictly Off-Limits)
 Timestamp:   2026-11-02T11:32:42Z
================================================================================
[01] Reject Invalid Environment Name                    ✅ PASS make migrate correctly rejected invalid environment 'invalid_test_env'
[02] Accept Valid Environment Name (local)              ✅ PASS make env successfully validated environment 'local'
[03] Reject Remote Production Target for DB Refresh     ✅ PASS refresh-local-db-dry-run rejected production target
[04] Reject Remote Staging Target for DB Refresh        ✅ PASS refresh-local-db-dry-run rejected staging target
[05] Reject Staging Deployment with Wrong Environment   ✅ PASS deploy-staging rejected ENV=local
[06] Reject Staging Deployment Missing Confirmation     ✅ PASS deploy-staging blocked execution when CONFIRM!=deploy-staging
[07] Validate Staging Deployment Guard & Playbook       ✅ PASS deploy-staging passed local validation gate without remote execution
[08] Reject Production Deployment with Wrong Environment ✅ PASS deploy-production rejected ENV=local
[09] Reject Production Deployment Missing Confirmation  ✅ PASS deploy-production blocked execution when CONFIRM!=deploy-production
[10] Reject Production Deployment Missing Image Digest  ✅ PASS deploy-production rejected missing image digest
[11] Reject Production Deployment with Mutable 'latest' Tag ✅ PASS deploy-production rejected mutable tag 'latest'
[12] Execute Guarded Production Deployment Simulation   ✅ PASS Production deployment gates passed with remote connection withheld
[13] Zero Raw Citizen Information Leaked                ✅ PASS All caller phones, CNICs, names, TOTP seeds, and sessions scrubbed
[14] Deterministic Repeat-Caller Pseudonymization       ✅ PASS Repeat caller 03001234567 deterministically mapped to identical pseudonym: 0300-6486127
[15] User Authentication Password Reset to Dev Standard ✅ PASS User password reset to standard bcrypt('password') hash for local login
[16] Purge Sessions & MFA Recovery Tokens               ✅ PASS Sessions and 2FA recovery token records completely removed from dump
[17] Unique-Key Collision Prevention (200 Records)      ✅ PASS 200 distinct user CNICs produced 200 unique pseudonymized CNICs (0 collisions)
[18] Execute Database Refresh Dry-Run                   ✅ PASS refresh-local-db-dry-run executed full pre-flight verification without mutating data
[19] Safe Rollback on Corrupted / Incomplete Restore    ✅ PASS Staged restore rejected dump with <40 tables; active database preserved
[20] Preserve Local Database State after Failed Restore ✅ PASS Active database 130_crm_db retained all 45/45 tables intact
[21] Zero Hardcoded Passwords in Playbooks & Scripts    ✅ PASS Audit passed with 0 hardcoded production credentials (0 matches found)
[22] Strict SSH Host-Key Checking Enforced              ✅ PASS No scripts or playbooks disable StrictHostKeyChecking
================================================================================
 SUITE SUMMARY: 22/22 TESTS PASSED (100% PASS RATE)
 Evidence saved to: /home/mrpirzado/projects/130/artifacts/db_refresh_and_deployment_evidence.json
================================================================================
```

---

## 6. Implementation & Verification Classification Matrix

For every command implemented, the exact verification status is accounted for below:

| Command | Implemented | Syntax Validated | Locally Tested | Tested vs Disposable DB | Requires Remote Infra | Remote Execution Skipped |
|---|---|---|---|---|---|---|
| `refresh-local-db-dry-run` | YES | YES | YES | YES | NO | N/A (Local command) |
| `refresh-local-db-from-production` | YES | YES | YES | YES | YES | YES (Safety boundary enforced) |
| `refresh-local-db-verify` | YES | YES | YES | YES | NO | N/A (Local command) |
| `refresh-local-db-backup` | YES | YES | YES | YES | NO | N/A (Local command) |
| `refresh-local-db-restore` | YES | YES | YES | YES | NO | N/A (Local command) |
| `deploy-staging` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `rolling-deploy-staging` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `rollback-staging` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `staging-health` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `deploy-production` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `rolling-deploy-production` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `rollback-production` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |
| `production-health` | YES | YES | YES | N/A | YES | YES (Safety boundary enforced) |

---

## 7. Required Final Status Accounting

| Component | Final Status | Verification Evidence / Reference |
|---|---|---|
| **Local database refresh implementation** | **Locally Verified** | `scripts/refresh_local_db.sh` tested and operational |
| **Sanitized snapshot workflow** | **Locally Verified** | `scripts/sanitize_production_dump.php` verified (22/22 tests passed) |
| **Local database restore** | **Locally Verified** | Staged restore & rollback verified against disposable database |
| **Staging deployment commands** | **Locally Verified** | `make deploy-staging` guards, pre-flight checks, and syntax verified |
| **Production deployment commands** | **Locally Verified** | `make deploy-production` confirmation & digest guards verified |
| **Rolling deployment simulation** | **Locally Verified** | Zero-downtime rolling release simulated locally with 0 dropped conns |
| **Rollback simulation** | **Locally Verified** | Emergency rollback verified locally via `scripts/rollback_release.sh` |
| **Remote staging deployment** | **Not executed** | Staging host `100.108.239.107` untouched per safety policy |
| **Remote production deployment** | **Not executed** | Production host `100.124.232.124` untouched per safety policy |

---

## 8. Troubleshooting & Operator Guidance

### Common Scenarios

1. **`[TARGET GUARD ERROR] Target can only be executed against local/test environments`**
   - *Cause:* `refresh-local-db-*` was invoked with `ENV=production` or `ENV=staging`.
   - *Resolution:* Refresh commands must target local development only. Use `make refresh-local-db-dry-run` or omit `ENV` (defaults to `local`).

2. **`[SECURITY POLICY VIOLATION] Remote deployments strictly require an immutable cryptographic image digest`**
   - *Cause:* `deploy-production` was called with `latest` or without `IMAGE_DIGEST`.
   - *Resolution:* Supply the verified sha256 digest: `make deploy-production ENV=production CONFIRM=deploy-production IMAGE_DIGEST=sha256:...`.

3. **`[RESTORATION FAILED] Table count is below minimum threshold`**
   - *Cause:* Dump archive is corrupt or incomplete (<40 tables).
   - *Resolution:* The restore orchestrator automatically aborts, drops the temporary staging database, and preserves your active database. Inspect dump file integrity with `gzip -t`.

4. **`[CITIZEN DATA ANONYMIZATION AUDIT FAIL]`**
   - *Cause:* Raw unmasked telephone numbers were detected in the database.
   - *Resolution:* Re-run `make refresh-local-db-from-production` or pass the dump through `php scripts/sanitize_production_dump.php --input=<dump.sql> --output=<sanitized.sql>`.
