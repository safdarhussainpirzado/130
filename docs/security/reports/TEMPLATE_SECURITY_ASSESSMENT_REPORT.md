# Security Assessment Report Template

**File Naming Convention:** `docs/security/reports/<feature-name>-security-assessment.md`  
**Reference Standard:** [SECURITY_FIRST_DEVELOPMENT_STANDARD.md](file:///home/mrpirzado/projects/130/docs/engineering/SECURITY_FIRST_DEVELOPMENT_STANDARD.md)

---

# Security Assessment Report: [Feature Name]

- **Date:** YYYY-MM-DD
- **Target Component:** [Component / Subsystem Name]
- **Environment:** Local Docker Cluster / Testing Environment
- **Security Analyst / Engineer:** [Name / Agent]
- **Current Status:** `[STATUS: SECURITY ASSESSMENT COMPLETE]`

---

## 1. Feature Scope & Architecture Summary
[Describe what was implemented, affected routes, controllers, middleware, and database tables.]

---

## 2. Threat Model & Attack Surface (STRIDE)
[Summarize assets, trust boundaries, entry points, and specific threat scenarios evaluated.]

---

## 3. Test Suites & Automated Evidence
[List all executed unit, feature, integration, and security test suites, including exact CLI commands and results.]

```bash
# Test Execution Command:
php artisan test --filter=...
```

---

## 4. Controlled Penetration Testing Scenarios & Results
[Detail simulated attacks against the feature: unauthenticated bypass, horizontal/vertical privilege escalation, token replay, input fuzzing, rate limiting, and session invalidation.]

---

## 5. Security Findings & Vulnerability Classification (CVSS v4.0)

### Finding SEC-001: [Title]
- **Severity:** [CRITICAL | HIGH | MEDIUM | LOW | INFORMATIONAL]
- **CWE / OWASP:** [CWE-XXX]
- **CVSS v4.0 Score:** [X.X]
- **Description:** [Details]
- **Preconditions:** [Preconditions]
- **PoC / Reproduction:** [Steps]
- **Evidence:** [Sanitized Logs/Snippets]
- **Expected vs. Actual:** [Comparison]
- **Impact:** [Impact]
- **Remediation Options:** [Option A, Option B]
- **Trade-offs:** [Trade-offs]
- **Verification Tests:** [Tests]
- **Status:** Open / Awaiting Decision

---

## 6. Stop-and-Discuss: Decision Required
[Present prioritized options requiring user authorization before proceeding with discretionary remediation.]

---

## 7. Residual Risks & Operational Limitations
[Document any accepted residual risks, environment limitations, or operational constraints.]
