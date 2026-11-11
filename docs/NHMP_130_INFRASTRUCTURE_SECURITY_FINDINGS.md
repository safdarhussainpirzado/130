# NHMP 130 Helpline — Infrastructure and Container Security Findings

**Document ID:** `NHMP-130-SEC-INFRA-2026-V1`  
**Classification:** Infrastructure Architecture & Security Hardening Report  
**Standard References:** NIST SP 800-190 (Application Container Security Guide), CIS Docker Benchmark v1.6.0, CIS MySQL 8.0 Benchmark v1.4.0  
**Target Environment:** Local Docker Compose Multi-Node Staging Stack  
**Audit Date:** November 05, 2026  

---

## 1. Container & Host Hardening Assessment

### 1.1 Non-Root Container Execution & Daemon Isolation
- **Execution User:** All application containers (`130-app-1-1`, `130-app-2-1`) execute strictly as non-root user `www-data` (UID 82, GID 82).
- **Docker Socket Isolation:** Verified `/var/run/docker.sock` is **NOT mounted** inside any application or web container. An application compromise cannot control the Docker daemon or escape to the host kernel.
- **Privileged Mode:** Verified `Privileged=false` across all 16 running services.
- **Development Toolchain Exclusion:** Verified production container images exclude build tools (`gcc`, `g++`, `make`), preventing in-container malware compilation.

### 1.2 Remediated Credential Exposure: `db_root_password`
- **Initial Finding:** `db_root_password` was mounted into `app-1` and `app-2`.
- **Remediation:** Removed `db_root_password` from `app-1` and `app-2` in [docker-compose.yml](file:///home/mrpirzado/projects/130/docker-compose.yml). Application containers now only have access to `db_password` for `crm_user`.

---

## 2. Network Topology & Micro-Segmentation Analysis

### 2.1 The Single Flat Bridge Finding (Finding SEC-NET-01 — Severity: Medium)
- **Current State:** All 16 containers operate within a single user-defined Docker bridge network: `crm-net`.
- **Observed Behavior:** Probing from `130-app-1-1` verified direct TCP reachability to:
  - Database primary (`db-1:3306`) and replicas (`db-2:3306`, `db-3:3306`)
  - Prometheus (`prometheus:9090`) and Grafana (`grafana:3000`)
  - HAProxy stats socket (`haproxy:8404`)
  - Exporters (`mysql-exporter:9104`, `node-exporter:9100`)
- **Recommended Production Segmentation:** For candidate datacenter deployment, bifurcate `crm-net` into three isolated bridge networks:
  1. `crm-ingress-net`: `haproxy` <-> `web-1`, `web-2`.
  2. `crm-backend-net`: `web-1`, `web-2` <-> `app-1`, `app-2` <-> `db-1`, `redis`, `minio`.
  3. `crm-metrics-net`: `prometheus` <-> `node-exporter`, `mysql-exporter`, `redis-exporter`, `grafana`.

---

## 3. Database Least-Privilege & Audit Triggers (MySQL 8.4)

### 3.1 Grant Analysis for `crm_user` (Finding SEC-DB-01 — Severity: High)
- **Observed Grant:** `GRANT ALL PRIVILEGES ON 130_crm_db.* TO 'crm_user'@'%'`.
- **Risk:** `ALL PRIVILEGES` includes `TRIGGER` and `DROP`. While MySQL triggers (`audit_logs_no_update`, `audit_logs_no_delete`) block DML modification of audit logs with SQLSTATE 45000, an attacker with SQL injection as `crm_user` could execute `DROP TRIGGER audit_logs_no_update;` to defeat the immutability control.
- **Production Hardening Standard:**
  ```sql
  -- Production DBA Migration Script
  REVOKE ALL PRIVILEGES ON `130_crm_db`.* FROM 'crm_user'@'%';
  GRANT SELECT, INSERT, UPDATE, DELETE ON `130_crm_db`.* TO 'crm_user'@'%';
  REVOKE UPDATE, DELETE, DROP, TRIGGER ON `130_crm_db`.`audit_logs` FROM 'crm_user'@'%';
  GRANT SELECT, INSERT ON `130_crm_db`.`audit_logs` TO 'crm_user'@'%';
  ```
  Triggers and schema DDL must be owned exclusively by the `root` administrative identity.

---

## 4. Redis In-Memory Store Hardening (Redis 8.6)

1. **Authentication:** Enforced at container startup via `--requirepass "$REDIS_PASSWORD"`.
2. **Network Restriction:** Port 6379 is bound strictly to loopback `127.0.0.1:6379` for local inspection; public binding is disabled.
3. **Command Security (Recommended):** In production `redis.conf`, rename or disable dangerous administrative commands:
   ```text
   rename-command FLUSHALL ""
   rename-command FLUSHDB  ""
   rename-command CONFIG   "NHMP_RESTRICTED_CONFIG"
   ```

---

## 5. Perimeter Ingress Hardening (HAProxy & Nginx)

1. **Information Disclosure Suppression:** Nginx nodes configure `server_tokens off;` to suppress version fingerprinting.
2. **Perimeter Headers:** HAProxy injects defense-in-depth security headers on all responses (including static assets and error pages):
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
   - `X-Frame-Options: SAMEORIGIN`
   - `X-Content-Type-Options: nosniff`
   - `Referrer-Policy: strict-origin-when-cross-origin`
3. **Content Security Policy (CSP):** Enforced via Laravel middleware, restricting scripts, styles, and iframe framing to authorized CDNs and origin.
4. **Active Runtime Architecture:** Confirmed that **PHP-FPM** is the active container runtime handling FastCGI traffic. RoadRunner is documented as an offline prototype script and is not active in the staging deployment.
