# Security-First Development, Mandatory TDD, Automated Testing, and Penetration Testing Standard

**Document Identifier:** NHMP-ENG-STD-001  
**Classification:** Internal Engineering Standard — Mandatory  
**Target Repository:** National Highways & Motorway Police (NHMP) 130 Helpline CRM  
**Effective Date:** October 2026  
**Status:** Approved & Enforced  

---

## 1. Executive Mandate & Governing Principle

This standard governs **all** software engineering and maintenance activities on the NHMP 130 Helpline CRM, including new feature development, modifications to existing features, bug fixes, API endpoint creation, database schema migrations, authentication/authorization mechanisms, external integrations, user interface updates, background queue workers, and deployment/infrastructure configurations.

### The Governing Principle
> **Develop with TDD, verify functionality with automated tests, assess security through evidence-based and authorized attack simulation, report findings transparently, and obtain explicit authorization before consequential discretionary security remediation.**

No task or feature shall be deemed complete merely because code compiles, Docker containers start, an interface renders, or a basic happy-path test passes. Security, verification, and defense-in-depth must be woven into every phase of the engineering lifecycle.

### The Standard Development Lifecycle
Every engineering cycle must strictly adhere to the following sequence:

```
[1. Requirements Analysis] 
       │
       ▼
[2. Threat Modeling (STRIDE)] 
       │
       ▼
[3. Test Design (Failing Tests)] 
       │
       ▼
[4. Test-Driven Development (Red-Green-Refactor)] 
       │
       ▼
[5. Implementation & Defensive Hardening] 
       │
       ▼
[6. Comprehensive Multi-Tier Testing] 
       │
       ▼
[7. Security Assessment & Static/Dependency Auditing] 
       │
       ▼
[8. Controlled & Authorized Penetration Testing] 
       │
       ▼
[9. Evidence-Based Reporting & Classification] 
       │
       ▼
[10. Stop-and-Discuss Decision Gate] 
       │
       ▼
[11. Authorized Remediation & Retesting] 
       │
       ▼
[12. Regression Testing & Final Handover]
```

---

## 2. Mandatory Test-Driven Development (TDD) Workflow

Every functional change and bug fix must follow the **Red-Green-Refactor** discipline.

```
       ┌──────────────────────────────┐
       │   Phase A: Understand Req.   │
       │   (Analyze, Scope, Baseline) │
       └──────────────┬───────────────┘
                      │
                      ▼
       ┌──────────────────────────────┐
       │   Phase B: Design Tests      │
       │   (RED: Tests fail cleanly)  │
       └──────────────┬───────────────┘
                      │
                      ▼
       ┌──────────────────────────────┐
       │   Phase C: Implement Feature │
       │   (GREEN: Tests pass)        │
       └──────────────┬───────────────┘
                      │
                      ▼
       ┌──────────────────────────────┐
       │   Phase D: Refactor & Verify │
       │   (REFACTOR: Clean & Solid)  │
       └──────────────────────────────┘
```

### Phase A: Understand the Requirement
Before writing any code:
1. **Module & Dependency Inspection**: Inspect existing controllers, models, policies, middleware, database tables, views, and third-party packages involved in the change.
2. **Acceptance Criteria**: Formulate precise, verifiable acceptance criteria covering expected business logic.
3. **Failure & Boundary Scenarios**: Enumerate edge cases, malformed payloads, type violations, null values, truncation risks, and boundary limits.
4. **Impact Mapping**: Identify impacted components, database locks, cache keys, session keys, and backward compatibility constraints.
5. **Security Sensitivity**: Explicitly note whether the operation touches authentication, authorization, session state, audit logs, personally identifiable information (PII), or operational emergency records.
6. **Assumption Clarification**: When architectural or policy decisions are ambiguous, formulate explicit options and seek confirmation prior to irreversible edits.

### Phase B: Design the Tests First (RED)
1. Write automated test cases that demonstrate the desired behavior, security boundaries, and failure responses.
2. Execute the test suite against the current codebase. The tests **must fail** (or be skipped/unimplemented) because the new functionality or fix is not yet present.
3. Capture and log the initial failing test execution as immutable evidence of TDD compliance.
4. Prohibited actions:
   - Writing implementation code prior to test design.
   - Writing tests that pass vacuously before implementation.

### Phase C: Implement the Feature (GREEN)
1. Implement the minimal appropriate code required to satisfy the failing tests.
2. Adhere strictly to the established architecture, PSR-12 coding style, Laravel 12 idioms, and secure coding practices.
3. Prohibited actions:
   - Weakening test assertions to force a passing result.
   - Disabling CSRF protection, authentication guards, or security middleware during feature testing unless testing an explicitly unauthenticated endpoint.
   - Modifying expected outcomes solely to mask defects.

### Phase D: Refactor and Verify (REFACTOR)
1. Clean up implementation details, remove redundancies, optimize database queries (e.g., prevent N+1 queries), and reinforce error handling.
2. Re-run the test suite to ensure tests remain completely green.
3. If genuine bugs or architectural limitations are revealed during refactoring, write a new regression test reproducing the defect before correcting it.

---

## 3. Comprehensive Multi-Tier Testing Requirements

Testing must be multi-tiered. Skipping any applicable category requires a documented technical justification.

```
┌────────────────────────────────────────────────────────┐
│             Tier 5: End-to-End (E2E) Tests             │  Browser journeys, UI workflows, auth ceremonies
├────────────────────────────────────────────────────────┤
│          Tier 4: Penetration & Attack Simulation       │  IDOR, CSRF, Replay, Session hijacking, Injection
├────────────────────────────────────────────────────────┤
│             Tier 3: Feature & API Integration          │  HTTP endpoints, RBAC policies, Middleware
├────────────────────────────────────────────────────────┤
│           Tier 2: Component Integration Tests          │  Database transactions, Redis, Queue jobs, Audit
├────────────────────────────────────────────────────────┤
│                Tier 1: Unit Tests                      │  Services, Helpers, Algorithms, Enums, DTOs
└────────────────────────────────────────────────────────┘
```

### 3.1 Tier 1: Unit Testing
- **Target**: Isolated services, mathematical calculations, string parsing, token generation, TOTP algorithms, WebAuthn payload decoders, policy rules, and custom validation rules.
- **Requirements**:
  - Test valid inputs and expected deterministic outputs.
  - Test malformed, truncated, null, binary, oversized, and unexpected inputs.
  - Test boundary numbers, leap years, timezone offsets, and Unicode/RTL strings.
  - Test exception handling, specific exception classes, and error codes.
  - Test state machine transitions and immutable entity contracts.

### 3.2 Tier 2: Component & Database Integration Testing
- **Target**: Interactions between models, database transactions, foreign key cascades, database locking (`SELECT ... FOR UPDATE`), Redis cache, queue dispatchers, and audit log generation.
- **Requirements**:
  - Verify database constraints, indexing, unique constraints, and referential integrity.
  - Test atomic transactions and proper rollback behavior upon uncaught exceptions.
  - Test concurrency, row-level locks, and race conditions (e.g., single-use token consumption, OTP replay prevention).
  - Test audit log record creation (`AuditLog::create`) for every mutating operational and security event.

### 3.3 Tier 3: Feature & API Contract Testing
- **Target**: Complete HTTP requests through the Laravel middleware pipeline, controllers, and response formatters.
- **Requirements**:
  - Validate exact HTTP status codes (200, 201, 302, 400, 401, 403, 404, 419, 422, 429, 500).
  - Verify authentication guard enforcement (unauthenticated requests rejected or redirected).
  - Verify RBAC authorization (user without requisite role/permission receives 403 Forbidden).
  - Verify data isolation by role, zone, sector, and beat (a sector admin cannot view another sector's calls).
  - Verify strict request validation errors (422 Unprocessable Entity with targeted field keys).
  - Verify rate limiting and throttling headers (`Retry-After`, 429 Too Many Requests).

### 3.4 Tier 4: End-to-End (E2E) & User Journey Testing
- **Target**: Complete browser-based operational workflows across sessions and views.
- **Requirements**:
  - Full authentication lifecycle: Login → MFA Challenge (TOTP/WebAuthn) → Dashboard landing.
  - Inactivity timeout and session termination.
  - Emergency recovery flow: Recovery code submission → Restricted session mode → Replacement authenticator setup.
  - Multi-tab synchronization and immediate logout broadcast.

### 3.5 Tier 5: Regression & System Testing
- **Target**: Repository-wide regression validation.
- **Requirements**:
  - Whenever a change is made, run all related test suites and ensure zero regressions across legacy modules (Calls, Users, Roles, Reports, Settings).
  - Existing functional workflows must not be silently broken.

---

## 4. Mandatory Threat Modeling (STRIDE)

Before implementing any security-sensitive feature and again prior to penetration testing, an explicit threat model must be constructed using the **STRIDE** methodology.

| STRIDE Threat Category | Definition & Focus Areas | Mandatory Mitigation Controls |
|---|---|---|
| **S — Spoofing** | Impersonating a user, device, or external system. | Strong MFA (phishing-resistant WebAuthn for privileged users, TOTP baseline for staff); session regeneration; strict cookie flags; cryptographic signature verification. |
| **T — Tampering** | Unauthorized alteration of data in transit or at rest. | Server-side validation; cryptographic hashes; encrypted columns (`totp_secret`); immutable audit trail; database foreign key constraints. |
| **R — Repudiation** | Plausible deniability of actions taken by users or administrators. | Mandatory append-only `audit_logs` and `login_logs` capturing timestamp, user ID, IP address, user agent, old values, and new values; dual-control records. |
| **I — Information Disclosure** | Exposure of sensitive data, secrets, or internal architecture. | Concealing secrets from error pages; disabling debug modes in production; strict field masking in UI; zero external third-party requests (e.g., local SVG QR generation); SameSite cookies. |
| **D — Denial of Service** | Exhaustion of server resources or blocking authorized access. | Tiered rate limiting on authentication and search endpoints; max failed attempt lockouts (5 strikes); optimized indexed queries; timeout limits. |
| **E — Elevation of Privilege** | Gaining unauthorized permissions or bypassing RBAC/scopes. | Multi-layer authorization (Middleware + Policy + Scope check); no reliance on client-submitted role IDs; strict separation of duties (no self-approval, no lower-privilege approving higher-privilege). |

### Mandatory Attack Surface Checklist
For all web application features, explicitly evaluate:
- [ ] Broken Object-Level Authorization (BOLA / IDOR)
- [ ] Broken Authentication & Session Hijacking
- [ ] Sensitive Data Exposure & Leaked Secrets
- [ ] SQL Injection & Query Manipulation
- [ ] Cross-Site Scripting (Stored, Reflected, DOM-based XSS)
- [ ] Cross-Site Request Forgery (CSRF) & SameSite Configuration
- [ ] Server-Side Request Forgery (SSRF)
- [ ] Insecure Direct Object References (IDOR) on Call, User, and Report IDs
- [ ] File Upload & Path Traversal Vulnerabilities
- [ ] Race Conditions & Time-of-Check to Time-of-Use (TOCTOU)
- [ ] Missing Audit Logs on Mutating Actions

---

## 5. Mandatory Authorized Penetration Testing

Following implementation and initial automated test passage, a risk-based penetration testing assessment must be executed against the feature in an authorized local/testing environment.

### 5.1 Rules of Engagement (ROE) & Safety Controls
1. **Target Boundary**: Testing is strictly restricted to local Docker containers (`app-1`, `app-2`, `web-1`, `web-2`, `db-1`, etc.) or dedicated staging environments.
2. **Never Attack Production**: No intrusive tests, fuzzing, or vulnerability scanners may be executed against live emergency helpline infrastructure.
3. **No Destructive Payloads**: Never execute destructive database commands (`DROP TABLE`, `TRUNCATE`, wiping real operational data).
4. **Controlled Concurrency**: Concurrency tests must be bounded (e.g., max 20 parallel threads) to prevent host resource starvation.
5. **No Credential Exfiltration**: Synthetic test credentials and seed accounts must be used exclusively. Real staff PII or credentials must never be used.

### 5.2 Tooling Standards

| Tool / Technology | Purpose | Usage Standard |
|---|---|---|
| **PHPUnit 11** | Core Automated Test Engine | Runs unit, feature, integration, and security test suites. |
| **OWASP ZAP / Burp Suite** | DAST & Traffic Analysis | Intercept, analyze, and test session cookies, token handling, and HTTP headers. |
| **Laravel Pint / PHP CS Fixer** | Code Style & Static Linter | Enforces PSR-12 and clean code standards. |
| **Composer Audit** | PHP Dependency Auditing | Scans `composer.lock` for CVEs in third-party packages. |
| **npm audit** | Node/Frontend Dependency Auditing | Scans `package-lock.json` for frontend library vulnerabilities. |
| **Semgrep / Custom AST** | Static Application Security Testing | Scans PHP and Blade code for CWE patterns (SQLi, XSS, unescaped output). |
| **cURL / HTTP Client** | Scripted Penetration Testing | Simulates malicious HTTP requests, tampering, and replay attempts. |

### 5.3 Mandatory Attack Simulation Scenarios
For every feature, the engineer/security analyst must simulate and record the results of:
1. **Unauthenticated Access**: Direct HTTP access to protected routes without session cookies.
2. **Horizontal Privilege Escalation (IDOR)**: Using User A's session to query or mutate User B's resources.
3. **Vertical Privilege Escalation**: Using a standard operator session to invoke administrative endpoints (e.g., `/mgmt/settings/mfa-roles`).
4. **Token Replay & Race Conditions**: Submitting the exact same OTP, recovery code, or authorization token simultaneously in concurrent HTTP requests.
5. **Session Invalidation Check**: Attempting to reuse a session cookie after logout or password change.
6. **Input Fuzzing**: Submitting boundary inputs (`null`, `""`, strings > 10,000 chars, SQL control characters `' OR 1=1 --`, XSS strings `<script>alert(1)</script>`).
7. **Rate Limit Exhaustion**: Sending rapid requests to verify 429 throttling engages properly.
8. **Audit Trail Verification**: Inspecting `audit_logs` table after attack attempts to verify failed and successful security events are logged with IP and User Agent.

---

## 6. Vulnerability Classification & Reporting Standard

All security findings discovered during testing, static analysis, or attack simulation must be classified using the **CVSS v4.0** scoring framework and the five-tier severity scale:

```
┌────────────────────────────────────────────────────────┐
│  CRITICAL (CVSS 9.0 - 10.0)                            │  Immediate RCE, auth bypass, unauth admin access
├────────────────────────────────────────────────────────┤
│  HIGH (CVSS 7.0 - 8.9)                                 │  Privilege escalation, IDOR to sensitive PII, SQLi
├────────────────────────────────────────────────────────┤
│  MEDIUM (CVSS 4.0 - 6.9)                               │  Rate limit bypass, CSRF on minor actions, info leak
├────────────────────────────────────────────────────────┤
│  LOW (CVSS 0.1 - 3.9)                                  │  Minor header misconfiguration, verbose error log
├────────────────────────────────────────────────────────┤
│  INFORMATIONAL (CVSS 0.0)                              │  Hardening opportunity, defense-in-depth suggestion
└────────────────────────────────────────────────────────┘
```

### Mandatory 17-Point Finding Schema
Every finding must be documented using the following standard template:

```markdown
### Finding [ID]: [Concise Descriptive Title]

1. **Finding ID**: SEC-NHMP-<FEATURE>-<NUM> (e.g., SEC-NHMP-MFA-001)
2. **Title & Severity**: [Title] — [CRITICAL | HIGH | MEDIUM | LOW | INFORMATIONAL]
3. **Affected Component**: [Controller, Route, Migration, Service, View, or File Path]
4. **CWE & OWASP Mapping**: [e.g., CWE-287: Improper Authentication | OWASP A01:2021-Broken Access Control]
5. **CVSS v4.0 Vector & Score**: [e.g., CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N (9.3)]
6. **Description**: [Precise technical description of the vulnerability mechanism]
7. **Preconditions & Threat Actor**: [Attacker profile, required privileges, network access]
8. **Safe Proof of Concept (PoC)**: [Reproducible cURL command, test script, or steps]
9. **Sanitized Evidence**: [HTTP request/response snippets, logs, error outputs — with secrets masked]
10. **Expected vs. Actual Behavior**:
    - *Expected*: [What secure behavior should have occurred]
    - *Actual*: [What the insecure implementation actually permitted]
11. **Security & Operational Impact**: [Impact on 130 Helpline confidentiality, integrity, availability]
12. **Likelihood & Exploitability**: [Evaluation of ease of discovery and exploitation]
13. **Remediation Options**:
    - *Option A*: [Primary architectural recommendation]
    - *Option B*: [Alternative or defense-in-depth countermeasure]
14. **Trade-offs & Compatibility Risks**: [Performance, UX impact, backward compatibility risks]
15. **Suggested Verification Tests**: [Specific test cases required to prove remediation]
16. **Confidence Level**: [Confirmed Exploit | High Confidence | Potential / Unverified]
17. **Verification Status**: [Open | Remediated & Verified | Accepted Risk]
```

---

## 7. Mandatory Stop-and-Discuss Decision Gate

### The Mandatory Decision Rule
> **No discretionary security remediation that alters application architecture, database schemas, role permissions, authentication flows, user experience, or operational workflows shall be implemented without prior review, discussion, and explicit user approval.**

After completing the implementation, automated tests, and authorized penetration testing:
1. Compile the findings into the formal assessment report at:
   `docs/security/reports/<feature-name>-security-assessment.md`
2. Present a concise **Decision Required** briefing to the user detailing:
   - What the vulnerability is.
   - How it can be exploited and by whom.
   - What potential damage or disruption could result.
   - The available remediation options with their respective pros, cons, and operational trade-offs.
   - The recommended option and technical justification.
   - Whether the finding is a release-blocking condition.
3. Pause execution and wait for the user's decision before proceeding with discretionary remediations.

### Critical Safety Exception
If an **actively exploitable critical vulnerability** is discovered that presents an immediate danger to live data or live systems, it must be reported **immediately** in the response with an urgent containment recommendation, rather than holding it until the full final report is finished.

---

## 8. Definition of Done (DoD) for Every Feature

A feature or change is **Done** and ready for release only when all items on this checklist are satisfied and verified with evidence:

- [ ] **Requirements & Scope**: Fully documented, with acceptance criteria.
- [ ] **Threat Model**: STRIDE threat assessment completed and documented.
- [ ] **TDD Evidence**: Automated tests designed and executed first (Red phase recorded).
- [ ] **Unit Tests**: All unit tests written, executed, and passing (100% of applicable units).
- [ ] **Integration Tests**: Database transactions, locks, and cache operations verified.
- [ ] **Feature & API Tests**: HTTP status codes, middleware, policies, and rate limits verified.
- [ ] **Security Tests**: Monotonic replay, token consumption, and boundary limits verified.
- [ ] **Authorized Penetration Testing**: Simulated attack scenarios executed and logged.
- [ ] **Static & Dependency Auditing**: Linter, static analysis, and dependency scanners executed cleanly.
- [ ] **Stop-and-Discuss Gate**: Security Assessment Report delivered; decisions approved by user.
- [ ] **Remediation Verified**: Approved fixes implemented and proven with regression tests.
- [ ] **Final Handover**: Documentation updated, residual risks disclosed.

### Formal Status Classifications
Every task report must state its status using one or more of these distinct, unmerged labels:
1. `[STATUS: IMPLEMENTATION COMPLETE]` — Code and automated functional tests pass.
2. `[STATUS: SECURITY ASSESSMENT COMPLETE]` — Security audit and penetration tests executed; findings documented.
3. `[STATUS: SECURITY REMEDIATION VERIFIED]` — Approved security remediations implemented and retested.
4. `[STATUS: RELEASE APPROVED]` — All release criteria and approvals satisfied.

---

## 9. Standard 10-Section Response Format for Future Tasks

For every future engineering task assigned to AI or engineering staff, the progress and completion reports must follow this structured 10-section format:

1. **Requirement Analysis** — Core requirements, scope, affected modules, and identified constraints.
2. **Threat Model** — STRIDE analysis, trust boundaries, entry points, and abuse cases.
3. **Test Plan** — Unit, feature, and security test cases designed prior to implementation.
4. **Implementation Summary** — Code changes made, architecture adhered to, files modified.
5. **Automated Test Evidence** — Exact CLI commands, execution output, passed/failed counts.
6. **Security Assessment** — Auditing tools run (Composer Audit, npm audit, static checks) and results.
7. **Penetration-Test Report** — Controlled attack simulation scenarios, CVSS classification, evidence.
8. **Decision Required** — High/Medium findings presented under the Stop-and-Discuss rule for user decision.
9. **Post-Approval Verification** — Regression tests and re-verification of approved fixes.
10. **Final Handover** — Residual risks, documentation updates, and final status labels.

---

## 10. Automated CI/CD & Local Repository Enforcement

To ensure this standard is not merely documentary but actively enforced, the repository provides the following command gates:

### Local Quality Gate Commands
```bash
# 1. Run full test suite with config isolation
composer test
# or inside docker container:
docker compose exec -T app-1 php artisan test

# 2. Run targeted security tests
docker compose exec -T app-1 php ./vendor/bin/phpunit tests/Feature/Auth/TwoFactorSecurityTest.php

# 3. Check code style and formatting
./vendor/bin/pint --test

# 4. Dependency security audit
composer audit
npm audit
```

### Git Pre-Commit & Pre-Push Policy
No code shall be merged into main branches if:
- Any unit, feature, or security test fails.
- Dependency audit reveals an unpatched Critical or High CVE.
- Any new endpoint lacks authentication middleware or explicit authorization policy gates.
