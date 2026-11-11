# NHMP 130 — Git secret exposure: remediation plan (2026-11-11)

## What was found (verified from git history)
Tracked in `origin/main` (GitHub `jetbrains88/130`), added 2026-05-03 .. 2026-05-18 (commits 7868d87, d078e11, f433b30):

| File | What it is | Notes |
|---|---|---|
| `haproxy/certs/haproxy.pem` | Self-signed cert + **private key**, CN `cpo.nhmp.gov.pk`, expires 2027-05-07 | issuer = subject (self-signed) |
| `haproxy/certs/nationaldatacenter.pem` | Self-signed cert + **private key**, CN `nationaldatacenter.gov.pk`, expires 2036-05-10 | issuer = subject (self-signed) |
| `sedVmV4qw` | 5.2 MB SQL file | Only `INSERT IGNORE INTO activity_log` rows (designation seed data). Low sensitivity; confirm no PII before closing. |

Not leaked (verified): the production certificate `130.nhmp.gov.pk` (`.key`/`.crt`) was never tracked.
Already done on local `main` (0ccafc3): files untracked and ignored. History is unchanged, so they remain retrievable from GitHub until purged.

## Risk assessment
- Keys exposed are for **other hostnames** and self-signed. Impact depends on whether prod HAProxy (or anything else) actually serves them. **To verify on the server** (needs SSH): compare `openssl pkey -pubout | sha256` of each leaked key against the certificate HAProxy serves on :443 and any other TLS endpoint (Grafana, MinIO, internal).
- If neither is used anywhere: retire them, no production impact; purge history for hygiene.
- If in use: rotate (below) before purging.

## Plan (in order; each step has a rollback)
1. **Verify usage** (read-only): fingerprints above vs. served certs; check repo visibility on GitHub (public => assume compromised).
2. **Rotate only if in use**: generate a new key + CSR, issue the certificate, place the new combined PEM beside the old one, validate with `haproxy -c`, then graceful reload (USR2). Keep the old PEM until verified. Rollback = point back to the old PEM and reload. Zero downtime. Revoke the old cert if it was CA-issued.
3. **Purge history** (needs repo-owner rights, announce first):
   `git filter-repo --invert-paths --path haproxy/certs/haproxy.pem --path haproxy/certs/nationaldatacenter.pem --path sedVmV4qw` on a fresh mirror clone, back up the mirror first, force-push all branches and tags. All collaborators (and Gemini's `feat/staging-remediation-baseline`) must re-clone/rebase; open PRs are invalidated. Ask GitHub Support to purge cached views and check forks. Purging does not un-leak: rotation is the real mitigation.
4. **Scan the whole history** for other secrets (gitleaks/trufflehog) and rotate anything found.
5. **Prevent recurrence**: pre-commit + CI secret scan, keep `.gitignore` rules (`*.pem`, `/haproxy/certs/*.pem`, `/docker/secrets/`), keep real certs only on the server.

## Do not do
- Do not force-push before step 1–2 are complete and the owner approves.
- Do not merge `feat/staging-remediation-baseline` wholesale into `main` (it predates the Octane/overlay production config).
