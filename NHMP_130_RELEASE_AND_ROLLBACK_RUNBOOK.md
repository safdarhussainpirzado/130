# NHMP 130 — PRODUCTION RELEASE & ROLLBACK RUNBOOK

## 1. RELEASE PRE-CONDITIONS & STAGING ACCEPTANCE GATES

No production deployment may proceed unless all 6 pre-conditions are satisfied in staging:
1. **Framework Boundary:** Verified `Laravel Framework 13.40.0` on PHP 8.3 CLI.
2. **Runtime Verification:** Verified RoadRunner PID 1 with zero PHP-FPM processes.
3. **Automated Quality Gates:** 100% pass on unit, integration, and security test suites (zero failures).
4. **Real HTTP Isolation Test:** 12 / 12 HTTP worker isolation tests passed on both application nodes.
5. **Database Migration Safety:** All pending database migrations tested for reversibility.
6. **Backup Integrity:** A verified encrypted backup snapshot completed within the past 60 minutes.

---

## 2. ZERO-DOWNTIME ROLLING DEPLOYMENT PROCEDURE

The deployment utilizes HAProxy layer-7 traffic shifting to guarantee zero dropped TCP connections or user session interruptions:

```
Step 1: Drain Node 1 in HAProxy ──► Deploy Node 1 ──► Reset RoadRunner ──► Verify /up ──► Re-enable Node 1
Step 2: Drain Node 2 in HAProxy ──► Deploy Node 2 ──► Reset RoadRunner ──► Verify /up ──► Re-enable Node 2
```

### Detailed Execution Steps:

#### Phase A: Application Node 1 Update
1. **Set Node 1 to Maintenance in HAProxy:**
   ```bash
   echo "set server app_nodes/web-1 state maint" | socat - /tmp/haproxy.sock
   ```
   *HAProxy automatically redispatches all active and incoming requests to Node 2 (`web-2`).*

2. **Deploy Updated Application Image / Code to Node 1 (`130-app-1-1`):**
   ```bash
   docker-compose pull app-1
   docker-compose up -d --no-deps app-1
   ```

3. **Run Pending Database Migrations (Once from Node 1):**
   ```bash
   docker exec 130-app-1-1 php artisan migrate --force
   ```

4. **Reset RoadRunner Workers on Node 1:**
   ```bash
   docker exec 130-app-1-1 rr reset
   ```

5. **Verify Local Health Probe on Node 1:**
   ```bash
   curl -i http://127.0.0.1:8000/up
   # Expect: HTTP/1.1 204 No Content
   ```

6. **Re-Enable Node 1 in HAProxy:**
   ```bash
   echo "set server app_nodes/web-1 state ready" | socat - /tmp/haproxy.sock
   ```

---

#### Phase B: Application Node 2 Update
7. **Set Node 2 to Maintenance in HAProxy:**
   ```bash
   echo "set server app_nodes/web-2 state maint" | socat - /tmp/haproxy.sock
   ```

8. **Deploy Updated Application Image to Node 2 (`130-app-2-1`):**
   ```bash
   docker-compose pull app-2
   docker-compose up -d --no-deps app-2
   ```

9. **Reset RoadRunner Workers on Node 2:**
   ```bash
   docker exec 130-app-2-1 rr reset
   ```

10. **Verify Local Health Probe on Node 2:**
    ```bash
    curl -i http://127.0.0.1:8000/up
    # Expect: HTTP/1.1 204 No Content
    ```

11. **Re-Enable Node 2 in HAProxy:**
    ```bash
    echo "set server app_nodes/web-2 state ready" | socat - /tmp/haproxy.sock
    ```

12. **Post-Deployment Verification:**
    Verify both nodes report `UP,L7OK` in HAProxy:
    ```bash
    echo "show stat" | socat - /tmp/haproxy.sock | grep "app_nodes"
    ```

---

## 3. EMERGENCY ROLLBACK PROCEDURE

If unexpected application errors, elevated latencies, or security regressions occur post-release:

### MANDATORY ROLLBACK DIRECTIVE:
- **THE ROLLBACK TARGET MUST RESTORE A KNOWN-GOOD LARAVEL 13 + OCTANE + ROADRUNNER RELEASE.**
- **ROLLING BACK TO PHP-FPM IS STRICTLY PROHIBITED.**
- Under no circumstances may PHP-FPM, FastCGI passes, or port 9000 services be reintroduced.

### Rollback Execution Steps:
1. **Identify Previous Known-Good Docker Image Tag:**
   (e.g., `local-registry:5000/130-app:v13.40.0-stable`).

2. **Drain Node 1 in HAProxy:**
   ```bash
   echo "set server app_nodes/web-1 state maint" | socat - /tmp/haproxy.sock
   ```

3. **Revert Application Image on Node 1:**
   ```bash
   docker service update --image local-registry:5000/130-app:v13.40.0-stable 130-app-1-1
   docker exec 130-app-1-1 rr reset
   ```

4. **Rollback Reversible Database Migrations (if applicable):**
   ```bash
   docker exec 130-app-1-1 php artisan migrate:rollback --step=1 --force
   ```

5. **Verify Health and Re-Enable Node 1:**
   ```bash
   curl -i http://127.0.0.1:8000/up
   echo "set server app_nodes/web-1 state ready" | socat - /tmp/haproxy.sock
   ```

6. **Repeat Procedure for Node 2 (`130-app-2-1`).**

7. **Verify Cluster Recovery:**
   Run automated real HTTP isolation suite (`run_real_http_roadrunner_isolation_test.py`) to confirm clean operational baseline.
