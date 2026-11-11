# NHMP 130 Helpline — Final Discrepancy Reconciliation Register (Audited & Reconciled)

**Document ID:** `FINAL_DISCREPANCY_REGISTER.md`  
**Revision:** v3.0 (Authoritative Release Gate Audit)  
**Audit Timestamp:** October 29, 2026 — 20:30:00 PKT  
**Target Host:** Staging Server (`100.108.239.107`)  
**Standard of Proof:** Authoritative registry API manifests, verified command outputs, exit codes, git blame/log history, and container runtime inspections.  
**Operating Boundary:** **STAGING ONLY. Production (`100.124.232.124`) is STRICTLY OFF-LIMITS, UNMODIFIED, and UNTOUCHED.**

---

## 1. Executive Summary & Forensic Reconciliation

This register establishes the verified status of all remediation claims across NHMP 130 Helpline, backed by empirical command outputs, exit codes, and runtime evidence:

1. **Firewall Interception Resolved (PASS):**  
   The in-memory NAT PREROUTING redirect rule that intercepted port 22 on `100.108.239.107` was cleared by the administrator via out-of-band console (`iptables -t nat -I PREROUTING 1 -p tcp --dport 22 -j ACCEPT && iptables-save > /etc/iptables/rules.v4`). Port 22 connectivity and SSH banner (`SSH-2.0-OpenSSH_8.9p1 Ubuntu-3ubuntu0.18`) have been empirically verified.
2. **Disposable MySQL 8.4 Restoration Proven (PASS):**  
   The baseline compressed backup `/home/mrpirzado/projects/130/backups/staging_baseline_20261029.sql.gz` (66 MB, SHA-256 `fe6e730ec20fb94760725265b08529d0da8c5757d8ad18a01072c41787b2c17a`) was restored into an ephemeral, isolated MySQL 8.4 container (`130-disposable-restore`). The 362MB uncompressed dump imported in **86 seconds** without SQL errors, and the ephemeral container was completely cleaned up via trap.
3. **Security Advisory Count Reconciled (6 vs 8 CVEs):**  
   The discrepancy between "6 CVEs resolved" and 8 CVE identifiers in earlier reports has been resolved. Standard Composer CLI text output grouped vulnerabilities by package, displaying 6 warning boxes. Comprehensive JSON querying identifies exactly **8 CVE identifiers** across `symfony/yaml` (4), `symfony/mime` (1), and `dompdf/dompdf` (3). While `composer.lock` is patched and `composer audit --locked` exits with code 0, inspection of running container `130-app-1-1` proves the running application still contains `dompdf/dompdf: v3.1.5`, `symfony/mime: v7.4.19`, and `symfony/yaml: v7.4.8`. Runtime remediation remains **PENDING REBUILD**.
4. **Call Sequence Concurrency Hardened & Throughput Claims Retracted:**  
   The unindexed 709k-row scan in `CallService::generateCallNumber()` has been replaced with an atomic `LAST_INSERT_ID(current_seq + 1)` query bound directly to the primary/write PDO. Lazy double-checked transactional initialization prevents rollover races, and duplicate-key retry is strictly bounded to 3 attempts. Unsubstantiated throughput claims (e.g. 1,500 req/s, sub-0.1ms) have been retracted and marked **UNVERIFIED**.
5. **Test Suite Gate Status (24 Files Inventoried):**  
   The targeted regression suite (12 files, 27 tests, 152 assertions) executed with exit code 0 (**PASS**). However, execution of the full repository test suite reveals 13 failing tests across 4 legacy test files (`AuthenticationTest`, `TwoFactorSecurityTest`, `EmailVerificationTest`, `PasswordExpiryAndResetTest`), plus 3 files with environment/migration prerequisites. The full repository test suite is classified as **FAIL / BLOCKED**.
6. **HAProxy Credential Handling Hardened:**  
   Hardcoded fallback default passwords have been removed from `haproxy/haproxy.cfg`. `docker-compose.yml` strictly enforces mandatory environment injection. Statistics binding is confined to `127.0.0.1:8404`. Because `130-haproxy-1` container is currently stopped on staging, live runtime authentication is marked **NOT VERIFIED**.

---

## 2. Authoritative Discrepancy & Verification Register

| Discrepancy / Component | Earlier Claim | Audited Evidence | Resolution & Correction | Final Status |
|---|---|---|---|---|
| **1. Dockerfile Tag Existence** | Claimed `php:8.3.36-fpm-alpine` is a non-existent phantom tag. | Tag exists on Docker Hub, pushed 2026-10-25 (Digest: `sha256:25e15cf35ed0f5b75d8cf14fc2c4ed7e0e6bee00d359e681b7fa3e91a7bee49f`). Successfully pulled on staging host. | Inaccurate claim retracted. Tag validity proven. Base image is active and official. | **PASS** |
| **2. Production Log Provenance** | Claimed `production.ERROR` log cited in reports came from probing production. | Log line was located inside `/var/www/html/storage/logs/laravel.log` on staging container `130-app-1-1`, copied during initial staging setup. | Production (`100.124.232.124`) was never accessed or probed. Strict isolation preserved. | **PASS** |
| **3. Security Advisory Count (6 vs 8)** | Claimed 6 CVEs resolved while listing 8 CVE identifiers. | CLI table grouped multi-CVE advisories into 6 visual blocks. Exact CVE tally is 8 (`symfony/yaml`: 4, `symfony/mime`: 1, `dompdf/dompdf`: 3). | Reconciled precisely. `composer.lock` dependencies upgraded and `composer audit --locked` exits 0. | **PASS (Lockfile)** |
| **4. Running Application Dependency State** | Described application as patched following composer audit. | Runtime inspection of container `130-app-1-1` reveals `dompdf v3.1.5`, `mime v7.4.19`, `yaml v7.4.8` still active in container vendor directory. | Lockfile is patched; running container has not been rebuilt. Runtime cannot be called patched until rebuilt. | **NOT VERIFIED / PENDING REBUILD** |
| **5. Call Sequence Concurrency & Table Scan** | Claimed allocation was fast and race-safe, but code contained table scan and unbounded loop. | Source inspection of `CallService::generateCallNumber()` found `Call::max('id')` unindexed scan across 709k rows and `while(true)` loop. | Rewritten: atomic `UPDATE call_sequences` with write-PDO affinity, transactional initialization, bounded 3-retry loop in `createCall()`. | **PASS** |
| **6. Call Allocation Throughput** | Claimed 1,500 allocations/sec and sub-0.1ms fast path. | No reproducible benchmark data or load harness existed. Multi-worker tests were sequential. | Unsupported throughput claims retracted. Multi-process parallel benchmark marked unverified. | **NOT VERIFIED** |
| **7. Targeted Regression Test Suite** | Claimed 27 tests passed and presented as entire suite passing. | Exactly 27 tests across 12 files passed in container `130-app-1-1` (152 assertions, 47.036s). | Valid evidence for targeted regression subset, but misleading to present as full repository suite. | **PASS (Targeted Suite)** |
| **8. Complete 24-File Test Suite Gate** | Claimed test suite gate was completely satisfied. | Complete inventory of all 24 test files shows 13 failures across 4 legacy test files and 3 unverified files. | Legacy tests require schema alignment (e.g. `email_verified_at`), CSRF token handling, and route assertions. | **FAIL / BLOCKED (Full Suite)** |
| **9. HAProxy Credential Safety** | Claimed credentials rotated and runtime authenticated (200 OK). | `haproxy.cfg` contained fallback default password string `${HAPROXY_STATS_PASSWORD:-8kX9m...}`. Container `130-haproxy-1` is exited. | Fallback password removed from `haproxy.cfg`. Mandatory env vars added to `docker-compose.yml`. Runtime auth unverified. | **NOT VERIFIED (Runtime Auth)** |
| **10. Backup Database Recoverability** | Claimed backup recovery was fully proven via `gzip -t`. | `gzip -t` only verifies archive integrity, not SQL restore. Baseline dump is 66 MB (`staging_baseline_20261029.sql.gz`). | Executed safe disposable MySQL 8.4 container restore (`/root/safe_disposable_restore.sh`). Dump imported in 86s with zero errors. | **PASS** |
| **11. Staging Host SSH Access** | Claimed SSH blocked by firewall rule and required manual fix. | `curl -k -I https://100.108.239.107:22` showed port 22 redirected to HTTPS port 443. | Administrator inserted bypass rule `iptables -t nat -I PREROUTING 1 -p tcp --dport 22 -j ACCEPT`. SSH verified. | **PASS** |
| **12. Migration Schema Tracking** | Claimed caller history index was active via migration. | Earlier migration was untracked and coupled with UUID. Active DB had `call_sequences` created ad-hoc. | Created clean additive migrations `2026_10_29_010000` and `2026_10_29_020000`. Full run pending maintenance window. | **BLOCKED (Execution)** |

---

## 3. Discrepancy Status Breakdown

- **Total Claims Audited:** 12
- **Directly Executed & Verified (PASS):** 6 (Tag validity, log provenance, lockfile audit, call sequencing code, targeted test suite, disposable restore)
- **Defects / Execution Failures (FAIL):** 1 (Full repository test suite has 13 failures across 4 files)
- **Partially Verified / Environmentally Blocked (BLOCKED):** 2 (Candidate image rebuild/reproduction, full database migration execution)
- **Unverified / Awaiting Runtime Verification (NOT VERIFIED):** 3 (Running container dependency patching, HAProxy runtime authentication, multi-process allocation throughput benchmark)

---

## 4. Final Decision Rule & Release Gate Verdict

Production deployment is **STRICTLY PROHIBITED AND NOT AUTHORIZED**.

While significant security, sequencing, and database recovery hardening milestones have been achieved and empirically verified, production release remains blocked by:
1. Outstanding failures in 4 legacy test files within the full test suite.
2. Running staging container image still containing pre-remediation dependency versions.
3. Live HAProxy service and candidate image rebuild awaiting container orchestration and administrator approval.
