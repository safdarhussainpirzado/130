# NHMP 130 Helpline — Remediation and Vulnerability Register

**Document ID:** `NHMP-130-SEC-REG-2026-V1`  
**Classification:** Controlled Security Engineering Artifact / Findings Register  
**Standard Reference:** NIST SP 800-53 Rev. 5 (CA-5 Plan of Action and Milestones, SI-2 Flaw Remediation)  
**Effective Date:** November 05, 2026  

---

## 1. Vulnerability & Flaw Remediation Register

### Finding SEC-KEY-01: Application Access to MySQL Root Password
- **Finding ID:** `SEC-KEY-01`
- **Severity:** **HIGH (CVSS 8.4)**
- **Affected Component:** `docker-compose.yml` (`app-1`, `app-2` services)
- **Reproduction Steps:** Execute `docker exec 130-app-1-1 cat /run/secrets/db_root_password`.
- **Evidence:** The root password was readable in plaintext inside the PHP container runtime.
- **Root Cause:** Injudicious inclusion of `db_root_password` in application secret mounts.
- **Business Impact:** An adversary compromising a web worker could take complete administrative control of the MySQL database.
- **Remediation:** Removed `- db_root_password` from `app-1` and `app-2` secret lists in [docker-compose.yml](file:///home/mrpirzado/projects/130/docker-compose.yml).
- **Regression Test:** Verified `/run/secrets/db_root_password` is absent in application containers.
- **Verification Result:** **REMEDIATED & VERIFIED**
- **Residual Risk:** None. The app connects strictly as `crm_user` using `db_password`.
- **Status:** **CLOSED**

---

### Finding SEC-CRY-01: Silent Plaintext Fallback Masking Corrupted Ciphertext
- **Finding ID:** `SEC-CRY-01`
- **Severity:** **HIGH (CVSS 7.5)**
- **Affected Component:** [app/Casts/GracefulEncrypted.php](file:///home/mrpirzado/projects/130/app/Casts/GracefulEncrypted.php)
- **Reproduction Steps:** Flip 1 byte in a base64 ciphertext in the database and call `$call->caller_number`.
- **Evidence:** Model silently returned the corrupted base64 string (`eyJpdiI6...`) instead of throwing an exception.
- **Root Cause:** Overly broad `catch (\Throwable $e)` block returning raw `$value` as legacy plaintext fallback.
- **Business Impact:** Tampered citizen PII or corrupted database records bypassed detection and displayed garbled data to police operators.
- **Remediation:** Rewrote `GracefulEncrypted::get()` to throw `DecryptException` immediately whenever an envelope structure fails MAC or tag verification.
- **Regression Test:** Automated Scenario S13 bit-flipping attack simulation.
- **Verification Result:** **REMEDIATED & VERIFIED** (Throws `DecryptException: Security Violation: AES-256-GCM authentication tag verification failed`).
- **Residual Risk:** Low. Legacy unencrypted historical records still pass through safely.
- **Status:** **CLOSED**

---

### Finding SEC-CRY-02: Missing AAD Binding Allowing Cut-and-Paste Substitution
- **Finding ID:** `SEC-CRY-02`
- **Severity:** **HIGH (CVSS 7.4)**
- **Affected Component:** [app/Casts/GracefulEncrypted.php](file:///home/mrpirzado/projects/130/app/Casts/GracefulEncrypted.php)
- **Reproduction Steps:** Copy ciphertext from `calls.caller_number` and paste into `users.mobile_no`.
- **Evidence:** Ciphertext decrypted successfully under another model because AES-CBC lacked AAD binding.
- **Root Cause:** Use of AES-256-CBC without Associated Authenticated Data.
- **Business Impact:** Adversary with database write access could transpose sensitive phone numbers and CNICs between users without tripping integrity checks.
- **Remediation:** Upgraded to AES-256-GCM v2 with AAD bound strictly to `$model->getTable() . ':' . $key`.
- **Regression Test:** Automated Scenario S09 cross-model substitution simulation.
- **Verification Result:** **REMEDIATED & VERIFIED** (Throws `DecryptException: Security Violation: Ciphertext AAD mismatch`).
- **Residual Risk:** None for new and migrated records.
- **Status:** **CLOSED**

---

### Finding SEC-SRC-01: Main Call Search Incompatible with Encrypted Records
- **Finding ID:** `SEC-SRC-01`
- **Severity:** **HIGH (CVSS 7.2)**
- **Affected Component:** [app/Http/Controllers/CallController.php](file:///home/mrpirzado/projects/130/app/Http/Controllers/CallController.php) (`applyContextFilters`)
- **Reproduction Steps:** In main UI, search for a caller's phone number (`03001234567`).
- **Evidence:** Database returned 0 results because query ran `LIKE '0300%'` against base64 ciphertext.
- **Root Cause:** Phone blind index `caller_number_bidx` was omitted from main query builder logic.
- **Business Impact:** Helpline operators could not locate previous call history for citizens whose data had been encrypted.
- **Remediation:** Added `phone_blind_index()` fast path in `applyContextFilters` to query `calls.caller_number_bidx`.
- **Regression Test:** Unit search verification query in staging.
- **Verification Result:** **REMEDIATED & VERIFIED**
- **Residual Risk:** Partial phone prefix searches (e.g. 4 digits) require exact or full mobile prefix for blind index match.
- **Status:** **CLOSED**

---

### Finding SEC-MON-01: Missing Prometheus Exporter Failure Alerts
- **Finding ID:** `SEC-MON-01`
- **Severity:** **MEDIUM (CVSS 5.3)**
- **Affected Component:** [monitoring/prometheus/alert_rules.yml](file:///home/mrpirzado/projects/130/monitoring/prometheus/alert_rules.yml)
- **Reproduction Steps:** Stop `mysql-exporter`; observe Prometheus alert table.
- **Evidence:** Prometheus showed target state DOWN (`up == 0`), but zero alerts were generated.
- **Root Cause:** Absence of `InstanceDown` (`up == 0`) alert rule in Prometheus rule configuration.
- **Business Impact:** Silent failure of monitoring and telemetry exporters went undetected.
- **Remediation:** Added `ServiceInstanceDown` alert rule with 30-second trigger window for `up == 0`.
- **Regression Test:** Automated Scenario S26 rule existence check.
- **Verification Result:** **REMEDIATED & VERIFIED**
- **Residual Risk:** Low. Alert delivery depends on external Alertmanager webhook integration in production.
- **Status:** **CLOSED**

---

### Finding SEC-TST-01: Weak Test Assertions in Adversarial Matrix Suite
- **Finding ID:** `SEC-TST-01`
- **Severity:** **LOW (CVSS 3.1)**
- **Affected Component:** [scripts/test/run_adversarial_matrix_suite.php](file:///home/mrpirzado/projects/130/scripts/test/run_adversarial_matrix_suite.php)
- **Reproduction Steps:** Review code for S01, S09, S10, S13, S14, S15, S19, S25, S26.
- **Evidence:** Tests inspected source-code strings (`str_contains`) or class existence rather than exercising live controls.
- **Root Cause:** Rapid prototype testing prioritizing PASS counts over adversarial depth.
- **Business Impact:** False sense of security from tests passing on unexercised code.
- **Remediation:** Rewrote 9 tests with authentic negative attacks, real GCM tampering, AAD mismatch checks, unauthenticated request interception, and real public key encryption.
- **Regression Test:** Execution of full 26-scenario suite on both nodes.
- **Verification Result:** **REMEDIATED & VERIFIED** (26/26 PASS with authentic negative tests).
- **Residual Risk:** None.
- **Status:** **CLOSED**
