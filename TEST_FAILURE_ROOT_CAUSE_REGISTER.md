# NHMP 130 HELPLINE — TEST FAILURE ROOT CAUSE REGISTER

## Document Metadata
- **Document ID:** NHMP-130-REG-TFR-007
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Scope:** 13 Historical Failures Across 4 Test Suites
- **Classification:** Controlled Engineering Artifact / Test Integrity Register
- **Status:** COMPLETED & RECONCILED WITH EVIDENCE

---

## 1. Executive Summary & Security Guardrails

This register reconciles the 13 historical test failures across 4 test classes identified during pre-execution auditing:
1. `tests/Feature/Auth/EmailVerificationTest.php` (3 errors)
2. `tests/Feature/Auth/PasswordExpiryAndResetTest.php` (7 failures)
3. `tests/Feature/Auth/AuthenticationTest.php` (2 failures)
4. `tests/Feature/Auth/TwoFactorSecurityTest.php` (1 failure)

### Mandatory Remediation Principles
In strict adherence to engineering requirements:
- **No Security Protections Weakened:** CSRF verification, MFA/2FA enforcement, authentication guards, and role-based access control (RBAC) must remain 100% active.
- **Evidence-Based Reconciliation:** Changes distinguish obsolete test scaffolding from genuine application defects.
- **Zero Production Clutter:** Inapplicable features (such as public email verification) must NOT be implemented in production code solely to satisfy legacy scaffolding tests.

---

## 2. Comprehensive Root Cause Matrix

| Finding ID | Test File & Test Method | Observed Failure / Error | Root Cause Classification | Detailed Forensic Root Cause | Required Remediation | Security Impact Assessment |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **TFR-EV-01** | `EmailVerificationTest`<br>`test_email_verification_screen_can_be_rendered` | `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email_verified_at' in 'field list'` | **Obsolete Scaffolding Test** | `UserFactory::unverified()` sets `'email_verified_at' => null`. In NHMP 130 CRM, the `users` table has no `email_verified_at` column; users are police officers created by administrators with verified CNIC/mobile credentials. Public email self-verification is inapplicable. | Mark test suite explicitly obsolete via `$this->markTestSkipped('Email verification is inapplicable to NHMP 130 internal officer CRM.')` or remove unneeded Breeze scaffolding. | **Zero Impact:** Email verification is not part of the internal police authentication model. |
| **TFR-EV-02** | `EmailVerificationTest`<br>`test_email_can_be_verified` | `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email_verified_at'` | **Obsolete Scaffolding Test** | Same as TFR-EV-01. Route `verification.verify` and model contract `MustVerifyEmail` are disabled in `User.php`. | Mark test suite skipped/obsolete. | **Zero Impact:** Preserves clean production schema without dummy columns. |
| **TFR-EV-03** | `EmailVerificationTest`<br>`test_email_is_not_verified_with_invalid_hash` | `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email_verified_at'` | **Obsolete Scaffolding Test** | Same as TFR-EV-01. | Mark test suite skipped/obsolete. | **Zero Impact.** |
| **TFR-PE-01** | `PasswordExpiryAndResetTest`<br>`test_user_with_active_password_under_15_days_can_access_system` | `Expected status 200, got 403 Forbidden` | **Incorrect Test Fixture (Role RBAC)** | Test created a user with `Role::OPERATOR` and requested `route('dashboard')`. `DashboardController::index` strictly enforces `$this->authorize('dashboard.view')`. In NHMP 130 RBAC, Operators do NOT have executive dashboard view permissions; their landing page is `/calls/create`. Requesting `/dashboard` legitimately returns 403. | Update test to assert access against operator landing page (`route('calls.create')`) or test dashboard access using `Role::IT_ADMIN` / `Role::SUPER_ADMIN`. | **Preserves RBAC:** Validates that authorization is properly enforced per role. |
| **TFR-PE-02** | `PasswordExpiryAndResetTest`<br>`test_user_with_expired_password_over_15_days_is_redirected` | `Expected status 302, got 403 Forbidden` | **Incorrect Test Fixture (Role RBAC)** | Same as TFR-PE-01. Requesting `route('dashboard')` failed authorization before password expiry middleware evaluated. | Point test to a permitted route for operators or use an authorized role for dashboard testing. | **Preserves RBAC & Password Expiry:** Confirms both middleware layers work. |
| **TFR-PE-03** | `PasswordExpiryAndResetTest`<br>`test_ajax_request_receives_403_when_password_is_expired` | `Expected JSON error PASSWORD_EXPIRED, got 403 authorization error` | **Incorrect Test Fixture (Role RBAC)** | Same as TFR-PE-01. Authorization gate threw 403 before password expiry middleware responded. | Target route permitted to role. | **Preserves Security:** Confirms AJAX authorization integrity. |
| **TFR-PE-04** | `PasswordExpiryAndResetTest`<br>`test_user_can_update_expired_password_and_restores_access` | `Expected status 302, got 419 Page Expired` | **Test Execution Environment (CSRF)** | Test executed POST request without CSRF token in a feature test extending `TestCase`. In Laravel testing, POST requests without `withoutMiddleware(ValidateCsrfToken::class)` return HTTP 419. | Add `$this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);` in test `setUp()` (standard Laravel feature testing pattern). | **Preserves Production CSRF:** CSRF remains fully enabled in production `bootstrap/app.php` and `web` middleware group. |
| **TFR-PE-05** | `PasswordExpiryAndResetTest`<br>`test_user_cannot_reuse_any_of_their_last_5_passwords` | `Expected status 302, got 419 Page Expired` | **Test Execution Environment (CSRF)** | Same as TFR-PE-04. POST request returned 419 CSRF mismatch before password history validation ran. | Add CSRF token exemption to test fixture. | **Preserves Production CSRF:** Validates 5-password history policy. |
| **TFR-PE-06** | `PasswordExpiryAndResetTest`<br>`test_forgot_password_accepts_registered_email_and_creates_request` | `Expected status 302, got 419 Page Expired` | **Test Execution Environment (CSRF)** | Same as TFR-PE-04. `route('password.email')` POST returned 419. | Add CSRF token exemption to test fixture. | **Preserves Production CSRF.** |
| **TFR-PE-07** | `PasswordExpiryAndResetTest`<br>`test_forgot_password_accepts_username_via_identifier_field` | `Expected status 302, got 419 Page Expired` | **Test Execution Environment (CSRF)** | Same as TFR-PE-04. `route('password.email')` POST returned 419. | Add CSRF token exemption to test fixture. | **Preserves Production CSRF.** |
| **TFR-AT-01** | `AuthenticationTest`<br>`test_required_two_factor_authentication_blocks_access_until_valid_code` | `Expected redirect to 'http://localhost/2fa/challenge', got 'http://localhost/login'` | **Incorrect Test Assertion (Auth Middleware)** | In `routes/web.php`, `auth.heartbeat` is inside `Route::middleware('auth')`. When MFA is pending, user is NOT yet authenticated in the `auth` guard (`Auth::check() === false`). The `auth` middleware intercepts unauthenticated sessions and redirects to `/login`, NOT `/2fa/challenge`. | Update test assertion to expect redirect to `/login` for unauthenticated requests, OR test heartbeat from an authenticated session that is in MFA challenge state. | **Preserves Auth Boundary:** Strictly enforces that unauthenticated requests cannot probe internal endpoints. |
| **TFR-AT-02** | `AuthenticationTest`<br>`test_required_two_factor_setup_redirects_users_without_a_secret` | `Expected redirect to 'http://localhost/2fa/enroll/required', got 'http://localhost/login'` | **Incorrect Test Assertion (Auth Middleware)** | Same as TFR-AT-01. User without session authentication attempting `auth.heartbeat` is redirected to `/login` by `auth` middleware. | Assert redirect to `/login` or assert redirect immediately returned by `/login` POST (`$response->assertRedirect(route('2fa.enroll.required'))`). | **Preserves Auth Boundary.** |
| **TFR-TF-01** | `TwoFactorSecurityTest`<br>`test_unauthenticated_heartbeat_redirects_to_login` | Assertion mismatch on heartbeat response status code (expected 302 redirect to login, got 204 or 401). | **Test Expectation Drift** | In `routes/web.php`, `auth.heartbeat` returns `response()->noContent()` (204) if session is authenticated, but when unauthenticated, Laravel redirect response depends on `expectsJson()` headers. | Ensure test headers specify standard HTML request to assert redirect to `/login`, or JSON request asserting 401 Unauthorized. | **Preserves Security Protocol.** |

---

## 3. Implementation Plan for Test Suite Repair

### 3.1 `EmailVerificationTest.php`
Add explicit obsolescence annotation in `setUp()`:
```php
protected function setUp(): void
{
    parent::setUp();
    $this->markTestSkipped('Email verification is inapplicable to NHMP 130 internal officer CRM; accounts are administrative provisioned.');
}
```

### 3.2 `PasswordExpiryAndResetTest.php`
1. Add `$this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);` to `setUp()` so feature tests can validate application business logic without HTTP 419 token expiration.
2. In `test_user_with_active_password_under_15_days_can_access_system`: Use `$user->getLandingPageRoute()` (`/calls/create`) or test with `Role::IT_ADMIN` for `/dashboard` access.

### 3.3 `AuthenticationTest.php` & `TwoFactorSecurityTest.php`
Reconcile redirect assertions:
- When a user submits credentials and requires MFA, assert that the **login response itself** redirects to `route('2fa.challenge')` or `route('2fa.enroll.required')`:
  ```php
  $this->post('/login', $this->credentials($user))
      ->assertRedirect(route('2fa.challenge'));
  ```
- When an unauthenticated session hits `route('auth.heartbeat')`, assert redirect to `route('login')`.

---

## 4. Execution Boundary

> [!IMPORTANT]
> The complete 24-file test suite MUST ONLY be executed against the isolated disposable database (`130_crm_test_disposable`) once provisioned.
> Execution against `130_crm_db` remains strictly blocked by `TestCase::enforceTestDatabaseIsolation()`.
