# HANDOFF — NHMP 130 (written 2026-11-11 for Gemini / next engineer)

Read this first. It is the authoritative state after the Claude session. Repo: `/home/mrpirzado/projects/130`, branch **`main`** (checked out, clean).
Prod: `root@10.2.44.53`, compose project `130`, dir `/home/mrpirzado/projects/130` on the server.

## HARD RULES
- No test data, tests, load tests or DB resets on production. Tests run only on the dev machine against the disposable DB (`130_crm_test_disposable`, port 3309). Never inside a prod container.
- Zero data loss, zero planned downtime. Roll app nodes ONE AT A TIME through HAProxy drain (see "How to deploy"). No bare `docker compose up -d` on prod (it recreates base services); always `up -d --no-deps --no-build <service>`.
- Never push secrets. `docker/secrets/`, `*.pem`, `*.key`, `.env*` are gitignored. Never copy `docker/secrets/*` to prod (prod has its own).
- Do NOT merge `feat/staging-remediation-baseline` again or overwrite prod-critical files from it (it was merged into `main` with prod versions kept; backup tag `pre-geminimerge-20261111`, branch `backup/feat-staging-remediation-baseline-20261111`).
- Ask the owner before anything outward-facing (push, deploy, force-push, rotating certs, restarting DB/Redis).

## Current production state (verified 2026-11-11)
- Runtime: Laravel 13.40 on **Octane/RoadRunner**, image `130-app-octane:phase2-rc10` on `app-octane-1/2` (+ nginx `web-octane-1/2`) behind HAProxy; `scheduler` container (`php artisan schedule:work`, exactly one). Old php-fpm nodes `app-1/2`, `web-1/2` are STOPPED (not removed; `docker start` to bring back).
- DB: MySQL 8.4 Group Replication (db-1 primary, db-2/3 secondary, all ONLINE). Buffer pool 3 GB (online), thread_cache 64, durability intact (`flush_log_at_trx_commit=1`, `sync_binlog=1`). Persisted in `mysql/my.cnf`.
- Redis: single node, `maxmemory 1gb`, `noeviction` (runtime only; lost on container recreate — put in compose command if persisting). AOF on.
- Docker memory ceilings set live (`docker update`), previous values: `/root/deploy_backups/limits_before_20261111.txt`. Restart policy `unless-stopped` on all containers (also in `docker-compose.octane.yml` overlay).
- Secrets: Docker secrets (`/run/secrets/*`, `*_FILE` env loaded by `entrypoint.sh`); field-level AES-GCM encryption with blind indexes. `.env` on prod is mode 600.
- Backups: cron 01:30 `/root/nightly_backup.sh` -> `/root/nightly_backups` (keeps 14), encrypted with `/root/.nhmp_backup_key` (copies on dev machine under `/home/mrpirzado/nhmp-escrow/` and `/home/mrpirzado/.config/nhmp-escrow-copy/`).
- HAProxy runtime socket: `docker exec -i 130-haproxy-1 socat stdio /tmp/haproxy.sock`. Servers: `app_nodes/web-octane-1|2`.

## What is in `main` (not yet pushed)
`origin/main` is still `9d8305d`. This machine has **no GitHub credentials**: the owner must run `git -C /home/mrpirzado/projects/130 push origin main` from a logged-in terminal.
Key commits: Octane overlay + rc10 pin, per-user dropdown cache fix, shared agent caller history, MySQL tuning, untracked leaked keys/dump, Postman collections, dev gateway, `hamsafar.access` middleware (7f91bbe), docs.

## URGENT — not deployed (owner said "Not yet")
**Security hole on prod:** any self-registered commuter token can `GET /api/v1/womens-journey/desk-queue` (passenger name, phone, CNIC, PSV, emergency contacts) and call `/api/v1/patrol/*` and `/api/v1/cases/{uuid}` action routes.
Fix is committed as **`7f91bbe`** (`app/Http/Middleware/EnsureHamsafarAccess.php`, `routes/api.php`, `bootstrap/app.php` alias `hamsafar.access`). Verified locally with a role matrix and the Postman E2E collection.
To deploy (after owner approval): hot-patch those 3 files into both `app-octane-*` containers (`docker cp`), `php artisan route:cache`, `rr reset -c /var/www/html/.rr.prod.yaml`; then build image `phase2-rc11` and roll (below). Afterwards grep prod web logs for `desk-queue` hits by non-desk users (possible incident).
Optional stopgap with no code deploy: block `/api/v1/womens-journey/desk-queue` and `/api/v1/patrol/*` at HAProxy for external callers.

## How to deploy (proven twice)
1. Build locally: `docker build -f Dockerfile.octane -t 130-app-octane:phase2-rcN .` (Docker Desktop must be running with WSL integration). Verify contents with `docker run --rm --entrypoint sh <img> -c 'grep ...'`.
2. Ship: `docker save <img> | gzip -1 > /tmp/rcN.tar.gz`; `rsync --partial --inplace` to `root@10.2.44.53:/root/`; `gunzip -c | docker load` on prod (the link to prod is flaky; use rsync retries, never build on the server).
3. Update tag in `docker-compose.octane.yml` (all `phase2-rcN` occurrences), copy to prod as `/root/octane.rcN.yml`, `docker compose -p 130 -f docker-compose.yml -f /root/octane.rcN.yml config -q`.
4. Roll with `/root/roll.sh` (drains `web-octane-N`, recreates `app-octane-N`, waits healthy, re-enables, runs a live probe). Point it at the new overlay first (`sed -i` the overlay path inside it). Then install the overlay as `docker-compose.octane.yml` on prod and recreate `scheduler` with `--no-deps`.
5. Hot-patching a file: `docker cp` into both app containers + `php artisan view:cache`/`route:cache` as relevant + `docker exec <c> rr reset -c /var/www/html/.rr.prod.yaml` (opcache has `validate_timestamps=0`).
Rollback: previous overlay copies are in `/root/deploy_backups/docker-compose.octane.yml.pre-*`; older images `phase2-rc1..rc10` remain on the server.

## Open tasks (priority order)
1. **Deploy 7f91bbe** (above) — owner approval needed. Re-raise first.
2. Push `main` (owner, from a logged-in terminal).
3. **Git secret exposure:** `origin/main` history contains private keys for self-signed certs `haproxy/certs/haproxy.pem` (CN cpo.nhmp.gov.pk) and `nationaldatacenter.pem` (CN nationaldatacenter.gov.pk) and `sedVmV4qw` (activity_log seed data). Plan: `docs/NHMP_130_GIT_SECRET_EXPOSURE_REMEDIATION_PLAN.md` (verify on server whether HAProxy/anything serves those keys by comparing public-key fingerprints; rotate if used; then `git filter-repo` + force-push with owner approval).
4. `mysql-exporter` is down since August (no user configured): create a read-only monitoring user (PROCESS, REPLICATION CLIENT) + Docker secret. DB change, needs approval.
5. Redis password (none today) and MinIO password rotation: each needs a restart window and drops sessions/file access briefly.
6. `db-1` controlled switchover/rejoin test: planned quiet window, fresh backup first, warn agents.
7. Run the 13 legacy failing tests (`PRODUCTION_RELEASE_BLOCKERS.md`) on the dev machine only.
8. Slow-query/index review: `/var/lib/mysql/mysql-slow.log` in `db-1` (~117 slow queries/day); add indexes only with proof.
9. Public Travel Advisory API throttle is `throttle:60000,1` (effectively none) — lower to ~120-300/min per IP.
10. Optional: off-server copy of nightly backups; Redis Sentinel (needs auth-pass via Docker secret, planned window); queue workers only when the app starts queuing jobs (`docker/supervisor/worker-isolated.conf` is ready; nothing is queued today).
11. Sanitized dev dataset for sharing authenticated APIs: `docs/NHMP_130_SANITIZED_DEV_DATASET_PLAN.md` (Option A synthetic recommended; awaiting owner decisions).

## Dev workstation facts
- Local Docker Desktop stack (`130-*` containers on `https://localhost`) holds a **production copy** (735k calls, 260 real users). Do not expose it. `APP_ENV=production`, local DB `127.0.0.1:3309`.
- Shared dev gateway `nhmp-dev-api-gw` on :8480 exposes ONLY the public Travel Advisory GET endpoints (LAN `http://192.168.137.196:8480`, Tailscale `http://100.93.33.77:8480`). Docs: `docs/DEV_API_GATEWAY.md`. Windows port-forward/firewall via `scripts/windows/expose-dev-api-gateway.ps1` (run as Administrator; re-run after WSL restarts; `-Remove` to undo). A pre-existing Windows portproxy 0.0.0.0:8000 -> WSL:8000 also exists (not ours; worth checking).
- `docker cp` into local containers fails (Docker Desktop bind-mount issue); use `docker exec -i <c> php < file.php` instead.
- Postman CLI works without login for local runs: `cd postman/collections/nhmp-hamsafar-ecosystem-apis && postman collection run "5. Public Travel Advisory Portal" -e ../../environments/nhmp-hamsafar-dev.environment.yaml --insecure`.
  Collections: `postman/collections/nhmp-hamsafar-ecosystem-apis` (folders 1-5; 1-3 need valid credentials; sample ones in the collection don't match local users) and the self-contained `postman/collections/nhmp-hamsafar-e2e-local` (registers a fresh throwaway commuter each run; 16 requests / 25 assertions pass; includes the commuter-must-get-403 regression). Both are local/dev only, never against production.
- Don't `git branch -f main`; `main` is checked out in the primary checkout. The worktree branch `phase2/merged` is behind `main`.

## Memory/notes for the next Claude session
`~/.claude/projects/-home-mrpirzado-projects-130/memory/pending-prod-tasks-2026-11-11.md` mirrors the open tasks above.
