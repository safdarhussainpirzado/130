# NHMP 130 Helpline — Production Release Blockers & Administrator Gate (Audited)

**Document ID:** `PRODUCTION_RELEASE_BLOCKERS.md`  
**Revision:** v3.0 (Authoritative Release Gate Audit)  
**Audit Date:** October 29, 2026 — 20:30:00 PKT  
**Target Host:** Staging Server (`100.108.239.107`)  
**Operating Boundary:** **STAGING ONLY. Production (`100.124.232.124`) is STRICTLY OFF-LIMITS, UNMODIFIED, and UNTOUCHED.**  
**Release Authorization Status:** **NOT READY — PRODUCTION DEPLOYMENT IS STRICTLY PROHIBITED AND NOT AUTHORIZED.**

---

## 1. Executive Summary & Release Gate Verdict

Pursuant to the final evidence audit rules:
1. **Current Release Status:** **NOT READY. Production deployment is prohibited.**
2. **Major Progress Verified:**
   - Host SSH port 22 interception resolved by administrator out-of-band console execution.
   - Safe disposable MySQL 8.4 database restoration executed and verified (362MB dump restored in 86s with zero errors, then cleaned up).
   - Targeted regression suite (12 files, 27 tests, 152 assertions) verified passing with exit code 0.
   - Call sequence unindexed table scan and unbounded loop eliminated.
   - Repository dependency lockfile (`composer.lock`) audit clean (0 CVEs).
   - HAProxy credentials decoupled from configuration.
3. **Active Release Blockers Remaining:**
   - Full repository test suite has 13 failures across 4 legacy test files.
   - Running container runtime has not been rebuilt and still contains unpatched package versions.
   - Edge services (`haproxy`, `web-1`, `web-2`) are stopped and require scheduled restart.
   - Candidate container image rebuild and full database migration execution require an approved staging maintenance window.

---

## 2. Release Blocker Status Register

| Blocker ID | Release Domain | Verification Requirement | Observed Evidence | Status |
|---|---|---|---|---|
| **BLK-01** | **Host Network / SSH** | External SSH access restored without TLS redirection | Port 22 reachable, SSH-2.0 banner confirmed, remote commands execute cleanly | **PASS (Resolved)** |
| **BLK-02** | **Disaster Recovery** | Successful database restore into MySQL 8.4 instance | 66MB compressed dump restored in disposable container in 86s with 0 errors | **PASS (Resolved)** |
| **BLK-03** | **Targeted Regression Suite** | Critical business flows & sequence tests pass | 27 tests across 12 files passed in `130-app-1-1` (152 assertions, 47.036s) | **PASS (Resolved)** |
| **BLK-04** | **Call Sequencing Code** | Atomic sequence allocation without table scan | Fast-path atomic write-PDO increment, transactional init, bounded 3-retry loop | **PASS (Resolved)** |
| **BLK-05** | **Dependency Audit (Lockfile)**| `composer audit --locked` exits with 0 CVEs | Upgraded `symfony/yaml` (7.4.22), `mime` (7.4.22), `dompdf` (3.1.6); exits 0 | **PASS (Resolved)** |
| **BLK-06** | **HAProxy Credentials (Config)**| Secrets removed from tracked configuration | Fallback default password purged from `haproxy.cfg`; env injection enforced | **PASS (Resolved)** |
| **BLK-07** | **Operational Data Protection**| Redact citizen PII and operational secrets | PII and credentials redacted across all logs, queries, and audit documents | **PASS (Resolved)** |
| **BLK-08** | **Complete Test Suite Gate** | All 24 test files execute and pass | 13 failures across 4 legacy files (`AuthenticationTest`, `TwoFactorSecurityTest`, `EmailVerificationTest`, `PasswordExpiryAndResetTest`) | **FAIL / BLOCKED** |
| **BLK-09** | **Runtime Dependency State** | Running container contains patched packages | `130-app-1-1` vendor directory still contains unpatched versions (`v3.1.5`, `v7.4.19`, `v7.4.8`) | **NOT VERIFIED / PENDING REBUILD** |
| **BLK-10** | **Container Image Rebuild** | Rebuild immutable candidate container image | Candidate build and container recreation pending administrator rollout | **BLOCKED / PENDING DEPLOYMENT** |
| **BLK-11** | **HAProxy Live Auth** | Authenticated request to `/monitor` succeeds | Container `130-haproxy-1` is in Exited state on staging host | **NOT VERIFIED** |
| **BLK-12** | **Full Concurrency Benchmark** | Verify throughput under parallel process load | Unsupported 1,500 req/s claims retracted; multi-process load test unexecuted | **NOT VERIFIED** |
| **BLK-13** | **Staging Database Migrations** | Execute additive migrations on staging database| Clean migrations created; pending execution on 709k-row table in staging window | **BLOCKED / PENDING WINDOW** |

---

## 3. Staging-Only Administrator Action List

The following procedure must be executed exclusively on the authorized staging host (`100.108.239.107`):

### Phase 1: Legacy Test Suite Reconciliation
1. **Prerequisite:** Staging container `130-app-1-1` active.
2. **Action:**
   - In `UserFactory.php`: Remove `email_verified_at` assignment or align `EmailVerificationTest.php` with internal CRM architecture.
   - In `PasswordExpiryAndResetTest.php`: Pass CSRF token on password update POST requests, adjust redirect assertions for role-based dashboard redirection, and update session status keys.
   - In `AuthenticationTest.php` and `TwoFactorSecurityTest.php`: Align route assertions with canonical `/login` authentication challenge redirects.
3. **Expected Outcome:** `docker exec 130-app-1-1 ./vendor/bin/phpunit` executes 100% of repository tests with exit code 0.
4. **Rollback Step:** `git checkout tests/` to revert test modifications.

### Phase 2: Candidate Container Rebuild & Edge Service Startup
1. **Prerequisite:** Verified `composer.lock` with 0 CVEs on staging host.
2. **Action:**
   ```bash
   cd /home/mrpirzado/projects/130
   # Build candidate image without modifying running containers
   docker compose build app-1 app-2
   # Start edge services
   docker compose up -d web-1 web-2 haproxy
   ```
3. **Expected Outcome:**
   - Candidate image built with `dompdf v3.1.6`, `symfony/mime v7.4.22`, `symfony/yaml v7.4.22`.
   - `130-web-1-1`, `130-web-2-1`, and `130-haproxy-1` status: `Up`.
4. **Rollback Step:**
   ```bash
   docker compose stop haproxy web-1 web-2
   ```

### Phase 3: HAProxy Statistics Authentication Verification
1. **Prerequisite:** `130-haproxy-1` running.
2. **Action:**
   ```bash
   # Test unauthenticated access (must return 401)
   curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8404/monitor
   # Test authenticated access using runtime environment secret
   curl -s -o /dev/null -w "%{http_code}" -u "${HAPROXY_STATS_USER}:${HAPROXY_STATS_PASSWORD}" http://127.0.0.1:8404/monitor
   ```
3. **Expected Outcome:** Unauthenticated request yields `401`; authenticated request yields `200`.

### Phase 4: Database Migration Execution
1. **Prerequisite:** Verified backup archive on staging host.
2. **Action:**
   ```bash
   docker exec 130-app-1-1 php artisan migrate --force
   ```
3. **Expected Outcome:**
   - `2026_10_29_010000_create_call_sequences_table` marked Ran.
   - `2026_10_29_020000_add_caller_history_composite_index_to_calls_table` adds `idx_calls_caller_history` to `calls` table.
4. **Rollback Step:**
   ```bash
   docker exec 130-app-1-1 php artisan migrate:rollback --step=2
   ```

---

## 4. Concise List of Actions Still Requiring Administrator Access

1. **Authorize and apply legacy test fixture refactoring** across the 4 failing test files.
2. **Rebuild staging application containers** (`docker compose build app-1 app-2`) to bake updated `composer.lock` dependencies into runtime.
3. **Restart edge proxy services** (`docker compose up -d web-1 web-2 haproxy`) and verify loopback stats authentication.
4. **Execute database migrations** on the active staging database during a scheduled maintenance window.
5. **Conduct final staging smoke test** and formally review release readiness.

**PRODUCTION DEPLOYMENT PROHIBITION: Production server `100.124.232.124` remains strictly off-limits. Do not deploy.**
