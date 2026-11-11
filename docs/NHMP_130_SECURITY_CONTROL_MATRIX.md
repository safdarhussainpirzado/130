# NHMP 130 — SECURITY CONTROL TRACEABILITY MATRIX

## 1. COMPLIANCE STANDARDS & CONTROL BASELINES

This matrix establishes formal traceability between technical controls in NHMP 130 and international cybersecurity governance standards:
- **NIST SP 800-53 Rev. 5:** Security and Privacy Controls for Information Systems and Organizations
- **NIST CSF 2.0:** Cybersecurity Framework (Govern, Identify, Protect, Detect, Respond, Recover)
- **OWASP ASVS 4.0:** Application Security Verification Standard (Level 2/3 Government Baseline)

---

## 2. TRACEABILITY MATRIX

| Control ID | Governance Reference | Technical Requirement | Architectural Component | Finding Status | Remediation Status | Verification Evidence |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | NIST AC-2 / ASVS V2.1 | Unique user identification; no generic or shared accounts | IAM Domain (`User`, `Role`) | **Confirmed** | **Independently verified** | RBAC database audit; separate operator logins |
| **SEC-02** | NIST IA-2(1) / ASVS V2.8 | Multi-Factor Authentication for privileged users | `TwoFactorEnforcement` middleware | **Confirmed** | **Independently verified** | Test `RR-HTTP-08`; MFA challenge redirection |
| **SEC-03** | NIST AC-3 / ASVS V4.1 | Server-side authorization check on every resource access | Domain Policies (`CallPolicy`, `UserPolicy`) | **Confirmed** | **Independently verified** | Test `RR-HTTP-01`; Super Admin vs Operator |
| **SEC-04** | NIST AC-6 / ASVS V4.2 | Principle of Least Privilege & Organizational ABAC | `applyScopeVisibility` trait | **Confirmed** | **Independently verified** | Test `RR-HTTP-02`, `RR-HTTP-03`, `RR-HTTP-04` |
| **SEC-05** | NIST SC-28 / ASVS V6.2 | Authenticated Encryption at Rest (AES-256-GCM) | `EncryptionService.php` | **Confirmed** | **Independently verified** | 96-bit unique IV per record + 128-bit GCM tag |
| **SEC-06** | NIST SC-28(1) / ASVS V6.3| Cryptographic Binding of Ciphertext to Record Identity | AAD binding in `EncryptionService` | **Confirmed** | **Independently verified** | Ciphertext transposition attack test |
| **SEC-07** | NIST SC-13 / ASVS V6.4 | Keyed Blind Indexing for Searchable Encryption | `phone_blind_index` helper | **Confirmed** | **Independently verified** | HMAC-SHA256 indexed lookups (`bidx`) |
| **SEC-08** | NIST SI-10 / ASVS V5.3 | SQL Injection Prevention via Parameterized Queries | Eloquent ORM & PDO Prepared Statements | **Confirmed** | **Independently verified** | Security test suite (35/35 Pen tests passed) |
| **SEC-09** | NIST SC-8 / ASVS V9.1 | Transport Layer Security (TLS 1.3 only) | HAProxy frontend (`bind *:443 ssl ...`) | **Confirmed** | **Independently verified** | `curl -v https://localhost` TLS 1.3 handshake |
| **SEC-10** | NIST SC-23 / ASVS V3.4 | Session Invalidation on Logout & Privilege Revocation | `roadrunner_worker.php` GATE A & GATE B | **Confirmed** | **Independently verified** | Test `RR-HTTP-07` (Revoked Session Eviction) |
| **SEC-11** | NIST SC-5 / ASVS V1.14 | RoadRunner Worker State Isolation (Zero Cross-Talk) | Octane worker lifecycle reset hooks | **Confirmed** | **Independently verified** | Test `RR-HTTP-05` (Auth -> Anon Context Flush)|
| **SEC-12** | NIST SC-7 / ASVS V14.4 | Elimination of PHP-FPM and FastCGI Upstreams | Nginx configuration & Docker entrypoints | **Confirmed** | **Independently verified** | Process inspection (PID 1 is RoadRunner only)|
| **SEC-13** | NIST AU-2 / ASVS V8.1 | Tamper-Evident Audit Logging of Privileged Actions | `ActivityLog` & `CallAudit` models | **Confirmed** | **Independently verified** | Automated audit log insertion on mutations |
| **SEC-14** | NIST CP-9 / ASVS V14.2 | Encrypted Automated Backups & Restore Verification | Backup scripts & MinIO S3 integration | **Confirmed** | **Independently verified** | Automated synthetic restore test pipeline |
| **SEC-15** | NIST AC-4 / ASVS V13.1 | Keyset-Paginated Data Streaming (Memory Bounds) | `CallController::exportCsv` | **Confirmed** | **Independently verified** | Test `RR-HTTP-06` (Authorized Export Policy Gate) |
