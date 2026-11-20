# HANDOFF FOR GEMINI — Make Push Notifications Production-Ready (Desk/CRM scope)

Author: Claude (review), 2026-11-21. Owner/approver: Safdar Hussain.
Read this whole file before touching code. Follow it literally; where it says STOP, stop and ask.

## 0. Context and ground rules

- Production: Laravel 13 on RoadRunner, MySQL 8.4 Group Replication, Redis, HAProxy + nginx. Zero downtime, zero data loss.
- `main` is at `a0c0b0a` and is what production runs. Your ~32 uncommitted files sit on top of it in `/home/mrpirzado/projects/130`.
- **Do NOT commit, push or deploy anything.** Work in a new branch `feat/push-notifications-desk` created from `main` in a *separate git worktree* (`git worktree add ../130-push -b feat/push-notifications-desk main`). Claude will review, then deploy.
- Never put secrets (Firebase JSON, VAPID private key, tokens) in git, `.env.example`, tests, logs, docs or chat.
- No tests or test data against production. Use the disposable local DB only.

## 1. Scope — what ships vs. what is held

### SHIP (desk / CRM scope only)
- Web Push (browser) + FCM-capable service layer for **desk users**: agents, supervisors, sector operators, zone in-charge, operation admins.
- Triggers: **pending help created**, **travel advisory** lifecycle (created, approved, rejected, edited, extended, cleared), **support tickets** (created, status/action).
- Service worker `public/sw.js`, `public/js/webpush-client.js`, enrolment UI, `push_subscriptions` migration, subscribe/unsubscribe web routes.
- Collapsed-sidebar floating tooltip (UI-only) — ship, but as its own commit.

### HOLD (do NOT include in the shipping branch; leave in the old working tree)
- Humsafar commuter APIs, patrol app APIs, Women's Journey and Case Chat removal, `PatrolDutySession` linking, new call statuses (`assigned`, `acknowledged`, `review_pending`), `availablePatrols`, `agent_id` nullable migration, SOS sub-type migration, `routes/api.php` changes, `PushNotificationController` (`/api/v1/push/register|unregister` — mobile token registration), Postman changes, `HamsafarEcosystemSeeder`, `scripts/complete_all_pending_helps.php`.
- The shipping branch must contain **no patrol/commuter code paths**. `PushNotificationService::gatherFcmTokensForUsers` must not read `patrolOfficerProfile`; `notifyHelpPending` must not query `patrol_officer_profiles`. Keep any future patrol recipient logic behind a clearly separated, not-wired extension point (interface + doc note), not as live code.
- The FCM service itself ships (desk usage, future mobile), but the mobile register endpoint is held.

How to split: start from clean `main`; copy over only the SHIP files; for mixed files (`CallService.php`, `CallController.php`, `layouts/app.blade.php`, `Call.php`) re-apply **only** the push-hook / tooltip hunks by hand. Produce a file-by-file inclusion table in the engineering doc.

## 2. Defects found in review — all must be fixed

1. **Web Push is a stub.** `minishlink/web-push` is not installed, so `WebPushService` logs a "Simulation" and returns success. Install it (`composer require minishlink/web-push`, pinned, commit `composer.lock`). Remove the simulation branch entirely. If the library or keys are missing, fail closed (`success=false`, one logged error), never fake success.
2. **Fake VAPID keys hardcoded** in `WebPushService` (documentation sample + garbage private key). Remove all key fallbacks. Generate a real pair, read it only from secrets (section 3). Add `php artisan push:generate-vapid` that prints the public key and writes the private key to the secrets file with mode 600 (never prints the private key).
3. **FCM OAuth token is wrong.** `FcmService` builds the JWT with `base64_encode`; JWT needs base64url without padding. Use `google/auth` (`ServiceAccountCredentials`, scope `https://www.googleapis.com/auth/firebase.messaging`), cache the token ~50 min in Redis. Remove the hand-rolled JWT.
4. **FCM fake success.** With no credentials it returns `simulated => true, success => true`. Change to `success=false, error=NOT_CONFIGURED` plus one rate-limited log line. Add `PUSH_FCM_ENABLED` (default false) so absence is explicit.
5. **Synchronous sending inside the DB transaction** (`CallService::createCall`, per-token blocking HTTP with 5 s timeouts). Replace with queued jobs `SendHelpPendingPush`, `SendAdvisoryPush`, `SendSupportPush` (`ShouldQueue`, `afterCommit`, `tries=3`, backoff `[10,60,300]`, `timeout=30`, queue `push`). Hooks must never throw into the main action and add <5 ms to a request. Use `Http::pool`/chunked concurrency for FCM and `queueNotification` + `flush()` for Web Push.
6. **Recipient scope is far too wide.** Today every supervisor/admin nationwide is paged for every help at max priority/siren. Scope to users whose `UserScope` covers the call's beat/sector/zone, plus supervisors whose scope covers that zone. Operation admins/super admins only for P1. De-dup per `(call_id, user_id)` via `Cache::add` (10 min TTL) and rate-limit per user (e.g. 30/min).
7. **Dead-token handling.** Web Push 404/410 and FCM `UNREGISTERED`/`INVALID_ARGUMENT` deactivate the subscription. Scheduled daily prune of inactive rows older than 30 days.
8. **Subscribe endpoint hardening** (`WebPushSubscriptionController`): authenticated, `throttle:20,1`; `endpoint` must be `https://` with host in an allow-list (`fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.notify.windows.com`, `web.push.apple.com`) to prevent SSRF; `keys.p256dh`/`keys.auth` base64url with length checks; cap per user (e.g. 10); unsubscribe only own endpoint (no IDOR). `token`, `auth_token`, `public_key` encrypted at rest using the project's existing encrypted-cast / GCM approach (see `MigrateEncryptedFieldsToGcm`, `docs/NHMP_130_CRYPTOGRAPHIC_ARCHITECTURE_AND_MIGRATION.md`); lookup via `token_hash`.
9. **Payload hygiene.** Push crosses Google/Mozilla/Apple infrastructure: no caller name, phone, CNIC or victim details. Allowed: call number, type name, beat/sector name, priority, URL. Title ≤ 80, body ≤ 200 chars. `data.url` must be a same-origin relative path; the service worker rejects anything else. Unit-test this.
10. **Service worker.** Serve `/sw.js` with `Cache-Control: no-cache`; `notificationclick` opens only same-origin URLs; `requireInteraction` only for P1/P2 helps, not advisory/support. Confirm CSP/nginx allow the worker and push endpoints.
11. **Enrolment UX.** No auto-prompt on page load. Header control "Enable desktop alerts" calls `Notification.requestPermission()` on click, shows state (on / blocked / unsupported), per-user opt-out stored server-side.
12. **Migration.** Verify up/down/up on a disposable MySQL 8.4 DB; foreign key types match; no index on a `text` column.
13. **Hooks.** Re-check `TravelAdvisoryController::pushEvent` mapping: operator create/edit → supervisors; supervisor approve/reject/edit/extend/clear → originating sector. Model-event hooks for `SupportRequest`/`SupportRequestAction` dispatch queued jobs, not inline sends.
14. **Tooltip.** Verify keyboard focus/blur, touch, resize cleanup. Separate commit.
15. No dead code, no commented-out blocks, no `env()` outside `config/*.php` (config cache returns null).

## 3. Where the Google Cloud / Firebase key goes

The owner has **not** provided a Firebase key. Build so it works when dropped in and degrades safely (clear log + `push:status` warning, no fake success) when absent.

- Firebase console → Project settings → Service accounts → "Generate new private key" → JSON. Least-privilege role: **Firebase Cloud Messaging API Admin**.
- **Never commit it.** Host file `secrets/firebase_credentials.json` (the `/secrets/` dir is gitignored, dir mode 700, file mode 600), same pattern as `app_key`, `db_password`.
- In `docker-compose.octane.yml` add secret `firebase_credentials` mounted read-only at `/run/secrets/firebase_credentials` on app nodes **and the queue/scheduler container**; env `FIREBASE_CREDENTIALS_FILE=/run/secrets/firebase_credentials`. Same for `vapid_private_key` → `VAPID_PRIVATE_KEY_FILE`.
- `config/services.php`: `firebase` (`project_id`, `credentials_file`, `enabled`) and `webpush` (`public_key`, `private_key_file`, `subject`). Reuse the existing `*_FILE` secret-loading mechanism (see how `APP_KEY_FILE`/`DB_PASSWORD_FILE` are resolved). Public values (VAPID public key, project id) may be in `.env`; placeholders only in `.env.example`.
- Extend `scripts/create_secrets.sh` (placeholders only, never real values).
- Add `php artisan push:status`: library installed, keys present/valid format, FCM token fetch OK, queue worker alive, subscription counts — never prints secrets.
- A `push` queue consumer is required (Redis, `php artisan queue:work redis --queue=push --max-time=3600`) in the scheduler container or a dedicated service, not the web nodes. Document it and its health check.
- The engineering doc must contain copy-paste owner steps for placing the key.

## 4. Testing — run everything, save real output

Environment: no Docker inside WSL for you. Use the existing harness — image `130-app-octane:phase2-rc25`, `--network host`, disposable DB `130_crm_test_disposable` on port 3309, `HASH_DRIVER=bcrypt`, APP_KEY/BIDX_SALT/AUDIT_SALT set. Ask Safdar/Claude to start it rather than installing PHP drivers on the host. **Never claim a test passed unless you ran it; paste real output.**

1. **Unit** (`tests/Unit/Notifications/`): recipient resolution per beat/sector/zone/role; de-dup + rate limit; payload sanitisation (no PII); URL allow-listing; subscription validation; FCM request construction (`Http::fake`); token deactivation on 404/410/UNREGISTERED; Web Push with mocked client; fail-closed when keys absent.
2. **Feature** (`tests/Feature/Notifications/`): subscribe/unsubscribe (auth, throttle, SSRF host rejected, IDOR rejected, per-user cap); pending help dispatches exactly one queued job after commit and the request still succeeds if the push layer throws; advisory lifecycle both directions; support ticket flow. `Queue::fake()`/`Bus::fake()`.
3. **Real Web Push round-trip** against a local mock push service proving payload encryption and VAPID signature are accepted (replaces the simulation).
4. **Security / pen tests**: SQLi and XSS payloads in advisory titles/support text through payloads and the worker; SSRF via `endpoint` (internal IPs, `http://`, redirects, DNS-rebind-style hosts); CSRF on subscribe/unsubscribe; IDOR; mass assignment on `PushSubscription`; secret/token leakage in logs and responses (grep logs after run); open redirect via `data.url`; flood/replay; authz — low-role user cannot subscribe to supervisor-only streams. Deliver a findings table (ID, severity, status).
5. **Browser (Playwright)**: permission → subscribe → row stored → server push → notification shown; click opens correct same-origin URL; denied-permission UI; unsubscribe; tooltip on hover and keyboard focus; no console errors. Screenshots in `docs/evidence/push-notifications/`.
6. **Regression**: full suite on shipping branch vs `main`; failure lists must be identical (baseline ~60 pre-existing failures — document, don't fix unrelated ones). Include `TravelAdvisoryApprovalWorkflowTest`, `CallFlowTest`, `CallReminderTest`, `DashboardIncidentRoadSnapTest`.
7. **Performance**: 200 helps created locally with 50 fake subscriptions; request latency with push on vs off differs <10 ms.
8. **Static**: `php -l`, Pint/PHPStan at configured level, `composer audit`.

## 5. Deliverables (in the shipping branch; nothing committed to main)

1. Working code per sections 1–3.
2. Test evidence in `docs/evidence/push-notifications/test-run-YYYYMMDD.txt` (real output).
3. **Engineering document** `docs/NHMP_130_PUSH_NOTIFICATION_ARCHITECTURE.md`: purpose and SHIP/HOLD table; sequence diagram (trigger → afterCommit job → queue → FCM/WebPush → worker); data model; recipient matrix (event × role × channel × priority); security model and threat table; secrets handling + owner key-placement guide (Firebase + VAPID); config reference; queue/worker operations; monitoring (queue depth, failure rate, dead-token rate); failure modes + runbook; **rollout plan** (migration first, deploy dark, enable per environment, canary users); **rollback plan** (`PUSH_ENABLED=false`, stop consumer; migration is additive and safe to leave); test evidence summary; known limitations; file-by-file inclusion table.
4. Master switch `PUSH_ENABLED` (default false) so code deploys dark.
5. `HANDBACK.md`: files changed, how to run tests, what is NOT verified, anything not done.

## 6. Definition of done (Claude will check each)

- [ ] Branch from clean `main`; **no** patrol/Humsafar/commuter/Women's-Journey/mobile-register code; no secrets in diff.
- [ ] `minishlink/web-push` and `google/auth` installed with lock file; zero simulation/fallback-key code.
- [ ] Real encrypted Web Push round-trip passes locally; FCM verified with fakes plus a documented live-test procedure for when the key exists.
- [ ] Sending is queued, after-commit, rate-limited, de-duplicated, scoped; request overhead measured.
- [ ] Tokens encrypted at rest; SSRF/IDOR/CSRF/XSS tests pass; no PII in payloads.
- [ ] Tests green or identical to `main` baseline; evidence files present.
- [ ] Engineering document complete incl. owner key guide and rollback.
- [ ] You state plainly what you did NOT verify.

## 7. STOP conditions — ask Safdar first

- Anything needing production access, a real Firebase key, or sending a real push.
- Any change to an existing table other than the new `push_subscriptions`.
- Any change to authentication, sessions, RBAC or the audit hash chain.
- Anything that would restart production services.
