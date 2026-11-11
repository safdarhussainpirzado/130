# Reusable Feature Security & Testing Checklist

**Standard Reference:** [SECURITY_FIRST_DEVELOPMENT_STANDARD.md](file:///home/mrpirzado/projects/130/docs/engineering/SECURITY_FIRST_DEVELOPMENT_STANDARD.md)  
**Applicability:** Required for every feature, enhancement, bug fix, or configuration change.  
**Instructions:** Copy this checklist into the task documentation or feature security assessment report, and check off each gate with verifiable evidence.

---

## Feature Metadata

- **Feature Name / Task ID:** _______________________________________
- **Author / Engineer:** ___________________________________________
- **Date Initiated:** ______________________________________________
- **Target Release / Branch:** _____________________________________
- **Current Status:** `[ ] IMPLEMENTATION COMPLETE` | `[ ] SECURITY ASSESSMENT COMPLETE` | `[ ] SECURITY REMEDIATION VERIFIED` | `[ ] RELEASE APPROVED`

---

## 1. Requirements & Threat Modeling Gate

### 1.1 Requirements Understanding
- [ ] Existing codebase inspected (controllers, models, migrations, policies, middleware, views).
- [ ] Acceptance criteria explicitly formulated and documented.
- [ ] Edge cases, boundary values, null inputs, and failure scenarios documented.
- [ ] Assumptions documented; user clarification requested if ambiguous.
- [ ] Unrelated modules left untouched (no unrequested rewrites).

### 1.2 STRIDE Threat Assessment
- [ ] **Spoofing**: Authentication mechanism and identity verification assessed.
- [ ] **Tampering**: Parameter manipulation, SQL injection, Mass-assignment risks analyzed.
- [ ] **Repudiation**: Audit logging configured for all mutating operations (`AuditLog::create`).
- [ ] **Information Disclosure**: Secret leakage, debug output, and PII exposure evaluated.
- [ ] **Denial of Service**: Rate limits, pagination, query optimization, and resource exhaustion evaluated.
- [ ] **Elevation of Privilege**: Role-based access control (RBAC), user scopes, and policies verified.

---

## 2. Test-Driven Development (TDD) Gate

### 2.1 Phase B: Design Tests First (RED Phase)
- [ ] Unit tests designed covering core logic and boundary conditions.
- [ ] Feature/Integration tests designed covering HTTP routes, status codes, and policies.
- [ ] Security test cases designed covering replay, authorization bypass, and abuse scenarios.
- [ ] **Initial Test Run Executed**: Tests fail cleanly before implementation begins.
- [ ] **Failing Test Evidence Recorded**: Command and failure logs captured in work log.

### 2.2 Phase C: Implementation (GREEN Phase)
- [ ] Minimal code implemented to make tests pass.
- [ ] Standard architecture, PSR-12 formatting, and Laravel idioms followed.
- [ ] No test assertions weakened or bypassed.
- [ ] No security middleware disabled.
- [ ] **Test Run Executed**: All tests pass green.

### 2.3 Phase D: Refactoring & Cleanliness (REFACTOR Phase)
- [ ] Code refactored for clarity, maintainability, and database efficiency (N+1 query check).
- [ ] All tests re-run and confirmed passing.
- [ ] Regression tests added for any bugs discovered during refactoring.

---

## 3. Comprehensive Multi-Tier Testing Gate

| Test Tier | Scope & Focus | Status | CLI Command / Evidence File |
|---|---|---|---|
| **Tier 1: Unit Tests** | Services, Validators, Algorithms, Enums, Models | `[ ] PASS` | `php artisan test --filter=Unit` |
| **Tier 2: Component & DB** | DB Transactions, Locks (`FOR UPDATE`), Cache, Queue | `[ ] PASS` | `php artisan test tests/Feature/...` |
| **Tier 3: Feature & API** | Routes, Status Codes, Validation, Auth Middleware, Scopes | `[ ] PASS` | `php artisan test tests/Feature/...` |
| **Tier 4: E2E User Journeys** | Authentication ceremony, Recovery, Sessions, UI states | `[ ] PASS` | Browser Subagent / Playwright / Dusk |
| **Tier 5: System Regression** | Full repository test suite | `[ ] PASS` | `composer test` |

---

## 4. Security Assessment & Static Analysis Gate

- [ ] **Code Style & Linting**: `vendor/bin/pint --test` executed with zero errors.
- [ ] **PHP Dependency Audit**: `composer audit` executed; zero Critical/High CVEs.
- [ ] **NPM Dependency Audit**: `npm audit` executed; zero Critical/High CVEs.
- [ ] **Secrets & Keys Check**: No hardcoded secrets, API tokens, or private keys committed.
- [ ] **External Network Leaks**: Zero client-side external requests (e.g., QR codes generated locally as SVG).
- [ ] **Data Encryption**: Sensitive fields (e.g., `totp_secret`) encrypted at rest with `Crypt::encryptString`.

---

## 5. Controlled Penetration Testing Gate

*Target Environment: Local isolated Docker container or testbed. Never production.*

- [ ] **Scenario 1: Unauthenticated Bypass**
  - Attempt: Call endpoint without session or auth cookies.
  - Expected: `401 Unauthorized` or redirect to `/login` or `/2fa/challenge`.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 2: Horizontal Privilege Escalation (IDOR)**
  - Attempt: Access or modify another user's records via ID manipulation.
  - Expected: `403 Forbidden` or `404 Not Found`.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 3: Vertical Privilege Escalation**
  - Attempt: Non-privileged role invokes administrative or privileged actions.
  - Expected: `403 Forbidden`.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 4: Replay & Concurrency Attacks**
  - Attempt: Submit exact same single-use token or OTP twice concurrently.
  - Expected: Exactly 1 success, 1 rejection (`REPLAY_DETECTED`).
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 5: Session Invalidation & Version Bump**
  - Attempt: Access protected resources after session invalidation or `security_version` bump.
  - Expected: Session rejected; redirected to login.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 6: Malformed Input Fuzzing**
  - Attempt: SQL injection patterns, XSS tags, extreme string lengths, type mismatches.
  - Expected: Request rejected cleanly with `422 Unprocessable` or sanitized. Zero 500 crashes.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 7: Rate Limiting & Brute Force**
  - Attempt: Spray multiple requests rapidly against authentication/challenge routes.
  - Expected: `429 Too Many Requests` triggered with `Retry-After` header.
  - Result: `[ ] PASS / BLOCKED`
- [ ] **Scenario 8: Forensic Audit Log Trail**
  - Attempt: Inspect `audit_logs` and `login_logs` after successful and failed actions.
  - Expected: Timestamp, IP address, user agent, old values, and new values recorded accurately.
  - Result: `[ ] PASS / BLOCKED`

---

## 6. Stop-and-Discuss Decision Gate

- [ ] Security Assessment Report compiled at `docs/security/reports/<feature>-security-assessment.md`.
- [ ] Findings classified using CVSS v4.0.
- [ ] Any High or Medium findings presented with:
  - Technical description of vulnerability.
  - Attacker preconditions and potential impact.
  - Available remediation options with trade-offs.
  - Recommended solution.
  - Release-blocker determination.
- [ ] **Execution Paused**: Waiting for user review and explicit authorization.
- [ ] **User Approval Received**: Approved option: ________________________ (Date: __________)

---

## 7. Post-Approval Remediation & Final Handover Gate

- [ ] Approved remediation implemented according to specifications.
- [ ] Regression test added that specifically reproduces the original finding.
- [ ] Multi-tier test suite executed; zero regressions.
- [ ] Penetration test re-executed against the remediated feature; exploit no longer works.
- [ ] Residual risks and operational considerations documented.
- [ ] Final handover report presented to user with appropriate status label:
  - `[STATUS: RELEASE APPROVED]`
