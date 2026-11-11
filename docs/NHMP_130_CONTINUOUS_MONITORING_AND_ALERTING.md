# NHMP 130 — CONTINUOUS MONITORING & ALERTING ARCHITECTURE

## 1. OBSERVABILITY TOPOLOGY & METRICS PIPELINE

The NHMP 130 Helpline CRM implements centralized multi-layer observability across the infrastructure, runtime, application, and security audit domains.

```
┌────────────────────────────────────────────────────────────────────────┐
│                          METRICS EXPORTERS                             │
├───────────────────┬───────────────────┬────────────────────────────────┤
│ 1. Node Exporter  │ 2. MySQL Exporter │ 3. RoadRunner Worker Stats     │
│    (Host metrics) │    (Query stats)  │    (RPC :6001 stats)           │
├───────────────────┼───────────────────┼────────────────────────────────┤
│ 4. HAProxy Stats  │ 5. Nginx VTS / Up │ 6. Laravel Application Logs    │
│    (:8404 socket) │    (Ingress RPS)  │    (Structured JSON logs)      │
└───────────────────┴───────────────────┴────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        PROMETHEUS ENGINE (:9090)                       │
│                        Scrapes all targets every 10s                   │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                   GRAFANA OPERATIONAL DASHBOARDS (:3000)               │
│                   Alerting Rules & PagerDuty/Webhook Dispatch          │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. INDEPENDENT NODE MONITORING

Both application nodes are monitored as independent upstream targets in Prometheus:
- **Node 1:** `web-1:80` (Nginx), `app-1:8000` (RoadRunner), `app-1:6001` (RoadRunner RPC).
- **Node 2:** `web-2:80` (Nginx), `app-2:8000` (RoadRunner), `app-2:6001` (RoadRunner RPC).

HAProxy independently monitors the `/up` health probe of both nodes every 3 seconds (`inter 3s fall 3 rise 2`).

---

## 3. HIGH-PRIORITY SECURITY & RUNTIME ALERT DEFINITIONS

| Alert Identifier | Category | Trigger Condition | Severity | Automated Response & Escalation |
| :--- | :--- | :--- | :--- | :--- |
| `SEC-AUTH-ANOMALY` | Security | > 10 failed login attempts from single IP in 60s | **High** | Temporary IP rate limit lock; alert SOC |
| `SEC-PRIV-ESCALATE` | Security | Any unauthorized role or permission assignment | **Critical** | Immediate session revocation; alert CISO |
| `SEC-IDOR-DENIAL` | Security | > 3 cross-scope/unauthorized record access denials | **High** | Flag user account; trigger security audit review |
| `SEC-BULK-EXPORT` | Security | Export of > 5,000 records within 10 minutes | **Medium** | Log to immutable audit trail; notify supervisor |
| `SEC-DECRYPT-SPIKE` | Security | Decryption volume exceeds 1,000 operations/min | **High** | Investigate scraping activity |
| `PERF-WORKER-MEMORY`| Runtime | RoadRunner worker RSS memory > 115 MB | **Warning** | Supervisor automatically schedules worker recycle |
| `PERF-WORKER-CRASH` | Runtime | > 2 RoadRunner worker restarts within 5 minutes | **Critical** | Page DevOps on-call; inspect stack trace logs |
| `DB-SATURATION` | Database | Active MySQL connections > 80% of max limit | **High** | Investigate slow queries; throttle bulk queries |
| `REDIS-DISCONNECT` | Cache | Redis ping failure or latency > 100ms | **High** | Fallback to database cache; reconnect daemon |
| `QUEUE-BACKLOG` | Operations | Queue backlog > 500 unprocessed jobs for > 5 min| **Medium** | Auto-scale background queue workers |
| `BACKUP-FAILURE` | DR | Daily encrypted backup job fails or missing | **Critical** | Immediate pager alert; trigger manual backup |
| `CONFIG-DRIFT` | Governance | Modification of `.env`, `nginx.conf`, or `haproxy.cfg`| **High** | Alert DevOps; verify Git version control hash |

---

## 4. STRUCTURED APPLICATION LOGGING

All Laravel and RoadRunner logs are output in machine-parsable JSON format:
```json
{
  "timestamp": "2026-11-05T21:17:33Z",
  "level": "INFO",
  "environment": "staging",
  "worker_pid": 312,
  "user_id": 1,
  "role": "super_admin",
  "event": "call.view",
  "resource_id": 49201,
  "ip_address": "127.0.0.1",
  "status_code": 200,
  "duration_ms": 4.2
}
```

PII masking rules automatically sanitize sensitive fields (`caller_number`, `password`, `auth_token`, `pin`) before log serialization.
