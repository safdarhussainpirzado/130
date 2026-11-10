# NHMP 130 CRM — Testing Framework Quick Reference

Welcome to the NHMP 130 CRM automated testing framework. This testing infrastructure is permanent, reusable, and maintained directly within the repository.

## The Three Permanent Requirements
1. **New Features:** Require tests before implementation (TDD).
2. **Bug Fixes:** Require a regression test reproducing the original defect.
3. **Releases:** Must run the full test suite before approval.

---

## Quick Start

### 1. Run Tier 1 Fast Tests (Every Commit & PR)
```bash
# Run all fast tests (Unit + Validation + Resources + Frontend)
npm run test:fast
```
*Execution time: < 1 second. Does not require external database or browser.*

### 2. Run Tier 2 Browser Tests (Release Gate)
```bash
# Run Playwright browser automation against candidate edge
npm run test:browser

# Run specific browser spec:
npx playwright test tests/Browser/auth.spec.ts
npx playwright test tests/Browser/dashboard.spec.ts
npx playwright test tests/Browser/calls.spec.ts
npx playwright test tests/Browser/reports.spec.ts
```

### 3. Run Security & MFA Audit
```bash
npm run test:security
```

---

## Test Organization
- `tests/Unit/Resources/`: Verifies data minimization and ensures relationships are loaded without lazy SQL queries (N+1 immunity).
- `tests/Unit/Validation/`: Verifies strict input validation rules (phone numbers, latitude/longitude bounds, Urdu Unicode).
- `tests/Unit/`: Math, response time metrics, and statistical aggregators.
- `tests/Frontend/`: Node.js tests for accessible UI and client logic.
- `tests/Browser/`: Playwright end-to-end browser test suites.
- `tests/Support/`: Reusable factories (`CallFixtureFactory`, `TestUserFactory`, `TotpTestHelper`).

For full policy documentation, see [docs/TESTING_POLICY.md](file:///home/mrpirzado/projects/130/docs/TESTING_POLICY.md).
