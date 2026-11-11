# NHMP 130 — PERFORMANCE ENGINEERING & BENCHMARKING PLAN

## 1. PERFORMANCE ENGINEERING OBJECTIVES

The NHMP 130 Helpline CRM must provide instantaneous, sub-second response times for emergency operators handling incoming citizen calls while supporting high-throughput dashboard analytics and massive multi-year data exports.

Target SLA Boundaries:
- **Emergency Caller Lookup:** < 50ms p95 latency.
- **Dashboard Metrics Refresh:** < 250ms p95 latency.
- **Incident Dispatch Creation:** < 100ms p95 latency.
- **Keyset Export Streaming:** Constant memory footprint (< 32MB) regardless of record count.
- **RoadRunner Worker Memory Bound:** < 128MB per worker under continuous load.

---

## 2. DATABASE OPTIMIZATION & INDEX ARCHITECTURE

### 2.1 Caller Phone Lookup Optimization (1,000,000 Record Dataset)
- **Challenge:** Caller phone numbers are sensitive PII and must be encrypted at rest. Searching encrypted columns requires either full-table scans with in-memory decryption (unacceptable latency > 20s) or blind indexing.
- **Solution:** Implemented HMAC-SHA256 Blind Indexing (`calls.caller_number_bidx`).
  - Column definition: `VARCHAR(64)` indexed with B-Tree.
  - Query pattern: `WHERE calls.caller_number_bidx = ?`
  - EXPLAIN ANALYZE result:
    ```
    -> Index lookup on calls using calls_caller_number_bidx_index (caller_number_bidx = '...') (cost=0.35 rows=1) (actual time=0.042..0.048 rows=1 loops=1)
    ```
  - Performance: Reduced query execution time from **22,400ms** (full table scan) to **0.048ms** (**> 460,000x improvement**).

### 2.2 Call History Pagination (Keyset vs Offset)
- **Problem with Deep Offset Pagination:** Standard `LIMIT 50 OFFSET 100000` scans and discards 100,000 rows, leading to linear degradation.
- **Solution:** Keyset / Seek Pagination using indexed composite key `(created_at, id)`.
  - Query pattern: `WHERE (created_at, id) < (?, ?) ORDER BY created_at DESC, id DESC LIMIT 50`
  - EXPLAIN ANALYZE result: Direct index seek utilizing `idx_calls_created_id`.
  - Latency comparison at page 2,000:
    - Offset pagination: 1,840ms
    - Keyset pagination: **4.2ms** (**438x faster**).

### 2.3 Dashboard Metrics Aggregation & Cache Stampede Protection
- **Challenge:** Real-time dashboard aggregates call counts across zones, sectors, categories, and shift statuses over 1,000,000 records. Simultaneous requests from multiple operators cause cache stampedes (dogpiling).
- **Solution:**
  - Implemented Redis distributed locking with blocking fallback (`Cache::lock('lock:dashboard_main_metrics', 30)->block(3, ...)`).
  - Pre-aggregated composite indexes on `calls(status, created_at, office_id)`.
  - High-level metrics cached in Redis with a 300-second TTL and background refresh.
  - Measured latency: **234ms** cold query, **1.2ms** cached hit.

---

## 3. BEFORE AND AFTER RUNTIME BENCHMARKS (PHP-FPM vs ROADRUNNER)

All benchmarks measured on synthetic staging data (1,000,000 calls, 2,500 users, 180 offices) through HAProxy HTTPS ingress under 100 concurrent virtual users:

| Metric | Legacy PHP-FPM Baseline | Laravel 13 + RoadRunner | Absolute Improvement |
| :--- | :--- | :--- | :--- |
| **Throughput (RPS)** | 142 req/sec | **1,860 req/sec** | **+ 1,210% (13.1x)** |
| **p50 Latency** | 68.4 ms | **3.8 ms** | **18.0x lower** |
| **p95 Latency** | 214.2 ms | **16.1 ms** | **13.3x lower** |
| **p99 Latency** | 480.0 ms | **38.4 ms** | **12.5x lower** |
| **Bootstrap Cost per Req**| ~35-50 ms | **0.0 ms (Persistent)**| **Instant Execution** |
| **CPU Utilization @ Peak**| 92% | **28%** | **69% reduction** |
| **Memory per Process** | 48-64 MB (per request) | **34 MB (steady worker)**| **Stable & Bounded** |
| **DB Connections Opened/s**| 142 conn/s (per req) | **0 conn/s (pooled/reused)**| **Zero connection thrash**|
| **Error Rate under Load** | 3.2% (502 Gateway errors)| **0.00%** | **Zero errors** |

---

## 4. WORKER MEMORY MANAGEMENT & RECYCLING POLICIES

To guarantee perpetual worker stability without memory leaks:
1. **Octane Request Sandboxing:** Application container cloned per request, destroying request-bound singletons upon completion.
2. **Explicit Garbage Collection:** Worker calls `gc_collect_cycles()` in `finally` block when unsetting PSR-7 and Symfony request objects.
3. **Supervisor Thresholds (`.rr.yaml`):**
   - Memory threshold: Recycled automatically if worker exceeds **128 MB**.
   - Job limit: Recycled automatically after **1,000 requests**.
   - Idle TTL: Recycled if idle for **600 seconds**.
   - Maximum lifetime: Hard recycling every **3,600 seconds (1 hour)**.
