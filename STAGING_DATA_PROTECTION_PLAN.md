# NHMP 130 HELPLINE — STAGING DATA PROTECTION & SANITIZATION PLAN

## Document Metadata
- **Document ID:** NHMP-130-SEC-PLAN-DAT-005
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Target Host:** Staging `100.108.239.107`
- **Active Operational Database:** `130_crm_db`
- **Classification:** Controlled Engineering Artifact / Sensitive PII Protection Architecture
- **Status:** READY FOR REVIEW — APPLIED EXCLUSIVELY TO DISPOSABLE ENVIRONMENTS

---

## 1. Sensitive Operational Data Inventory (Staging Environment)

Forensic inspection of the staging host and MySQL database confirmed that **staging contains real operational police records, citizen interactions, and personal identifiable information (PII)**:

| Table / Artifact | Row Count / Volume | Sensitive Data Elements | Risk Level |
| :--- | :--- | :--- | :--- |
| **`calls`** | **709,834 records** | Citizen mobile numbers (`caller_number`), citizen full names (`caller_name`), emergency incident locations, complaint details. | **CRITICAL (Citizen PII)** |
| **`users`** | **219 records** | Police officer Computerized National Identity Card numbers (`cnic`), personal phone numbers (`mobile_no`), usernames, hashed credentials. | **HIGH (Officer PII)** |
| **`login_logs`** | **1,012,207 records** | Originating internal and public IP addresses (`ip_address`), device user agents, authentication timestamps. | **MEDIUM (Network Metadata)** |
| **`audit_logs`** | **14,582 records** | Administrative actions, user updates, record modifications. | **MEDIUM (Operational Audit)** |
| **Export Files (`*.csv`)** | **560 KB (2 files)** | Unmasked citizen calls exported to repository directory (`export_1778495195709.csv`). | **HIGH (Unencrypted Artifacts)** |

---

## 2. Mandatory Boundary & Prohibition Against Active Database Scrubbing

> [!CAUTION]
> **ABSOLUTE OPERATIONAL BOUNDARY:**
> 1. Anonymization, pseudonymization, data masking, or destructive scrubbing **MUST NEVER BE RUN AGAINST THE ACTIVE STAGING DATABASE (`130_crm_db`)**.
> 2. In-place data alteration destroys evidentiary records, corrupts existing call statistics, and invalidates production reconciliation baselines.
> 3. All sanitization procedures defined herein are designed strictly for execution on **isolated disposable copies** provisioned for external performance testing, developer workstations, or CI/CD pipelines.

---

## 3. Deterministic Pseudonymization Design (Disposable Copies Only)

To enable realistic performance testing without compromising citizen or officer privacy, the sanitization pipeline employs **Format-Preserving Deterministic Pseudonymization**.

```
                           [Real Operational Record]
                     caller_number: 0300-1234567, cnic: 61101-1234567-1
                                      │
                                      ▼
                   [HMAC-SHA256 Deterministic Keyed Transform]
                     Key: Ephemeral session secret (never saved)
                                      │
                                      ▼
                   [Format-Preserving Synthetic Mapping]
                   - Preserves Pakistani telecom prefix (0300)
                   - Preserves valid CNIC format (XXXXX-XXXXXXX-X)
                   - Preserves 1-to-1 relational cardinality
                                      │
                                      ▼
                          [Sanitized Test Record]
                     caller_number: 0300-8492019, cnic: 61101-7392018-4
```

### 3.1 Field-by-Field Transformation Rules

#### A. Citizen Telephone Numbers (`calls.caller_number`)
- **Preservation Requirement:** Pakistani cellular network routing and reporting group by telco prefix (`0300-0309` Jazz, `0310-0318` Zong, `0320-0325` Warid, `0331-0337` Ufone, `0340-0349` Telenor).
- **Transformation Algorithm:**
  1. Extract the 4-digit prefix (e.g., `0300`).
  2. Compute HMAC-SHA256 of the 7-digit subscriber number with an ephemeral salt.
  3. Format the integer modulo $10^7$ as a 7-digit zero-padded number.
  4. Result: `0300-XXXXXXX` maps deterministically to a synthetic `0300-YYYYYYY`.
- **Relational Integrity:** Calls from the same citizen retain identical transformed numbers, preserving composite index lookups (`idx_calls_caller_created`) and caller history distribution.

#### B. Citizen Full Names (`calls.caller_name`)
- Replaced with localized synthetic names generated from a standardized dictionary of common Pakistani given and family names (e.g., "Muhammad Tariq", "Ali Raza", "Fatima Bibi", "Abdul Rehman").

#### C. Police Officer CNICs (`users.cnic`)
- Replaced with validly formatted synthetic 13-digit numbers (`XXXXX-XXXXXXX-X`), retaining province code while randomizing citizen identifier digits.

#### D. Police Officer Mobile Numbers (`users.mobile_no`)
- Transformed using the same format-preserving telco algorithm as caller numbers.

#### E. Police Officer Email Addresses (`users.email`)
- Masked using deterministic hash prefixes mapped to the non-routable testing domain: `officer_<hash>@nhmp.test`.

#### F. Network Identifiers (`login_logs.ip_address`)
- Public IP addresses mapped into RFC 5737 documentation networks (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`). Internal private IP addresses mapped to `10.130.x.x`.

---

## 4. Repository & Storage Data Quarantine

### 4.1 Unencrypted CSV Exports in Working Copy
The repository currently contains two CSV export files:
- `export_1778495195709.csv` (339 KB)
- `export_1778495922900.csv` (221 KB)

Forensic examination revealed these files contain unmasked citizen call logs exported during earlier operational testing.
**Remediation Actions:**
1. Add `*.csv` and `export_*.csv` to `.gitignore`.
2. Securely purge operational export files from the Git working tree.
3. Ensure no operational call logs are ever committed to version control.

### 4.2 Application Log Scrubbing (`storage/logs/laravel.log`)
Application logs must not capture plain TOTP secrets, authentication tokens, passwords, or raw caller CNICs. All logging pipelines must enforce redaction filters (already verified in `test_mysql_audit_logs_redact_secrets`).

---

## 5. Residual Re-Identification Risks & Mitigation

| Residual Risk | Description | Technical Mitigation |
| :--- | :--- | :--- |
| **Spatial / Temporal Correlation** | High-profile emergency calls (e.g. major multi-vehicle pileups or VIP escort calls) could be correlated with public news reports via exact incident timestamps and motorway landmark codes. | Apply timestamp jitter (random $\pm 15$ minutes) on disposable test data sets if ever shared outside the staging network. |
| **Frequency Analysis on High-Volume Callers** | A persistent caller who called 100+ times retains a distinct call count signature under deterministic pseudonymization. | Acceptable for internal query performance testing; unacceptable for public release. |

---

## 6. Implementation Status Distinction

In compliance with verification gate requirements:

| Control | Status | Evidence / Notes |
| :--- | :--- | :--- |
| **Production Access Boundary** | **PASS (Enforced)** | Zero access to `100.124.232.124`. |
| **Active Staging DB Scrubbing** | **PASS (Prohibited)** | Zero scrubbing or alteration executed against `130_crm_db`. |
| **Secret Redaction in Audit Logs** | **PASS (Verified)** | Unit tests prove TOTP secrets and passwords are never logged in DB. |
| **Git Working Tree Quarantine** | **PENDING** | `export_*.csv` files identified in working directory; scheduled for purge. |
| **Disposable DB Pseudonymization Script** | **DESIGNED (Recommendation)** | Ready for execution on `130_crm_test_disposable` once approved. |
