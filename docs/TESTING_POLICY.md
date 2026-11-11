# NHMP 130 CRM — Permanent Testing & Quality Assurance Architecture Directive

> **Official Development Standard**  
> **Repository:** National Highways & Motorway Police (NHMP) 130 CRM  
> **Status:** Mandatory & Active

---

## 1. Executive Directive

Testing is a **permanent, foundational part of the NHMP 130 CRM development architecture**, not a one-time pre-production exercise. 

Every developer, engineer, and automated agent contributing to this repository must adhere strictly to the **Three Permanent Engineering Requirements** defined below.

---

## 2. The Three Permanent Engineering Requirements

### Rule 1: Every New Feature Requires Tests Before Implementation (TDD)
- **Principle:** No new controller, API endpoint, service method, or frontend interface may be written without first defining the test specification that verifies its expected behavior.
- **Workflow:**
  1. Author the failing test in `tests/Unit/` (for domain logic, resources, validation) or `tests/Feature/` (for HTTP endpoints).
  2. Implement the minimal clean code to make the test pass.
  3. Refactor with guaranteed test coverage.
- **Enforcement:** Code reviews and CI will reject any pull request containing new business logic without corresponding automated tests.

### Rule 2: Every Bug Fix Requires a Regression Test That Reproduces the Original Defect
- **Principle:** A bug fix is considered unverified and unaccepted until a reproducible automated test is committed alongside the fix.
- **Workflow:**
  1. Write a regression test reproducing the exact failure mode (asserting the bug exists in the unpatched state).
  2. Apply the code remediation.
  3. Execute the regression test to prove the defect is resolved and cannot regress in future builds.
- **Documentation:** Reference the defect ticket or identifier (e.g., `DEF-01`, `DEF-07`) in the test method docblock.

### Rule 3: Every Release Must Run the Reusable Automated Test Suites Before Approval
- **Principle:** Production and staging promotions are strictly gated behind automated verification. No manual "looks good to me" approvals are permitted.
- **Workflow:**
  1. Execute Tier 1 (Fast Unit & Serialization Suites).
  2. Execute Tier 2 (Playwright Browser Suites, Security & MFA Penetration Suites).
  3. Verify strict infrastructure invariants (e.g., operational database sequence freeze).
  4. Generate signed test and security execution audit reports before release sign-off.

---

## 3. Two-Tier Testing Architecture: Fast Commits vs. Comprehensive Releases

To achieve comprehensive coverage without making everyday development slow, tests are segregated into two distinct operational tiers:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        NHMP 130 TESTING LIFECYCLE                      │
├───────────────────────────────────┬────────────────────────────────────┤
│ TIER 1: FAST COMMIT & PR GATE     │ TIER 2: RELEASE CANDIDATE GATE     │
│ (Sub-second execution: < 3s)      │ (Deep verification: 1-5 minutes)   │
├───────────────────────────────────┼────────────────────────────────────┤
│ • Pure Unit Tests                 │ • Playwright Browser E2E Tests     │
│ • API Resource Serializers (N+1)  │ • Full Auth & 2FA State Machines   │
│ • Form & Input Validation Rules   │ • Interactive Maps & KPI Dashboards│
│ • Calculation & Metrics Engines   │ • OWASP Penetration & Fuzzing      │
│ • Fast Frontend Unit Tests        │ • DB Invariant & Freeze Checks     │
│                                   │ • Multi-Node Load Balancing Checks │
│ Execution: On every commit & PR   │ Execution: Nightly & Pre-Release   │
│ Command: npm run test:fast        │ Command: npm run test:release      │
└───────────────────────────────────┴────────────────────────────────────┘
```

---

## 4. Test Directory Structure

All testing assets are maintained permanently inside the repository:

```
tests/
├── Browser/                      # Tier 2: Playwright Headless Browser Automation
│   ├── helpers/
│   │   └── auth.ts               # RFC 6238 TOTP generator & login automation helper
│   ├── auth.spec.ts              # Login page, accessibility, password toggle, 2FA challenge
│   ├── dashboard.spec.ts         # KPI cards, Leaflet map, Chart.js graphs, console error checks
│   ├── calls.spec.ts             # Table pagination, fast-path search, status transitions
│   └── reports.spec.ts           # Monthly/hourly report aggregation breakdowns
│
├── Feature/                      # Feature & API Integration Tests
│   └── Auth/
│       └── TwoFactorSecurityTest.php # MFA state machine, rate limits, session hijacking
│
├── Frontend/                     # Tier 1: Fast Node.js Frontend Logic Tests
│   └── login.test.mjs            # Client-side validation, password toggles, motion preferences
│
├── Support/                      # Shared Reusable Test Helpers & Factories
│   ├── TotpTestHelper.php        # Pure PHP RFC 6238 TOTP generator
│   ├── TestUserFactory.php       # Synthetic user factory across all 7 operational roles
│   └── CallFixtureFactory.php    # Emergency, P1/P2/P3, and Urdu Unicode call fixtures
│
└── Unit/                         # Tier 1: Fast Sub-Second In-Memory Unit Tests
    ├── CallTypeSummaryReportTest.php    # Matrix aggregation, monthly/hourly buckets
    ├── HelpResponseTimeMetricsTest.php  # Response time calculations & SLA buckets
    ├── Resources/
    │   ├── CallListResourceTest.php     # Data minimization & N+1 relation guards
    │   └── UserListResourceTest.php     # User serialization & relation guards
    └── Validation/
        └── CallInputValidationTest.php  # Mobile numbers, coordinates, Urdu text validation
```

---

## 5. Standard Test Execution Commands

### Tier 1: Fast Developer Gate (Run Frequently)
```bash
# Run all fast unit tests (PHP + Node)
npm run test:fast

# Or individually:
composer test:fast
node --test tests/Frontend/*.test.mjs
```

### Tier 2: Comprehensive Browser & Release Gate (Run Before Release)
```bash
# Run Playwright browser automation against candidate edge
npm run test:browser

# Run security & penetration audit
bash scripts/test/run_security.sh

# Run complete release candidate verification pipeline
bash scripts/test/run_release.sh
```

---

## 6. Continuous Integration & Release Gates

Pull requests must achieve 100% pass rate on Tier 1 tests.  
Release tags (`v*.*.*`) automatically trigger Tier 2 release workflows:
1. **Technical QA Gate:** 100% automated test pass, zero N+1 query regressions.
2. **Security QA Gate:** Zero OWASP Top 10 findings, 2FA bypass immunity, secret shielding.
3. **Operational UAT Gate:** Role permissions verified across all 7 operational tiers.
4. **Data Freeze Gate:** Strict confirmation that operational staging sequence counter remains frozen.
