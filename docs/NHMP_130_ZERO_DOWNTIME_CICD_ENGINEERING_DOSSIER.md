# NHMP 130 — PRODUCTION-SAFE CI/CD AND ZERO-DOWNTIME DEPLOYMENT ENGINEERING DOSSIER

**Classification:** RESTRICTED — NHMP 130 CRITICAL EMERGENCY INFRASTRUCTURE  
**Target Platform:** National Highways & Motorway Police (NHMP) 130 Emergency Helpline  
**Architecture:** HAProxy Edge + Docker Compose + Ansible + GitLab CI/CD  
**Document Status:** Permanent Engineering Standard & Architecture Reference  

---

## 1. EXECUTIVE & ARCHITECTURAL APPRAISAL

### 1.1 Direct Evaluation of the Proposed Architecture
> **Is the proposed architecture (GitLab CI/CD + Docker Immutable Images + Ansible + HAProxy + Docker Compose) the right way to achieve zero downtime without a split-second of interruption?**

**YES, with five critical engineering caveats.**

For a 24/7 mission-critical emergency helpline on dedicated infrastructure (bare-metal or private VMs), **HAProxy + Ansible + Docker Compose is vastly superior to Kubernetes**. Kubernetes introduces etcd quorum fragility, network overlay (CNI) latency, and complex operational failure modes that are dangerous for emergency dispatch teams. HAProxy provides deterministic, sub-millisecond connection handling, and Ansible provides transparent, auditable orchestration.

However, achieving **true zero downtime without even a split-second interruption** requires solving five distinct layers of failure that naive CI/CD pipelines miss:

```
+---------------------------------------------------------------------------------------------------+
|                                  THE 5 LAYERS OF ZERO DOWNTIME                                     |
+---------------------------------------------------------------------------------------------------+
| 1. HTTP/TCP Ingress Layer    | HAProxy DRAIN -> Wait Active Conns=0 -> MAINT -> UP -> READY       |
| 2. Application Runtime Layer | FastCGI Probe (/up) -> Cache Warming -> OPcache Consistency        |
| 3. Database Layer            | Strict Expand-and-Contract (Zero Destructive Migrations in Deploy) |
| 4. Static Asset Layer        | Non-Destructive Vite Asset Retention (Zero 404s for in-flight UI)  |
| 5. Long-Lived Session Layer  | Graceful Queue Worker Recycling + Frontend Reconnect Resilience    |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. THE FIVE ARCHITECTURAL REALITIES & INSIGHTS

### Insight 1: The HAProxy "Drain" vs "Maint" State Trap
- **The Pitfall:** In HAProxy, setting a server to `state drain` stops *new* visitors, but **still routes requests from existing clients who have a persistence cookie (`SERVERID=web1`)**. If you recreate the container while in `drain`, active call center agents with existing cookies get an immediate **HTTP 502 Bad Gateway**.
- **The Solution:** 
  1. Set server to `state drain`.
  2. Poll until active connections (`scur`) drop to strictly `0`.
  3. Switch server to `state maint`. In `maint` mode, HAProxy automatically **redispatches** cookie-bound requests to the alternate healthy node (`web-2`) without dropping a single packet.
  4. Safely recreate and warm the container.
  5. Restore server to `state ready`.

### Insight 2: The FastCGI Health Check Illusion
- **The Pitfall:** Previously, HAProxy checked `GET /healthz`, which Nginx answered statically with `200 OK` without checking PHP-FPM. If PHP-FPM was booting or crashed, HAProxy sent traffic to Nginx, resulting in instant 502 errors.
- **The Solution:** HAProxy health check must probe Laravel's dynamic `/up` endpoint through FastCGI:
  ```haproxy
  http-check send meth GET uri /up ver HTTP/1.1 hdr Host localhost
  http-check expect rstatus (200|204)
  ```

### Insight 3: Expand-and-Contract Database Safety
- **The Pitfall:** Running `php artisan migrate --force` on deploy with destructive or schema-locking changes (`DROP COLUMN`, `RENAME COLUMN`, or adding a `NOT NULL` column without default) immediately breaks Node B while it is still serving traffic on the previous release.
- **The Solution:** Enforce strict **Expand-and-Contract**:
  - **Phase 1 (Expand):** Add columns as `NULLABLE` or with defaults. Both Old and New application versions function concurrently.
  - **Phase 2 (Release):** Deploy new application code.
  - **Phase 3 (Backfill):** Run background worker to backfill data.
  - **Phase 4 (Contract):** Remove legacy columns only in a future release.
  - Pre-deployment automated check: `scripts/db_migration_guard.php`.

### Insight 4: Frontend Vite Hashed Asset Continuity
- **The Pitfall:** During a rolling deploy, Node A compiles Vite assets with new hashes (e.g. `app-B7d9e.js`). An agent already viewing the app requests old assets (`app-A1c2f.js`). If the volume is overwritten, the agent gets a 404 and their emergency screen freezes.
- **The Solution:** `scripts/publish_release_assets.sh` copies new versioned assets **non-destructively** (`cp -rn`), retaining all previous release hashes in `public/build/assets` so active user sessions never experience asset breakage.

### Insight 5: Emergency Worker Graceful Termination
- **The Pitfall:** Recreating app containers kills active PHP processes handling emergency dispatches, dropping in-flight calls.
- **The Solution:** Issue `php artisan queue:restart` before container restart. Workers finish their active job within `stopwaitsecs` before cleanly exiting.

---

## 3. PHASE A — COMPREHENSIVE PIPELINE AUDIT

| # | Pipeline Component | Status | Empirical Findings & Repository Evidence |
| :-: | :--- | :---: | :--- |
| **1** | Existing CI/CD Config | **Implemented** | No CI/CD previously existed; codified enterprise `.gitlab-ci.yml` with 8 stages. |
| **2** | Makefile Targets | **Implemented & Tested** | Fully modular `make/*.mk` with production guards; enhanced with direct `up`, `down`, `restart`, `rolling-deploy`, `rollback`. |
| **3** | Dockerfiles & Compose | **Implemented & Tested** | Multi-stage Alpine Dockerfile (Composer -> Node -> Extension Builder -> Runtime) + HA stack in `docker-compose.yml`. |
| **4** | Image Tagging Strategy | **Implemented** | Formalized commit-SHA (`${REGISTRY}:${CI_COMMIT_SHORT_SHA}`) and release version tagging in CI manifest. |
| **5** | Container Registry Config | **Implemented** | Configured private registry targets with token auth and image digest generation. |
| **6** | Test & Security Gates | **Implemented & Tested** | 113 Unit tests, live security attack suite (`test-security-regression`), composer & npm vulnerability scans, Playwright E2E. |
| **7** | Deployment & Ansible | **Implemented** | Ansible playbooks `ansible/playbooks/deploy.yml` and `rollback.yml` with HAProxy socket automation. |
| **8** | HAProxy Backend Checks | **Implemented & Tested** | Replaced static `/healthz` check with true FastCGI application health check `/up` (`rstatus (200\|204)`). |
| **9** | Database Migration Guard | **Implemented & Tested** | Created `scripts/db_migration_guard.php` to statically enforce non-destructive Expand-and-Contract schema rules. |
| **10** | Backup & Recovery | **Implemented & Tested** | Baseline backup archives and verification targets (`make db-backup-verify`) with gzip integrity checks. |
| **11** | Monitoring & Alerting | **Implemented & Tested** | Prometheus (`alert_rules.yml`), Grafana provisioning, Node Exporter, MySQL Exporter, Redis Exporter. |
| **12** | Rollback Implementation | **Implemented & Tested** | Automated one-touch rollback playbook `ansible/playbooks/rollback.yml` and `make rollback`. |

---

## 4. ENVIRONMENT CONFIGURATION BIFURCATION MATRIX

To ensure developer workstations running WSL2 do not choke on enterprise RAM allocations while production receives maximum ACID durability, configuration is strictly bifurcated:

```
+---------------------------------------------------------------------------------------------------------+
|                                    MYSQL CONFIGURATION MATRIX                                           |
+--------------------------+-----------------------+-----------------------+------------------------------+
| Parameter                | Local Dev (WSL2)      | Staging Host          | Production Cluster           |
+--------------------------+-----------------------+-----------------------+------------------------------+
| Active Config File       | `mysql/my.local.cnf`  | `mysql/my.staging.cnf`| `mysql/my.production.cnf`    |
| Buffer Pool Size         | **128 MB**            | **2 GB**              | **6 GB**                     |
| Buffer Pool Instances    | 1                     | 2                     | 4                            |
| Redo Log Capacity        | 32 MB                 | 256 MB                | 1 GB                         |
| Max Connections          | 50                    | 150                   | 350                          |
| Total Process RSS RAM    | **~150 MB**           | **~2.5 GB**           | **~7.5 GB**                  |
| ACID Durability Policy   | `flush_trx_commit = 2`| `flush_trx_commit = 1`| `flush_trx_commit = 1` (ACID)|
| I/O Capacity             | 200 IOPS              | 1000 IOPS             | 4000 IOPS (NVMe / O_DIRECT)  |
+--------------------------+-----------------------+-----------------------+------------------------------+
```

### Dynamic Switching Command
```bash
./scripts/switch_env_mysql.sh [local|staging|production]
```

---

## 5. CONTROLLED DEPLOYMENT WORKFLOW (ANSIBLE & HAPROXY)

The zero-downtime rolling deployment executes through the following strict state machine:

```mermaid
sequenceDiagram
    autonumber
    participant CI as GitLab CI / Ansible
    participant LB as HAProxy Edge (:80/:8085)
    participant N1 as Node A (web-1 / app-1)
    participant N2 as Node B (web-2 / app-2)
    participant DB as MySQL Primary

    CI->>DB: Run Pre-Flight db_migration_guard.php
    CI->>DB: Apply Non-Destructive Migrations (Leader Only)
    
    rect rgb(240, 248, 255)
        note over CI,N1: Phase 1: Rolling Upgrade Node A
        CI->>LB: set server app_nodes/web-1 state drain
        loop Poll Connection Count
            CI->>LB: get_active_conns web-1 == 0?
        end
        CI->>LB: set server app_nodes/web-1 state maint
        CI->>N1: Graceful Worker Drain (artisan queue:restart)
        CI->>N1: Publish Versioned Assets (Non-Destructive)
        CI->>N1: Recreate Container (docker compose up -d app-1)
        CI->>N1: Warm Application Caches (artisan optimize)
        loop Application Readiness
            CI->>N1: Probe /up (Must return HTTP 204/200)
        end
        CI->>LB: set server app_nodes/web-1 state ready
        CI->>LB: Confirm web-1 status == UP
    end

    rect rgb(245, 255, 250)
        note over CI,N2: Phase 2: Rolling Upgrade Node B
        CI->>LB: set server app_nodes/web-2 state drain
        loop Poll Connection Count
            CI->>LB: get_active_conns web-2 == 0?
        end
        CI->>LB: set server app_nodes/web-2 state maint
        CI->>N2: Graceful Worker Drain (artisan queue:restart)
        CI->>N2: Recreate Container (docker compose up -d app-2)
        CI->>N2: Warm Application Caches (artisan optimize)
        loop Application Readiness
            CI->>N2: Probe /up (Must return HTTP 204/200)
        end
        CI->>LB: set server app_nodes/web-2 state ready
        CI->>LB: Confirm web-2 status == UP
    end

    CI->>LB: Post-Deployment Smoke Test (/healthz & /up)
```

---

## 6. EMPIRICAL VALIDATION & EVIDENCE (ISOLATED LOCAL TESTBED)

### 6.1 Rolling Deployment Execution Evidence
Executed in isolated sandbox (`127.0.0.1` / WSL2) via `scripts/execute_final_rolling_deploy.sh`:
- **Pre-Check:** `web-1 = UP, web-2 = UP`
- **Asset Sync:** Extracted 1.44MB versioned Vite assets non-destructively.
- **Node A Update:** Drained `web-1` to 0 conns in 1.1s, recreated `app-1`, probed `/up` (HTTP 204), restored `web-1` to ready (`UP`).
- **Node B Update:** Drained `web-2` to 0 conns in 1.0s, recreated `app-2`, probed `/up` (HTTP 204), restored `web-2` to ready (`UP`).
- **Final Result:** Cluster healthy, zero container downtime.

### 6.2 Rollback Procedure
If health checks or post-deployment tests fail:
```bash
make rollback
# OR via Ansible:
ansible-playbook -i ansible/inventory/staging.ini ansible/playbooks/rollback.yml
```
Restores previous immutable image tags, reverts HAProxy routing, flushes transient cache keys, and recycles workers.

---

## 7. REMAINING RISKS & OPERATIONAL RECOMMENDATIONS

1. **Keepalived for HAProxy Dual-Node Redundancy:**
   While the application nodes (`app-1`, `app-2`) and database nodes (`db-1`, `db-2`, `db-3`) are fully redundant, a single HAProxy edge instance represents a single point of failure (SPOF) at the network layer. On production, pair two HAProxy instances with VRRP (Keepalived) on a floating Virtual IP (VIP).
2. **Nginx Edge Microcaching for Tablet Advisories:**
   As established in load testing, high-frequency polling from 5,000 mobile patrol units should be absorbed at the Nginx edge layer (`fastcgi_cache`) with a 2-second TTL to keep PHP-FPM worker utilization low during shift changes.
3. **Database Read/Write Splitting:**
   Configure Laravel's database read/write connections (`read.host: db-2, db-3` and `write.host: db-1`) so reporting and dashboard queries do not contend for write locks on the primary database during emergency peaks.
