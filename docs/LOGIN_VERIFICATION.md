# Login verification

The redesigned login page preserves the CRM's **username and password** contract, existing account recovery link, remember option, role-based redirect, and two-factor enforcement. Email login is not supported by the current authentication request.

Reverified on 23 October 2026 after the production asset build: **13 tests, 97 assertions passed** in `tests/Feature/Auth/AuthenticationTest.php`, plus **10 frontend behavior tests** in `tests/Frontend/login.test.mjs`. Isolated Blade compilation, PHP syntax validation, and Pint formatting checks also passed. No production database or real user account was used.

## Run the focused checks

Use PHP 8.2 or newer with the project's Composer development dependencies and PDO SQLite enabled. Build the Vite assets before checking the rendered page:

```bash
npm run build
mkdir -p /tmp/nhmp-login-views
APP_ENV=testing APP_URL=http://localhost FORCE_HTTPS=false \
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
APP_CONFIG_CACHE=/tmp/nhmp-auth-no-config.php \
APP_ROUTES_CACHE=/tmp/nhmp-auth-no-routes.php \
VIEW_COMPILED_PATH=/tmp/nhmp-login-views \
php vendor/bin/phpunit tests/Feature/Auth/AuthenticationTest.php --display-warnings
```

The test class explicitly selects its own `auth_testing` connection backed by SQLite `:memory:` and creates only the schema needed by authentication. It does not run `RefreshDatabase`, production migrations, or application seeders. The fixture avoids the master migration's MySQL-specific partitioning and the legacy user factory's obsolete fields. Array-backed cache and sessions keep test state isolated.

The WSL PHP runtime used for this verification lacked PDO database drivers. SQLite support was downloaded with `apt download php8.3-sqlite3=8.3.6-0maysync1`, extracted into `scratch/auth-test-runtime/extracted` using `dpkg-deb -x`, and loaded for the individual PHP process with:

```bash
php -d extension="$PWD/scratch/auth-test-runtime/extracted/usr/lib/php/20230831/pdo_sqlite.so" \
    vendor/bin/phpunit tests/Feature/Auth/AuthenticationTest.php --display-warnings
```

Use that PHP invocation with the environment overrides above when reproducing the same runtime. The Ubuntu base package was used because the cached updates-package URL returned HTTP 404. Select an available package compatible with your PHP runtime. No system packages were installed. The extension path is specific to PHP 8.3's module ABI.

To compile templates without changing the application's normal view cache:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
APP_CONFIG_CACHE=/tmp/nhmp-auth-no-config.php \
APP_ROUTES_CACHE=/tmp/nhmp-auth-no-routes.php \
VIEW_COMPILED_PATH=/tmp/nhmp-login-views \
php artisan view:cache
```

## Covered behavior

Run the dependency-free frontend checks with `node --test tests/Frontend/login.test.mjs`. They exercise the actual login script with browser/DOM mocks, including initial and changing reduced-motion preferences, saved pause preferences, hidden tabs, unavailable storage, accessible password toggling, loading and browser-back recovery, and error focus.

The revised layout was inspected at 1440×900, 1366×768, 1024×768, 390×844, 360×640, 320×568, and 844×390. Normal login states matched the viewport dimensions without page overflow or internal form-card scrolling. The invalid-credentials state also fit without page or card scrolling on desktop and at 390×844, 360×640, 320×568, and 844×390.

Browser interaction checks confirmed password show/hide, remember selection, successful sign-in to the real agent workspace, and logout back to login. The shared pause control set both the page and scene to their paused state; computed animation states confirmed that route progression and the moving Tiger vehicle were paused. CSS also disables animation for reduced-motion users and keeps the complete SVG still without JavaScript.

Live HTTP checks used the isolated preview with active CSRF middleware. A login POST without a CSRF token returned **419**. An invalid password returned the visible credential error, retained username, and omitted the password. Valid credentials rendered the real agent workspace at `/calls/create` with **200** and issued a remember cookie. POST logout returned to login; a subsequent protected heartbeat request redirected to login. The account recovery page returned **200**. A separate cookie jar kept these checks independent of the visual browser session.

| Case | Verified behavior |
| --- | --- |
| Login page | Renders username/password fields, CSRF token, and account recovery link. |
| Valid credentials | Authenticates, regenerates the session, records login, updates last login, and redirects an agent to the configured workspace. |
| Intended destination | Returns the authenticated user to a previously requested protected route. |
| Invalid password | Shows the credential error, retains username, omits password, and records a failed-login audit entry. |
| Unknown username | Rejects authentication with a field error. |
| Missing credentials | Requires both username and password. |
| Repeated attempts | Returns HTTP 429 after the configured route limit. |
| Inactive account | Rejects authentication with the existing administrative message. |
| Locked account | Rejects authentication while an existing lock is active. |
| Remember me | Issues a recaller cookie and persists its token. |
| Logout | Ends authentication, records logout, and blocks protected routes afterward. |
| Required two-factor verification | Blocks access, rejects an invalid code, accepts a current code, and clears two-factor session state on logout. |
| Required two-factor setup | Redirects a user without a secret to setup. |

These checks execute the real authentication routes, request, controller, and middleware. They cover the focused login flow; they do not establish that the full application test suite or production MySQL migrations pass. Browser verification used a separate scratch SQLite database with synthetic data and isolated session/view/log directories. After verification, the owned preview server was stopped and the exact generated `scratch/auth-test-runtime` directory, including the synthetic database, sessions, and extracted extension, was removed. Preexisting scratch files were preserved.

## Existing backend findings

These issues were identified during verification and left outside the visual redesign:

| Finding | Location and follow-up |
| --- | --- |
| Failed-attempt counters and timed locks may not persist. | `app/Http/Requests/Auth/LoginRequest.php:70` updates `failed_attempts` and `locked_until`, but `app/Models/User.php:16` omits both from `$fillable`. The successful-login reset at `app/Http/Controllers/Auth/AuthenticatedSessionController.php:37` has the same restriction. Review allowed updates and add a regression check for five failures followed by a timed lock. Route throttling and rejection of an existing lock passed. |
| Successful logins create duplicate login records. | Both `app/Listeners/LogSuccessfulLogin.php:13` and `app/Http/Controllers/Auth/AuthenticatedSessionController.php:26` create a record. The listener is registered at `app/Providers/AppServiceProvider.php:29`. Consolidate ownership and verify that logout closes the corresponding single record. |
| The supervisor-only two-factor policy references missing constants. | `app/Http/Middleware/TwoFactorEnforcement.php:25` uses `Role::ADMIN` and `Role::SUPERVISOR`, which are absent from `app/Models/Role.php`. Select the intended existing roles and add a policy-mode regression check. The `all` policy mode passed. |
