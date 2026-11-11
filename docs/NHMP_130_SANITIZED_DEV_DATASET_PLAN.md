# NHMP 130 — Sanitized dev dataset for sharing the authenticated APIs (plan, 2026-11-11)

Status: PLAN ONLY. Nothing here is built or deployed. Production is not involved.

## Goal
Let other developers use the **authenticated** Hamsafar APIs (commuter, patrol, women's journey, cases) without exposing real data.
Today only the public Travel Advisory endpoints are shared (`docs/DEV_API_GATEWAY.md`) because the local database is a copy of production
(735,610 calls, 260 real users, 7 women's journeys, 1.05M login logs with IPs/user agents, calls up to 2026-11-10).

## Findings that shape the plan
1. `scripts/sanitize_production_dump.php` (v2.0) is **out of date**: it handles 9 tables (calls, users, outgoing_calls, phonebook_contacts,
   audit_logs, login_logs, telephony_*, notifications) and has **no awareness of field encryption** (0 references to GCM/bidx/decrypt).
   In the current schema `users.cnic`, `users.mobile_no`, `calls.caller_number/name` are AES-GCM ciphertext, with blind-index columns;
   a SQL-stream regex cannot sanitize them. It also does not know the 22 newer tables.
2. Personal data also lives in tables the script never touches: `commuter_profiles.emergency_contacts` (plaintext JSON),
   `womens_journeys(+updates)` (names, GPS, PSV reg), `patrol_officer_profiles`/`patrol_duty_sessions` (device ids, GPS),
   `case_messages`, `support_requests*`, `password_reset_requests` (names, emails, IPs), `call_status_history.remarks` (1.19M rows free text),
   `calls.details/location_details/pending_remarks` (free text), `personal_access_tokens`, `password_histories`.
3. The script's synthetic accounts use `bcrypt('password')` — a publicly known credential. Do not reuse that.
4. Reference/infrastructure data is **not** personal and can be reused as-is: `geospatial_markers` (377,740), `geospatial_landmarks`,
   `carriageways`, `offices` (check office phone numbers are official), `designations`, `call_types`, `call_sub_types`, `vehicle_types`,
   `roles`, `permissions`, `languages`.

## Options
| | A. Synthetic dataset (recommended) | B. Sanitize a production copy |
|---|---|---|
| PII risk | None by construction | Residual risk in free text/JSON/GPS; needs a rewritten, encryption-aware sanitizer |
| Effort | Small–medium (seeders already exist: `HamsafarEcosystemSeeder`) | Large; 45+ tables, 760k calls, verification burden |
| Realism | Enough for API consumers (shapes, statuses, edge cases) | High, but not needed to consume the API |
| Recommendation | **Do this** | Only if realistic volume testing is required later |

## Recommended design (Option A)
**Isolation** (nothing shares state with the production-copy stack):
- New docker network `nhmp-dev-net`; containers `nhmp-dev-db` (single MySQL 8.4, no published port, own passwords),
  `nhmp-dev-redis` (own instance), `nhmp-dev-app` (image `130-app-octane:phase2-rc10` or newer, built from `main`).
- Fresh secrets for the dev app: new `APP_KEY`, `BIDX_SALT`, `AUDIT_SALT` (never the production values; Docker secret files outside git).
- `APP_ENV=staging`, `APP_DEBUG=false`.

**Data**:
- `php artisan migrate --force` on the empty dev DB, then seed: roles/permissions, copy the reference tables above from the local copy
  (tables only, no user-linked rows), `HamsafarEcosystemSeeder`, and ~5–20k synthetic calls from factories with fake Pakistani-format numbers
  from a reserved test range, fake names, and fake CNICs.
- Per-developer accounts with **unique random passwords** issued out of band; roles: commuter, patrol_officer, agent, agent_supervisor
  (desk viewer). No admin web UI exposed.

**Exposure** (extends the existing gateway, still LAN/Tailscale only):
- Add an upstream for `nhmp-dev-app`; allow `/api/v1/*` but still deny `/login`, `/admin`, `/ops` and the web UI.
- Stricter limits on auth endpoints (e.g. 5 req/min/IP on login/register), body size cap, request logging, optional shared API-key header.
- Short Sanctum token TTL (e.g. 8 h); a one-command kill switch and an expiry date.

**Verification gate before sharing**:
1. Role-matrix test — must reproduce commit 7f91bbe behaviour (commuter 403 on `/patrol/*` and the desk queue).
2. PII canary scan: hashed set of real user emails/mobiles/CNICs/caller numbers from the local copy (in memory, never written),
   scan a full dump of the dev DB; any match fails the build.
3. Postman folders 1–5 pass against the dev gateway with the synthetic credentials.

## Steps and rough effort
1. Dev-stack run files + secrets generation script (0.5 d)
2. Seed/factory work + reference-table copy script (0.5–1 d)
3. Gateway extension + limits + Postman env update (0.5 d)
4. Verification gate and docs (0.5 d)

## Decisions needed from the owner
1. Approve Option A (synthetic) vs B (sanitized copy).
2. Who needs accounts, and which roles (commuter / patrol / desk)?
3. Data volume (suggest 10k calls) and lifetime of the shared environment (suggest 30 days, then rebuild).
4. Keep sharing LAN + Tailscale only (same Windows firewall script), or also a VPN?

## Related: the production-copy database on this workstation
It holds real personal data and the app decrypts it locally, which implies the local `APP_KEY`/salts match production.
Recommend: confirm disk encryption (BitLocker) on this machine, keep the DB unreachable from other hosts (it already is),
and delete or rotate the copy once the synthetic environment replaces the need for it.
