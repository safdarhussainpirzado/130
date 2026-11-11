# NHMP 130 HELPLINE — CALL CONCURRENCY VERIFICATION PLAN

## Document Metadata
- **Document ID:** NHMP-130-SEC-PLAN-CCV-003
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Target Host:** Staging `100.108.239.107`
- **Candidate Image Tag:** `130-app:candidate-safety-audit`
- **Classification:** Controlled Engineering Artifact / Concurrency Verification Plan
- **Status:** READY FOR REVIEW — SPECIFICATION DEFINED & PROVEN IN CODE

---

## 1. Discrepancy Reconciliation: Git Working Copy vs Running Containers

A critical finding of the forensic safety audit is that **the atomic call-number sequence allocation refactoring is present ONLY in the Git working copy, NOT inside the active staging containers**:

| Feature / Implementation | Active Container `130-app-1-1` | Git Repository (`feat/staging-remediation-baseline`) |
| :--- | :--- | :--- |
| **Image Digest / Build Age** | `130-app-1` (Created 3 days ago) | Not yet built into container image |
| **Call Number Generator** | Uses unindexed table scan and unbounded loop: `while (DB::table('calls')->where('call_number', $callNumber)->exists())` | Atomic `affectingStatement("UPDATE call_sequences SET current_seq = LAST_INSERT_ID(current_seq + 1)")` |
| **Locking Mechanism** | Unindexed existence check against 709k rows | Row-level atomic sequence lock via primary key (`year`) |
| **Connection Affinity** | Standard query builder (split connection risk) | Direct write PDO handle: `$connection->getPdo()->query("SELECT LAST_INSERT_ID()")` |
| **Year Rollover** | Table scan with `ON DUPLICATE KEY UPDATE` | Pessimistic `lockForUpdate()` transaction ensuring single-worker initialization |
| **Collision Recovery** | Unbounded `while()` loop (infinite loop hazard) | Bounded retry loop (max 3 attempts) in `createCall()` with collision logging |
| **Staging Runtime Status** | **ACTIVE IN PRODUCTION/STAGING CONTAINER** | **UNVERIFIED AT RUNTIME IN STAGING CONTAINER** |

> [!WARNING]
> Prior claims that the atomic sequence optimization was runtime-verified on staging are **FACTUALLY INACCURATE** and are hereby retracted. Staging application containers `130-app-1-1` and `130-app-2-1` continue to execute the legacy unbounded loop.

---

## 2. Candidate Image Build Specification (`130-app:candidate-safety-audit`)

To verify the refactored code without disrupting running staging services or risking capacity exhaustion:

### 2.1 Staging Host Capacity Verification
Before proposing any Docker build, the host storage capacity was verified:
- Total Root Volume: `392G`
- Used Space: `36G` (10%)
- Available Free Space: `340G`
- Reclaimable Docker Cache: `5.318GB`
- Host memory: `16GB` (adequate for ephemeral build)

### 2.2 Immutable Build & Metadata Audit Requirements
The candidate image must be built using an isolated tag without replacing or restarting active containers `130-app-1-1` or `130-app-2-1`:
```bash
docker build -t 130-app:candidate-safety-audit -f Dockerfile .
```

The candidate verification register must record:
1. **Source Git Commit:** `71a745d` (or exact commit hash at build time)
2. **Git Working Tree Status:** Clean (`git diff-index --quiet HEAD`)
3. **Candidate Image Digest:** `sha256:...`
4. **PHP Runtime Version:** Record exact `php -v` from candidate image (`php:8.3.36-fpm-alpine` as declared in `Dockerfile:50`). Explicitly distinguish from host workstation CLI (`PHP 8.3.6`) and active staging container `130-app-1-1` (`PHP 8.3.1`).
5. **Key Package Versions:**
   - `laravel/framework`: `v11.x`
   - `pragmarx/google2fa`: `v8.0.3`
   - `bacon/bacon-qr-code`: `v3.0.1`
   - `spatie/laravel-permission`: `v6.x`
6. **Code Identity Audit:** SHA-256 checksum of `/var/www/html/app/Services/CallService.php` inside the candidate image must match the repository working copy (`sha256sum app/Services/CallService.php`).
7. **Administrative Approval Boundary:** Building this candidate image requires explicit **Approval A-2**. It must NOT replace, restart, or retag running containers `130-app-1-1`, `130-app-2-1`, or `130-db-1-1`.

---

## 3. Truly Independent Multi-Process Concurrency Test Harness

### 3.1 Failure of Sequential Loops as Concurrency Proof
Prior test scripts (such as `CallSequenceStressTest.php`) ran sequential `for ($i = 0; $i < 25; $i++)` loops inside a single PHP process. A single-threaded sequential loop can NEVER prove concurrency safety, race condition prevention, or deadlock resilience under multi-worker contention.

### 3.2 Multi-Process Architecture
The verification harness executes genuinely concurrent, independent OS processes using `xargs -P` or parallel background PHP workers, each opening an independent physical MySQL connection:

```
                      [Concurrency Harness Controller]
                                      │
              ┌───────────────────────┼───────────────────────┐
              ▼                       ▼                       ▼
      [Worker Process 1]      [Worker Process 2]     ... [Worker Process 50]
      (PDO Conn #1)           (PDO Conn #2)              (PDO Conn #50)
              │                       │                       │
              └───────────────────────┼───────────────────────┘
                                      ▼
                      [MySQL 8.4 Disposable Database]
                      (130_crm_test_disposable)
                                      │
                      ┌───────────────┴───────────────┐
                      ▼                               ▼
            `call_sequences` table             `calls` table
           (Atomic LAST_INSERT_ID)       (Unique constraint checks)
```

### 3.3 Test Matrix Specification

| Test Case ID | Test Name | Scenario & Contention Parameters | Pass / Validation Criteria |
| :--- | :--- | :--- | :--- |
| **TC-CC-01** | **Massive Concurrent Allocation** | 50 concurrent independent processes allocating 20 call numbers each (1,000 total calls) against `130_crm_test_disposable`. | - Exactly 1,000 distinct numbers generated.<br>- Zero duplicate call numbers (`COUNT(DISTINCT call_number) = 1000`).<br>- Strictly monotonic sequence with zero internal gaps.<br>- Zero unhandled deadlock or crash exceptions. |
| **TC-CC-02** | **Duplicate Key Collision Recovery** | Inject a colliding pre-allocated sequence number into `calls` while concurrent workers are logging calls. | - Bounded retry in `createCall()` catches `UniqueConstraintViolationException`.<br>- Advances to next sequence and succeeds within 3 attempts.<br>- Logs warning without failing request. |
| **TC-CC-03** | **Transaction Rollback Gap Protection** | Worker begins transaction, increments sequence, logs call, and throws `RuntimeException` causing `DB::rollBack()`. | - In MySQL InnoDB, row update is rolled back.<br>- Subsequent transaction re-uses or re-allocates sequence cleanly without orphaned state. |
| **TC-CC-04** | **Cold Year Initialization** | Table `call_sequences` has NO entry for year `2027`. 20 concurrent workers simultaneously request call numbers for `2027`. | - Pessimistic transaction lock (`lockForUpdate()`) ensures exactly ONE worker initializes the row from `MAX(calls)` or 1.<br>- 19 remaining workers wait on lock and increment monotonically.<br>- No duplicate key crash on sequence initialization. |
| **TC-CC-05** | **Connection Affinity Verification** | Verify write PDO handle is strictly retained across `UPDATE` and `LAST_INSERT_ID()` retrieval under read/write split simulation. | - `LAST_INSERT_ID()` value matches the sequence incremented on that exact connection. |
| **TC-CC-06** | **Deadlock & Transient Fault Handling** | Simulate concurrent lock contention on sequence rows under high CPU pressure. | - Framework connection retry or PDO lock-wait-timeout recovery completes successfully. |
| **TC-CC-07** | **Worker Crash Recovery** | Kill worker process (`kill -9`) mid-allocation. | - MySQL releases InnoDB row lock upon connection termination.<br>- Subsequent workers continue without permanent deadlock. |

---

## 4. Reproducible Throughput Benchmark Protocol

To prevent unevidenced performance claims, all throughput figures must adhere to a standardized benchmark protocol:

1. **Database Target:** Verified disposable MySQL 8.4 container with identical InnoDB buffer pool configuration (`innodb_buffer_pool_size=1G`).
2. **Workload Definition:** Full call logging transaction (`CallService::createCall`) including priority resolution, sequence allocation, record persistence, and event dispatch.
3. **Benchmark Parameters to Record:**
   - Concurrency Level (1, 10, 25, 50, 100 concurrent workers)
   - Duration (seconds)
   - Total Calls Logged
   - Observed Throughput (Transactions Per Second / TPS)
   - Latency Distribution (min, p50, p95, p99, max ms)
   - CPU & Memory utilization on DB container
   - Error count and exception types
4. **Acceptance Threshold:**
   - TPS must exceed 200 call creations/sec on staging MySQL container.
   - p95 latency must remain under 25ms.
   - Error rate must be **0.00%**.

---

## 5. Execution Boundary

> [!CAUTION]
> The concurrency test harness and candidate container validation MUST ONLY be executed against an independently confirmed disposable database (`130_crm_test_disposable`) under explicit **Approval A-3**.
> 
> Executing concurrency stress tests against the active staging database `130_crm_db` is **STRICTLY PROHIBITED**.
> All logs generated during concurrency and throughput testing must be sanitized to ensure zero citizen PII or credentials are exposed.
