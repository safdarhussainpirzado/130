# REVIEW FIXES FOR GEMINI — feat/push-notifications-desk (round 2)

Reviewer: Claude, 2026-11-21. Worktree: `/home/mrpirzado/projects/130-push`. Same ground rules as `HANDOFF_PUSH_NOTIFICATIONS_FOR_GEMINI.md`: no push, no deploy, no secrets, no production access. Commit fixes on `feat/push-notifications-desk` as new commits (do not rewrite history).

Round-1 verdict: scope isolation, secrets handling, real libraries, queued after-commit design, SSRF/IDOR hardening and docs are good. The items below are real defects found by code review. Fix every one, add a regression test for each, re-run the **full** suite and replace the evidence file with real output.

## Must fix

### F1. Notification links point to pages that do not exist (404 on click)
`app/Services/Notifications/PushNotificationService.php`:
- Advisory url is `/travel-advisories`. Real page: `/ops/travel-advisory` (use `?status=pending_approval` for supervisor-bound "created/updated" events).
- Support url is `/admin/support-requests/{public_id}`. Real routes are under `mgmt/support` (admin, see `routes/web.php` ~line 207, `admin.support.*`) and `portal/support/requests/{supportRequest:public_id}` (requester, `portal.support.show`).
  - Notifications to **admins** → admin support show route.
  - Notifications to the **requesting user** → portal show route.
- Build every URL with `route(..., [], false)` (relative) instead of hand-written strings, so a route rename breaks a test instead of users. Keep `sanitizeRelativeUrl`.
- Add a test per event type that resolves the URL against the router (`app('router')->getRoutes()->match(Request::create($url))` must not throw 404).

### F2. P1 detection never matches
`notifyHelpPending` checks `in_array(strtolower($call->priority), ['critical','emergency','p1'])`. `calls.priority` is numeric (CallController validates `is_numeric`; 1 = P1). So `requireInteraction` is never set and admins are never paged for P1.
- Treat `(int) $call->priority === 1` as P1. Verify in code (`CallController`, dashboard SLA code uses P1=1, P2=2, P3+) what the numeric scale is, and use one shared constant/helper rather than a magic number.
- Priority label in the title should read `P1`, `P2`, … not the raw value upper-cased.
- Tests: priority 1 → admins/super admins included and `requireInteraction=true`; priority 2/3 → admins excluded, `requireInteraction=true` only for P1/P2 per the original brief (section 2.10).

### F3. Compose breaks deploys when secret files are missing
`docker-compose.octane.yml` declares file secrets `./secrets/firebase_credentials.json` and `./secrets/vapid_private_key` on **every** service. If either file is absent, `docker compose up` fails for the whole stack, which would break the rolling deploy of the app nodes.
- Make production deploy-safe: `scripts/create_secrets.sh` must create **empty placeholder files (mode 600)** for both when absent, and print a loud note. Empty file ⇒ service reports `NOT_CONFIGURED` (fail-closed), which is already the behaviour — verify `push:status` and both services treat an empty file as "not configured", not as an exception.
- Add tests/CLI check (`push:status`) proving empty-file handling.
- Document in the architecture doc: "create placeholders before the first deploy".

### F4. push-worker service is wrong in three ways
- It pins `130-app-octane:phase2-rc25`, which does not contain this code or the new composer packages. Do not hard-pin a new tag yourself; instead leave the image as a variable (`${NHMP_IMAGE:-130-app-octane:phase2-rc25}`) used by all services and note in the doc that a new image build (`composer install --no-dev` inside the build) is required before enabling. Check `Dockerfile.octane` actually installs the new packages (it builds from `composer.lock`; confirm no vendor/ is copied from the host).
- Add a **healthcheck** for the worker (e.g. a `push:status --health` mode that exits non-zero if the Redis `push` queue's oldest job is older than N minutes or the worker heartbeat key is stale). Add a heartbeat: the worker (or a scheduled command every minute) writes `push:worker:heartbeat` in Redis with a 3-minute TTL.
- Add a **queue-depth/failed-jobs check** to `push:status` and document the alert thresholds. Confirm the `failed_jobs` table exists in production schema (migration present?) — if not, add it to the shipping set as an additive migration, or set `QUEUE_FAILED_DRIVER=null` explicitly and document.
- Review the worker block for copy/paste drift versus the scheduler block (volumes, env, network, `depends_on`) and run `docker compose -f docker-compose.octane.yml config` (or `--dry-run`) to prove the file parses; save the output in the evidence folder with secrets masked.

### F5. Possible duplicate support notifications
`SupportRequest::created` dispatches a "created" push, and `SupportRequestAction::created` also dispatches one for every action — including the initial "submitted" action that is written when a ticket is created. Skip the `submitted` action type (see `SupportRequestAction::ACTION_SUBMITTED`) and add a test: creating one ticket produces exactly **one** queued push job.

### F6. Queue accumulates if the worker is down / feature disabled
The hooks dispatch jobs even when `PUSH_ENABLED=false`. With QUEUE_CONNECTION=redis and no consumer, jobs pile up in Redis forever.
- Short-circuit in the dispatch helpers: if `config('services.push.enabled')` is false, do not dispatch at all. Put this in one place (e.g. a small `PushDispatcher` or static helper) rather than in four hooks.
- Test: with the flag off, `Bus::fake()` shows zero jobs dispatched from all four hooks; with it on, exactly one.

## Should fix

### F7. Verify, do not assume
- `PayloadHygieneTest` asserts the *source file* does not contain certain strings. That is a weak check. Add behavioural tests that build payloads from calls/advisories/tickets whose fields contain a phone number, CNIC-like string, caller name and email, and assert none of them appear in `title`, `body` or `data`.
- Confirm advisory payload uses fields that exist on `TabletAdvisory` (`route_name`, `category`, `title`, `sector` relation) and a test with an advisory that has `sector_id = null` does not error.
- Confirm recipient queries are bounded (use `chunkById` or a cap + log when >500 recipients) and add an index review for the `UserScope` lookups used.

### F8. Service worker check
Re-read `public/sw.js`: `notificationclick` must open only same-origin relative URLs; `requireInteraction` only for P1/P2 helps; add a Playwright/Node test that feeds a `data.url` of `https://evil.example/x` and `//evil.example` and proves it falls back to `/dashboard`. Confirm nginx serves `/sw.js` with `Cache-Control: no-cache` (add to `nginx-octane.conf` and `nginx-octane-2.conf` if not set, with a small `location = /sw.js` block).

## Testing (must be real)

1. The previous run used the host PHP; also run the whole suite in the project's Docker harness once Safdar has Docker Desktop running (image `130-app-octane:phase2-rc25` with the worktree mounted, DB `130_crm_test_disposable` on port 3309, `HASH_DRIVER=bcrypt`). If Docker is unavailable, say so explicitly in HANDBACK.md and do NOT claim a Docker run.
2. Run the existing regression tests that touch the modified files and compare to `main`'s failures: `TravelAdvisoryApprovalWorkflowTest`, `CallFlowTest`, `CallReminderTest`, support-center tests. Paste the diff of failure lists (must be empty).
3. Keep Pint clean, `php -l`, `composer audit`.
4. Update `docs/evidence/push-notifications/test-run-YYYYMMDD.txt` with the real full output (not a hand-assembled summary), and add `docker compose config` output.

## Deliverables

- New commits on `feat/push-notifications-desk` fixing F1–F8.
- Updated `docs/NHMP_130_PUSH_NOTIFICATION_ARCHITECTURE.md` (links table, P1 rule, placeholder secrets step, worker health, flag-off behaviour, rollback) and `HANDBACK.md` with a section "Round 2: what changed / what is still not verified".
- A short table at the end of `HANDBACK.md`: finding ID → fix commit → test name → pass/fail.

## STOP and ask Safdar if
- you need production access, a real Firebase key, or to send a real push;
- you find you must change an existing table or add a non-additive migration;
- you need to restart anything or touch `routes/api.php` / patrol / Humsafar code.
