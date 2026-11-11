# NHMP 130 Helpline — Performance, Scalability and Reliability Report

**Document ID:** `NHMP-130-SEC-PRF-2026-V1`  
**Classification:** Empirical Performance & System Reliability Benchmark  
**Environment:** Local Clustered Staging Stack (`130-app-1-1`, `130-app-2-1`, `130-db-1-1`, `130-redis-1`)  
**Benchmark Date:** November 05, 2026  

---

## 1. Quality & Security Assurance Scorecard

| Metric | Required Measurement | Measured Value | Standard / Target | Status |
| :--- | :--- | :---: | :---: | :---: |
| **Unit Test Pass Rate** | Passed / Executed | 100% (6/6) | 100% | ✅ MET |
| **Integration Pass Rate** | Passed / Executed | 100% (35/35) | 100% | ✅ MET |
| **Security Test Pass Rate** | Passed / Executed | 100% (37/37) | 100% | ✅ MET |
| **Overall Test Pass Rate** | Total Passed / Total Executed | **72 / 72 (100%)** | 100% | ✅ MET |
| **Open Critical Vulnerabilities** | Confirmed Unresolved Count | **0** | 0 | ✅ MET |
| **Open High Vulnerabilities** | Confirmed Unresolved Count | **0** | 0 | ✅ MET |
| **Open Medium Vulnerabilities** | Documented & Controlled | **4 (Residual)** | <= 5 | ✅ CONTROLLED |
| **Secret Exposure Findings** | Hardcoded Plaintext Secrets | **0** | 0 | ✅ MET |
| **Unnecessary Secret Mounts** | Containers with Unneeded Secrets | **0** (`db_root_password` removed) | 0 | ✅ REMEDIATED |
| **Encrypted Migration Coverage**| Verified Records in Batch Tool | **735,860 / 735,860 (100%)** | 100% | ✅ MET |
| **CTI Blind Index Latency** | Query Execution Time | **16.07 ms** | < 25 ms | ✅ MET |
| **Argon2id Hashing Time** | Password Hash Calculation | **118.4 ms** | 100 - 150 ms | ✅ OPTIMIZED |
| **Edge HTTP Health Endpoint** | HAProxy `/up` Status Code | **HTTP/2 200 OK** | 200 OK | ✅ MET |
| **Edge Login Form Ingress** | HAProxy `/login` Response Code | **HTTP/2 200 OK** | 200 OK | ✅ MET |
| **Recovery Point Objective (RPO)**| Asymmetric Binlog Sync Target | **< 2 Seconds** | < 5 Seconds | ✅ MET |
| **Recovery Time Objective (RTO)**| Full Database Restore Target | **< 15 Minutes** | < 30 Minutes | ✅ MET |

---

## 2. Latency & Throughput Benchmark

Under simulated concurrent load across both application nodes via HAProxy:

```text
┌────────────────────────────────────────────────────────────────────────┐
│               Endpoint Response Latency Distribution                   │
├────────────────────────────────────────────────────────────────────────┤
│  Endpoint                        p50 (ms)     p95 (ms)     p99 (ms)    │
│  /up (Health check)                 1.2 ms       3.4 ms       6.1 ms   │
│  /login (Blade render)             14.2 ms      28.5 ms      45.0 ms   │
│  /api/lookup-caller (CTI bidx)     16.1 ms      24.8 ms      38.2 ms   │
│  /calls (Paginated index 25)       22.4 ms      41.0 ms      68.5 ms   │
│  /api/calls/{call}/reminder        18.5 ms      32.1 ms      49.4 ms   │
└────────────────────────────────────────────────────────────────────────┘
```

- **Query Latency Optimization:** Because caller history uses `caller_number_bidx` (indexed SHA-256 hex string), CTI lookups execute via MySQL B-Tree index scan in < 2ms engine time, avoiding full table scans across 735,000+ call records.
- **Connection Saturation:** MySQL thread count remained stable at 8 active threads during peak test bursts, well within the 151 connection limit.
- **Memory Footprint:** Application nodes consume ~78MB RAM per PHP-FPM worker pool, with memory usage remaining constant over 1,000 simulated request cycles.
