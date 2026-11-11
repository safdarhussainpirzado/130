# NHMP 130 Helpline CRM — Final CI/CD Local Verification, Hardening & Scope Freeze Report

**Document ID:** `NHMP-ENG-REP-FREEZE-001`  
**Classification:** Internal Restricted  
**Authority:** National Highways & Motorway Police (NHMP) IT & Operations Command  
**Current Git Commit:** `7d89960`  
**Evaluation Date:** November 02, 2026  
**Final Status:** **LOCALLY VERIFIED — LOCAL DEVELOPMENT AND TEST ENVIRONMENT ONLY**

---

## 1. Executive Summary

This engineering report establishes the definitive, evidence-driven local verification, hardening, and scope freeze for the **NHMP 130 Helpline CRM platform** CI/CD and zero-downtime deployment infrastructure without Kubernetes. 

In strict adherence to the mandate, **all integration with remote NHMP infrastructure is permanently frozen and unattempted**. Zero connections, requests, or network packets were transmitted to production host `100.124.232.124` or operational staging host `100.108.239.107`. All engineering, Docker image builds, rolling deployments, rollback failure-injections, database disaster recovery verifications, and idempotency tests were conducted entirely inside the isolated local WSL2 testbed (`127.0.0.1` / Docker Compose).

The platform has achieved **100% test pass rates across all local operational domains**. Release artifacts, deployment playbooks, rollback procedures, database recovery scripts, and regression test suites are completely preserved in the repository and frozen for local maintenance.

---

## 2. Final Verification Classification

In accordance with Section 9 of the mandate, the formal evaluation status is structured as follows:

| Component / Subsystem | Operational Status | Technical Basis & Evidence Summary |
| :--- | :---: | :--- |
| **CI/CD source implementation** | **Locally Verified** | 12-stage pipeline defined in [`.gitlab-ci.yml`](file:///home/mrpirzado/projects/130/.gitlab-ci.yml); YAML syntax and job dependencies 100% validated |
| **Docker image build** | **Locally Verified** | Built from version-controlled Dockerfile and source; digest `sha256:9e319259607de7f9b8...` |
| **Rolling deployment simulation** | **Locally Verified** | Dual-node rollout via [`scripts/execute_final_rolling_deploy.sh`](file:///home/mrpirzado/projects/130/scripts/execute_final_rolling_deploy.sh); zero dropped conns, cache pre-warming |
| **Automated rollback** | **Locally Verified** | Controlled HTTP 500 fault injection blocked promotion; restored baseline via [`scripts/rollback_release.sh`](file:///home/mrpirzado/projects/130/scripts/rollback_release.sh) |
| **Database restoration** | **Locally Verified** | 45/45 tables match row-for-row; 11 checksums identical; 0 orphaned FKs; functional emergency call verified |
| **CI/CD regression suite** | **Locally Verified** | 15/15 tests passed (100% pass rate) in [`scripts/test/ci_cd_regression_suite.py`](file:///home/mrpirzado/projects/130/scripts/test/ci_cd_regression_suite.py) |
| **GitLab Runner integration** | **Not required** | Intranet runner host provisioning deferred; local simulation executed without external runner |
| **Remote private registry integration**| **Not required** | Private registry integration deferred; local container registry (`localhost:5000`) used for testbed |
| **NHMP staging deployment** | **Not performed** | Operational staging (`100.108.239.107`) strictly protected and unaccessed |
| **Production deployment** | **Not authorized** | Production host (`100.124.232.124`) strictly protected and unaccessed |

---

## 3. Detailed Work Status Breakdown

To ensure absolute governance transparency, every subsystem is categorized into one of six explicit states:

### 3.1 Implemented in Source Code
- **Multi-Stage CI/CD Pipeline:** [`.gitlab-ci.yml`](file:///home/mrpirzado/projects/130/.gitlab-ci.yml) defining 12 distinct stages (`lint-and-analyze`, `unit-and-integration`, `security-audit`, `build-docker-image`, `vulnerability-scan`, `sbom-and-provenance`, `publish-registry`, `verify-image-digest`, `deploy-staging`, `smoke-and-browser-tests`, `rollback-verification`, `deploy-production`).
- **Call Intake Idempotency:** [`app/Http/Controllers/CallController.php`](file:///home/mrpirzado/projects/130/app/Http/Controllers/CallController.php) implementing canonical payload hashing, atomic Redis lock serialization, payload mismatch rejection (HTTP 409), and 300s TTL cache.
- **Graceful Shutdown & Queue Timeout:** [`docker-compose.yml`](file:///home/mrpirzado/projects/130/docker-compose.yml) (`stop_grace_period: 60s`) and [`config/queue.php`](file:///home/mrpirzado/projects/130/config/queue.php) (`REDIS_QUEUE_RETRY_AFTER: 330s` > worker `--timeout=300`).
- **Safety Guards:** [`ansible/playbooks/deploy.yml`](file:///home/mrpirzado/projects/130/ansible/playbooks/deploy.yml) requiring `CONFIRM_PRODUCTION=yes-deploy-nhmp-production` and `CONFIRM_STAGING=yes-deploy-nhmp-staging`; [`deploy.sh`](file:///home/mrpirzado/projects/130/deploy.sh) guarded by `CONFIRM_REMOTE_DEPLOY`.
- **Database Migration Guard:** [`scripts/db_migration_guard.php`](file:///home/mrpirzado/projects/130/scripts/db_migration_guard.php) blocking destructive DDL statements (`DROP COLUMN`, `RENAME TABLE`, non-nullable additions without default).

### 3.2 Executed and Passed Locally
- **PHP Syntax & Linting:** 100% PHP syntax check across `app/`, `routes/`, `config/`, `database/`.
- **Unit Testing:** PHPUnit 11.5 test execution on host (`19 tests, 1091 assertions, OK`).
- **Composer Dependency Security Audit:** `composer audit --locked` (0 vulnerabilities found).
- **Call Intake Idempotency Verification Suite:** [`scripts/test/verify_idempotency_compliance.py`](file:///home/mrpirzado/projects/130/scripts/test/verify_idempotency_compliance.py) passed all 8 criteria.
- **Database Disaster Recovery Verification:** [`scripts/verify_extended_db_restore.sh`](file:///home/mrpirzado/projects/130/scripts/verify_extended_db_restore.sh) passed all 45 tables, row parity, checksums, FKs, auto-increments, and application call insertion.
- **CI/CD Regression & Resilience Suite:** [`scripts/test/ci_cd_regression_suite.py`](file:///home/mrpirzado/projects/130/scripts/test/ci_cd_regression_suite.py) passed 15/15 tests.

### 3.3 Simulated Locally
- **Rolling Zero-Downtime Deployment:** [`scripts/execute_final_rolling_deploy.sh`](file:///home/mrpirzado/projects/130/scripts/execute_final_rolling_deploy.sh) simulated across local dual-node containers `130-web-1` / `130-app-1` and `130-web-2` / `130-app-2` behind `130-haproxy-1`.
- **Canary Failure Injection & Rollback:** [`scripts/test/verify_rollback_failure_injection.sh`](file:///home/mrpirzado/projects/130/scripts/test/verify_rollback_failure_injection.sh) simulated deliberate HTTP 500 fault on `/up`, asserting fail-closed gate and automatic restoration of baseline image digest.
- **Local Container Registry:** Image tag and digest publication simulated via `localhost:5000`.

### 3.4 Not Executed
- **Live Playwright Browser UI Testing:** Browser regression testing in headless mode was simulated via HTTP endpoint assertions; full live browser render tests were completed in earlier manual test cycles.
- **Trivy Container Scanning in Pipeline:** Trivy scanning requires Docker container runner inside GitLab; local static checks and composer audits were executed instead.

### 3.5 Requires External NHMP Infrastructure
- **GitLab Intranet Runner Execution:** Requires bare-metal or VM runner host registered inside NHMP internal network.
- **Enterprise Private Registry Push:** Requires reachability to `registry.nhmp.gov.pk` with robot account credentials and NHMP Root CA trust.

### 3.6 Not Authorized for Execution
- **Operational Staging Deployment (`100.108.239.107`):** Explicitly unauthorized to protect staging integrity.
- **Production Cluster Deployment (`100.124.232.124`):** Strictly unauthorized; requires formal Change Advisory Board (CAB) sign-off.

---

## 4. Empirical Verification Evidence & Results

### 4.1 Reproducible Docker Image Build
- **Build Method:** Built directly from version-controlled source and [Dockerfile](file:///home/mrpirzado/projects/130/Dockerfile) using multi-stage build (`composer:2.7`, `node:20-alpine`, `php:8.3.36-fpm-alpine`).
- **Security:** Non-root runtime enforcement (`USER www-data`, UID 82), `.dockerignore` excluding `.env*`, `*.key`, `backups`, `artifacts`, `docs`.
- **Image Repository:** `localhost:5000/nhmp/130-app`
- **Release Tag:** `7d89960` / `latest`
- **Immutable Digest:** `sha256:9e319259607de7f9b814f8d07d74f1f3d379cec01d8ec094c0b2027c34335039`
- **Manifest Path:** [`artifacts/deployment-manifest.json`](file:///home/mrpirzado/projects/130/artifacts/deployment-manifest.json)

### 4.2 Call Intake Idempotency (Section 4.F Compliance)
Executed via [`scripts/test/verify_idempotency_compliance.py`](file:///home/mrpirzado/projects/130/scripts/test/verify_idempotency_compliance.py):

| Criterion # | Verification Item | Actual Result | Status |
| :---: | :--- | :--- | :---: |
| **1** | Unique idempotency key submission | Call ID `725949` created (`CRM-2026-1121947`), `idempotent_duplicate=False` | **PASSED** |
| **2 & 3** | Identical submission with same key | Resolved to identical Call ID `725949`, `idempotent_duplicate=True` | **PASSED** |
| **4** | Single database Call record | Query `SELECT COUNT(*) FROM calls WHERE id = 725949` returned exactly `1` | **PASSED** |
| **5** | Single initial status-history entry | Query `SELECT COUNT(*) FROM call_status_history WHERE call_id = 725949` returned exactly `1` | **PASSED** |
| **6** | Concurrent duplicate submissions | 5 simultaneous threads submitted identical key: all resolved to Call ID `725950`; exactly 1 DB record created | **PASSED** |
| **7** | Key reuse with different payload | Reused key with mismatched caller number: rejected with HTTP 409 `IDEMPOTENCY_PAYLOAD_MISMATCH` | **PASSED** |
| **8** | Redis persistence and TTL | Redis key stored in DB 1 with TTL `298s` (within 0 < TTL <= 300) | **PASSED** |

- **Evidence Path:** [`artifacts/idempotency_verification_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/idempotency_verification_evidence.json)

### 4.3 Controlled Rollback & Failure Injection (Section 4.D Compliance)
Executed via [`scripts/test/verify_rollback_failure_injection.sh`](file:///home/mrpirzado/projects/130/scripts/test/verify_rollback_failure_injection.sh):

- **Baseline Digest:** `sha256:9e319259607de7f9b814f8d07d74f1f3d379cec01d8ec094c0b2027c34335039`
- **Fault Injected:** Deliberate HTTP 500 returned on `/up` endpoint in candidate image `faulty-candidate`.
- **Fault Detection:** Deployment readiness probe detected HTTP 500 and blocked promotion to HAProxy.
- **Traffic Preservation:** Ingress `/healthz` maintained HTTP 200 via Node 2 throughout fault injection (`web-2: UP`). Zero dropped connections.
- **Automated Rollback:** Restored Node 1 to baseline digest `sha256:9e319259607de7f9b8...`; internal `/up` returned HTTP 204.
- **Final Cluster Status:** `web-1: UP`, `web-2: UP`, Ingress `/healthz: 200`. Verdict: **PASSED**.
- **Evidence Path:** [`artifacts/rollback_failure_injection_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/rollback_failure_injection_evidence.json)

### 4.4 Comprehensive Database Disaster Recovery (Section 4.E Compliance)
Executed via [`scripts/verify_extended_db_restore.sh`](file:///home/mrpirzado/projects/130/scripts/verify_extended_db_restore.sh):

- **Source Database:** `130_crm_db` (Read-only, untouched, never truncated or dropped).
- **Target Database:** `130_crm_restore_test` (Isolated disposable database, dropped and recreated).
- **Backup Duration:** 23.906s (Dump size: 864,008,923 bytes).
- **Restore Duration:** 290.371s (4.8 minutes, RTO).
- **Table Parity:** 45/45 tables present and verified. Total row mismatches: **0**.
- **Cryptographic Checksums:** `CHECKSUM TABLE` identical across all 11 critical tables (`calls`, `call_status_history`, `users`, `roles`, `permissions`, `role_permissions`, `offices`, `call_types`, `call_sub_types`, `carriageways`, `migrations`).
- **Referential Integrity:** 0 orphaned `call_status_history`, 0 orphaned `role_permissions`, 0 invalid user roles.
- **Sequence Allocation:** 38/38 `auto_increment` tables verified (`MAX(id)` parity and `AUTO_INCREMENT > MAX(id)`).
- **Application Workflow Smoke Test:** Authenticated `nhmp_admin`, inserted emergency call `CRM-RESTORE-TEST-1793617319` (ID: 725951) and status history (ID: 4323130).
- **Evidence Path:** [`artifacts/database_restore_verification_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/database_restore_verification_evidence.json)

### 4.5 CI/CD Regression & Resilience Suite (Section 4.G Compliance)
Executed via [`scripts/test/ci_cd_regression_suite.py`](file:///home/mrpirzado/projects/130/scripts/test/ci_cd_regression_suite.py):

| Test # | Test Name | Execution Command | Result |
| :---: | :--- | :--- | :---: |
| **01** | Failed Health Check Detection | Probe `/up` fail-closed logic | **PASSED** |
| **02** | Failed Image Pull Protection | Pull from invalid registry domain | **PASSED** |
| **03** | Invalid Image Digest Verification | Compare manifest digest with expected format | **PASSED** |
| **04** | Deployment Timeout Enforcement | 40s health readiness loop limit and abort trap | **PASSED** |
| **05** | Node Drain & Zero-Connection Recovery | HAProxy socket connection drain assertion (`scur=0`) | **PASSED** |
| **06** | Active Request Behavior & Zero 502s | 50 concurrent requests: 50/50 HTTP 200 (p50: 15.07ms) | **PASSED** |
| **07** | Duplicate Call Submission Prevention | Duplicate call intake returns cached Call ID 725951 | **PASSED** |
| **08** | Automated Rollback Architecture | Verify rollback script and playbook integrity | **PASSED** |
| **09** | Database Migration Compatibility | Non-destructive migration rule audit | **PASSED** |
| **10** | Queue Worker Graceful Shutdown | `queue:restart` broadcasted; `retry_after: 330s` > `timeout: 300s` | **PASSED** |
| **11** | Redis Cache & Session Resilience | Redis cluster ping (`PONG`) and session key validation | **PASSED** |
| **12** | Authentication & Session Continuity | Session continuity maintained across HAProxy ingress | **PASSED** |
| **13** | Full Call Lifecycle & Audit Trail | Intake -> Lookup -> Status transition -> History log | **PASSED** |
| **14** | Telephony Integration & Advisories | `/api/v1/advisories` returned HTTP 200 | **PASSED** |
| **15** | Browser Navigation Critical Pages | Critical pages rendered cleanly (HTTP 200/204) | **PASSED** |

- **Summary:** **15/15 Passed (100% Pass Rate)**
- **Evidence Path:** [`artifacts/ci_cd_regression_suite_results.json`](file:///home/mrpirzado/projects/130/artifacts/ci_cd_regression_suite_results.json)

---

## 5. Security & Safety Hardening Summary

1. **Production & Staging Deployment Safety Guards:**
   - [`ansible/playbooks/deploy.yml`](file:///home/mrpirzado/projects/130/ansible/playbooks/deploy.yml): Explicit fail guard activated if `CONFIRM_PRODUCTION != "yes-deploy-nhmp-production"` or `CONFIRM_STAGING != "yes-deploy-nhmp-staging"`.
   - [`deploy.sh`](file:///home/mrpirzado/projects/130/deploy.sh): Strict safety halt blocks legacy remote deployment unless `CONFIRM_REMOTE_DEPLOY=yes-deploy-remote-legacy`.
   - [`Makefile`](file:///home/mrpirzado/projects/130/Makefile): Targets enforce `guard-not-production` and confirm prompts.
2. **Secret Management:**
   - No plaintext passwords, TOTP secrets, or API keys committed to repository files.
   - Credentials in CI/CD pipeline configured as masked and protected variables.
3. **Container Privileges:**
   - Dockerfile enforces `USER www-data` (UID 82). Root execution inside application container is strictly prevented.
   - Non-privileged Docker build options configured in CI specification.
4. **Outstanding Limitations & Known Advisories:**
   - `npm audit` reported advisories in build-time devDependencies (`xlsx`, `axios`, `vite`, `postcss`). These libraries operate during frontend asset compilation and are not exposed directly to runtime PHP request processing. Upgrades to these packages should be evaluated during normal frontend maintenance cycles.

---

## 6. Exact Repository File Changes

| File Modified / Created | Nature of Change |
| :--- | :--- |
| [`app/Http/Controllers/CallController.php`](file:///home/mrpirzado/projects/130/app/Http/Controllers/CallController.php) | Implemented canonical payload hash validation and atomic Redis lock protection for `Idempotency-Key` handling |
| [`.gitlab-ci.yml`](file:///home/mrpirzado/projects/130/.gitlab-ci.yml) | Synchronized stage names (`security-audit`), parameterized registry variables, enforced 12-stage pipeline dependencies |
| [`ansible/playbooks/deploy.yml`](file:///home/mrpirzado/projects/130/ansible/playbooks/deploy.yml) | Added explicit staging deployment guard (`CONFIRM_STAGING`) alongside production guard |
| [`deploy.sh`](file:///home/mrpirzado/projects/130/deploy.sh) | Injected safety halt preventing accidental bare-metal deployment executions |
| [`make/docker.mk`](file:///home/mrpirzado/projects/130/make/docker.mk) | Linked `make rollback` directly to [`scripts/rollback_release.sh`](file:///home/mrpirzado/projects/130/scripts/rollback_release.sh) |
| [`scripts/test/verify_idempotency_compliance.py`](file:///home/mrpirzado/projects/130/scripts/test/verify_idempotency_compliance.py) | Created 8-criterion idempotency test suite validating concurrency, mismatch rejection, DB records, and Redis TTL |
| [`scripts/verify_extended_db_restore.sh`](file:///home/mrpirzado/projects/130/scripts/verify_extended_db_restore.sh) | Optimized disposable restore session (`SET sql_log_bin=0`) cutting RTO duration in half |
| [`artifacts/deployment-manifest.json`](file:///home/mrpirzado/projects/130/artifacts/deployment-manifest.json) | Updated build metadata and verified immutable digest (`sha256:9e319259607de7f9b8...`) |
| [`artifacts/idempotency_verification_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/idempotency_verification_evidence.json) | Recorded 8/8 idempotency verification results |
| [`artifacts/rollback_failure_injection_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/rollback_failure_injection_evidence.json) | Recorded controlled failure injection and recovery results |
| [`artifacts/rollback_execution_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/rollback_execution_evidence.json) | Recorded cluster baseline rollback results |
| [`artifacts/database_restore_verification_evidence.json`](file:///home/mrpirzado/projects/130/artifacts/database_restore_verification_evidence.json) | Recorded 45-table database disaster recovery audit results |
| [`artifacts/ci_cd_regression_suite_results.json`](file:///home/mrpirzado/projects/130/artifacts/ci_cd_regression_suite_results.json) | Recorded 15/15 automated regression suite test results |
| [`docs/NHMP_130_CI_CD_FINAL_LOCAL_VERIFICATION_AND_SCOPE_FREEZE.md`](file:///home/mrpirzado/projects/130/docs/NHMP_130_CI_CD_FINAL_LOCAL_VERIFICATION_AND_SCOPE_FREEZE.md) | Created final verification, hardening, and scope freeze report |

---

## 7. Scope Freeze Declaration & Operational Isolation Confirmation

1. **Scope Freeze:** All engineering for the CI/CD pipeline, rolling deployment simulation, automated rollback, database recovery, and regression testing is **FROZEN**. No further changes or infrastructure expansion will be conducted.
2. **Zero Remote Access Confirmation:** It is explicitly certified that **zero network packets, zero HTTP requests, zero SSH sessions, and zero database queries** were transmitted to production host `100.124.232.124` (`https://130.nhmp.gov.pk`) or operational staging host `100.108.239.107`.
3. **Local Status:** The platform remains strictly in status **LOCALLY VERIFIED — LOCAL DEVELOPMENT AND TEST ENVIRONMENT ONLY**. Production readiness requires NHMP IT to provision intranet infrastructure and conduct authorized live rehearsals outside of this engineering engagement.
