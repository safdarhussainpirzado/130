# NHMP 130 — ROADRUNNER-ONLY RUNTIME ARCHITECTURE SPECIFICATION

## 1. ARCHITECTURAL MANDATE & TOPOLOGY

By executive directive, the NHMP 130 Helpline CRM has been permanently migrated to a **RoadRunner-only application runtime**.

### Approved End-to-End Application Topology

```
┌────────────────────────────────────────────────────────────────────────┐
│                   EXTERNAL / OPERATOR TRAFFIC                          │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Ports 80, 443, 7085, 8085 (TLS 1.3)
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                HIGH AVAILABILITY LOAD BALANCER (HAProxy)               │
│                • Layer 7 reverse proxy & HTTP/2 termination            │
│                • Dynamic health checks (/up URI, expects 200/204)      │
│                • Session stickiness via SERVERID cookie (web1/web2)    │
│                • option redispatch on maintenance/failure              │
└───────────────────┬────────────────────────────────┬───────────────────┘
                    │                                │
                    ▼                                ▼
       ┌────────────────────────┐       ┌────────────────────────┐
       │   Nginx Reverse Proxy  │       │   Nginx Reverse Proxy  │
       │       (Node 1: web-1)  │       │       (Node 2: web-2)  │
       │   • Static asset cache │       │   • Static asset cache │
       │   • Gzip compression   │       │   • Gzip compression   │
       │   • Pass-through proxy │       │   • Pass-through proxy │
       └────────────┬───────────┘       └────────────┬───────────┘
                    │ HTTP/1.1 (port 8000)           │ HTTP/1.1 (port 8000)
                    ▼                                ▼
       ┌────────────────────────┐       ┌────────────────────────┐
       │   RoadRunner Runtime   │       │   RoadRunner Runtime   │
       │       (Node 1: app-1)  │       │       (Node 2: app-2)  │
       │   • Go HTTP engine     │       │   • Go HTTP engine     │
       │   • 4 Octane Workers   │       │   • 4 Octane Workers   │
       │   • Port 8000 listener │       │   • Port 8000 listener │
       └────────────┬───────────┘       └────────────┬───────────┘
                    │                                │
                    └───────────────┬────────────────┘
                                    │
                                    ▼
       ┌─────────────────────────────────────────────────────────┐
       │                DATA & STORAGE TIER                      │
       │  • MySQL 8.4 Cluster (db-1 primary, db-2/3 read-replicas)│
       │  • Redis 8.6 Cluster (Cache, Session, Stampede Locks)   │
       │  • MinIO Object Storage (Encrypted Backups & Media)     │
       └─────────────────────────────────────────────────────────┘
```

---

## 2. COMPLETE & PERMANENT PHP-FPM ELIMINATION

PHP-FPM is strictly prohibited from serving application traffic. All legacy PHP-FPM pathways, configurations, and fallbacks have been completely excised.

### 2.1 Summary of Excised PHP-FPM Components

| Component | Previous Legacy State | Modernized RoadRunner-Only State | Verification Evidence | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx FastCGI Passes** | `fastcgi_pass $fpm_backend:9000;` | Completely removed; replaced by `proxy_pass http://$octane_backend;` | Inspected `nginx-1.conf`, `nginx-2.conf`, `nginx.conf` | **Confirmed Removed** |
| **Nginx FPM Fallback** | `error_page 502 = @fpm_fallback;` | Completely removed; errors return native 502/504 to HAProxy | Zero `@fpm_fallback` blocks in any Nginx config | **Confirmed Removed** |
| **Nginx PHP Suffix Match**| `location ~ \.php$` passing to FastCGI | Completely removed; all dynamic paths route exclusively to `@octane` | Verified regex match excised | **Confirmed Removed** |
| **Container Port 9000** | Exposed in Dockerfile for FastCGI | Removed from `Dockerfile` (only port 8000 exposed) | Docker build & container socket inspection | **Confirmed Removed** |
| **PHP-FPM Process** | Ran `php-fpm` via Supervisor | Entrypoint executes `rr serve -c .rr.yaml` | `ps aux` confirms PID 1 is `rr` on both nodes | **Confirmed Removed** |
| **FPM Fallback Upstreams**| Defined `app-1:9000` / `app-2:9000` | Removed all port 9000 upstream references | No port 9000 upstreams exist | **Confirmed Removed** |

### 2.2 Proof of Removal

1. **Process Tree Verification (`130-app-1-1` and `130-app-2-1`):**
   ```
   PID   USER     TIME  COMMAND
       1 www-data  0:01 rr serve -c .rr.yaml
      66 www-data  0:01 php ./app/Octane/roadrunner_worker.php
      67 www-data  0:01 php ./app/Octane/roadrunner_worker.php
      68 www-data  0:01 php ./app/Octane/roadrunner_worker.php
      69 www-data  0:01 php ./app/Octane/roadrunner_worker.php
   ```
   Zero instances of `php-fpm` exist.

2. **Network Listener Verification:**
   - Port 8000: Open and listening for incoming HTTP requests from Nginx.
   - Port 9000: Closed and unmapped to any host or internal application route.

3. **Node Failure Behavior:**
   - If RoadRunner crashes on an application node, Nginx immediately returns `HTTP 502 Bad Gateway`.
   - HAProxy immediately detects node failure via `http-check` and redirects all new and existing sessions to the remaining healthy node.
   - Under no circumstances does any silent fallback to PHP-FPM occur.

---

## 3. ROADRUNNER PRODUCTION CONFIGURATION (`.rr.yaml`)

```yaml
version: "3"

rpc:
  listen: tcp://127.0.0.1:6001

server:
  command: "php ./app/Octane/roadrunner_worker.php"
  relay: pipes

http:
  address: "0.0.0.0:8000"
  max_request_size: 64
  middleware: ["gzip"]
  pool:
    num_workers: 4
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_tick: 1s
      ttl: 3600s
      idle_ttl: 600s
      max_worker_memory: 128

logs:
  mode: production
  level: warn
  encoding: json
```

### Key Operational Controls:
- `num_workers: 4`: 4 concurrent workers per node (8 total workers in cluster), perfectly sized for multi-core container allocation without CPU thrashing.
- `max_worker_memory: 128MB`: Automatic recycling of any worker that approaches 128MB to prevent unconstrained memory leaks.
- `max_jobs: 1000`: Worker refreshed after serving 1,000 requests to guarantee long-term runtime cleanliness.
- `relay: pipes`: High-performance binary IPC communication between the RoadRunner Go master process and PHP CLI workers.

---

## 4. ROLLBACK SAFETY MANDATE

If an emergency rollback is required during release operations:
- The rollback target **MUST RESTORE A KNOWN-GOOD LARAVEL / OCTANE / ROADRUNNER RELEASE**.
- Rolling back to PHP-FPM is **STRICTLY PROHIBITED**.
- Any release failure must be triaged within the RoadRunner architecture.
