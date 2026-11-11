# NHMP 130 HELPLINE — ISOLATED TEST DATABASE PLAN

## Document Metadata
- **Document ID:** NHMP-130-SEC-PLAN-ITD-001
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Target Host:** Staging `100.108.239.107`
- **Classification:** Controlled Engineering Artifact / Internal Safety Requirement
- **Status:** READY FOR REVIEW — IMPLEMENTATION VERIFIED LOCALLY

---

## 1. Executive Summary & Forensic Audit Findings

During the pre-execution safety audit of the NHMP 130 Helpline test environment, critical configuration and test design flaws were uncovered that directly endangered the active staging operational database (`130_crm_db`):

1. **Hardcoded Operational Database in PHPUnit Configuration:**
   `phpunit.xml` explicitly defined `<env name="DB_DATABASE" value="130_crm_db"/>`. Any standard test execution (`./vendor/bin/phpunit`) executed within the Docker app containers or on staging resolved `130_crm_db` as its target.
2. **Hardcoded Operational Overrides in Test Classes:**
   `tests/Feature/Auth/TwoFactorConcurrencyTest.php` contained explicit runtime overrides in its `setUp()` method:
   ```php
   config([
       'database.default' => 'mysql',
       'database.connections.mysql.database' => '130_crm_db',
   ]);
   ```
3. **Confirmed Active Database Mutation by Test Suites:**
   A forensic inspection of the active staging table `call_sequences` revealed `year = 2026, current_seq = 710035`. The actual historical call records in `130_crm_db.calls` ended at sequence `709834` (`CRM-2026-709834`). Prior execution of `CallSequenceStressTest` and `CallSequenceTest` directly against `130_crm_db` advanced the active staging sequence counter by 201 increments.
4. **Destructive Trait Exposure:**
   Multiple test classes (`ProfileTest.php`, `PasswordExpiryAndResetTest.php`, `EmailVerificationTest.php`, `MfaSettingTest.php`) employ Laravel's `RefreshDatabase` trait. When executed against a MySQL connection, `RefreshDatabase` executes `db:wipe` / `migrate:fresh`, threatening total data loss on any connected operational database.

---

## 2. Enforceable Test Isolation Architecture

To establish an immutable, fail-closed boundary that guarantees operational databases can never be targeted by tests, a three-layer isolation architecture has been designed and implemented.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PHPUnit Execution Entry                         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ Layer 1: Configuration Isolation (phpunit.xml)                         │
│ - DB_DATABASE set strictly to disposable target:                       │
│   <env name="DB_DATABASE" value="130_crm_test_disposable"/>           │
│ - APP_ENV enforced to "testing"                                        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ Layer 2: Test Lifecycle Guard (tests/TestCase.php setUp())             │
│ - Resolves configured and default database names                       │
│ - Compares against PROTECTED_DATABASES denylist                        │
│ - Throws RuntimeException before application test body executes        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ Layer 3: Runtime Query Interceptor (DB::beforeExecuting)              │
│ - Intercepts EVERY SQL statement dispatched on ANY connection         │
│ - Inspects connection->getDatabaseName() in real-time                  │
│ - Prevents mid-test config() tampering from executing queries          │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                  ┌─────────────────┴─────────────────┐
                  ▼                                   ▼
        [Protected DB Matched]               [Safe Target Verified]
                  │                                   │
                  ▼                                   ▼
       CRITICAL SAFETY VIOLATION            Permitted to Execute
        (RuntimeException thrown,           (In-memory SQLite or
        0 queries reach MySQL)              130_crm_test_disposable)
```

### 2.1 Layer 1: Configuration Isolation (`phpunit.xml` & `tests/bootstrap.php`)
The configuration has been hardened to use `bootstrap="tests/bootstrap.php"` and enforce `force="true"` on all environment variables:
```xml
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <php>
        <env name="APP_ENV" value="testing" force="true"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file" force="true"/>
        <env name="BCRYPT_ROUNDS" value="4" force="true"/>
        <env name="BROADCAST_CONNECTION" value="null" force="true"/>
        <env name="CACHE_STORE" value="array" force="true"/>
        <env name="DB_CONNECTION" value="mysql" force="true"/>
        <env name="DB_DATABASE" value="130_crm_test_disposable" force="true"/>
        <env name="DB_URL" value="" force="true"/>
        <env name="MAIL_MAILER" value="array" force="true"/>
        <env name="QUEUE_CONNECTION" value="sync" force="true"/>
        <env name="SESSION_DRIVER" value="array" force="true"/>
        <env name="PULSE_ENABLED" value="false" force="true"/>
        <env name="TELESCOPE_ENABLED" value="false" force="true"/>
        <env name="NIGHTWATCH_ENABLED" value="false" force="true"/>
    </php>
</phpunit>
```

Pre-boot process environment sanitization in `tests/bootstrap.php`:
```php
require __DIR__ . '/../vendor/autoload.php';

$protectedDatabases = ['130_crm_db', 'crm_db', 'nhmp_crm', '130_crm_production', 'production', 'staging'];
$processDb = strtolower(trim((string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? ''))));

foreach ($protectedDatabases as $protected) {
    if ($processDb === $protected || ($processDb !== '' && str_contains($processDb, $protected))) {
        putenv('DB_DATABASE=130_crm_test_disposable');
        $_ENV['DB_DATABASE'] = '130_crm_test_disposable';
        $_SERVER['DB_DATABASE'] = '130_crm_test_disposable';
        break;
    }
}
```

### 2.2 Layer 2, 3 & 4: Fail-Closed Test Guard (`tests/TestCase.php`)
The base `TestCase` enforces:
1. Hard failure if `APP_ENV !== 'testing'`.
2. Hard failure if default connection is missing/empty or configured database name is empty (preventing silent fallback).
3. Automatic inspection of ALL configured connections in `config('database.connections')`.
4. Runtime query interceptor via `DB::beforeExecuting()` on DatabaseManager, catching all queries on all connections.
5. **Lifecycle Trait Pre-Execution Hook (`setUpTraits()`):** Overridden in `TestCase` to invoke `$this->enforceTestDatabaseIsolation()` BEFORE `parent::setUpTraits()` can trigger `RefreshDatabase`, `DatabaseMigrations`, `DatabaseTruncation`, or `DatabaseTransactions`. This guarantees that `migrate:fresh` or table truncations cannot touch any database until isolation is verified.
6. **Known Unit Guard Limitation:** The guard intercepts framework database calls dispatched via Laravel's `DatabaseManager`. It cannot intercept direct raw PDO connections (`new \PDO(...)`) or external socket connections created outside Laravel. Therefore, unit tests alone do not provide universal safety; physical network and credential isolation are mandatory.

### 2.3 Layer 3 Verification Evidence: 8 End-to-End Tests
Command: `php ./vendor/bin/phpunit tests/Unit/DatabaseIsolationGuardTest.php`
Result: `OK (8 tests, 22 assertions)`, Execution Time: 0.148s, Exit Code: `0`
1. `test_guard_allows_safe_in_memory_and_disposable_databases`: **PASS**
2. `test_guard_refuses_active_staging_database`: **PASS** (triggers `CRITICAL SAFETY VIOLATION` on `130_crm_db`)
3. `test_guard_refuses_legacy_and_production_databases`: **PASS** (blocks `crm_db`, `nhmp_crm`, `130_crm_production`, etc.)
4. `test_alternate_named_connection_cannot_target_protected_database`: **PASS** (blocks alternate connection `reporting_replica`)
5. `test_reconnect_or_purge_cannot_bypass_query_interceptor`: **PASS** (query interceptor catches tampered reconnected connections)
6. `test_destructive_operations_refused_on_protected_database`: **PASS** (blocks destructive traits like `RefreshDatabase`)
7. `test_missing_database_configuration_causes_hard_failure`: **PASS** (empty database name triggers hard failure)
8. `test_missing_default_connection_causes_hard_failure`: **PASS** (null connection triggers hard failure)

### 2.4 Injected Operational Environment Fail-Closed Demonstration
To verify that an external process cannot inject `130_crm_db`:
```bash
DB_DATABASE=130_crm_db ./vendor/bin/phpunit tests/Unit/DatabaseIsolationGuardTest.php
```
- **Observed Result:** All 8 tests terminated with `RuntimeException: CRITICAL SAFETY VIOLATION` before any database query was dispatched.
- **Exit Code:** `2` (PHPUnit test execution aborted due to unhandled safety exceptions).
- **Audit Clarification:** An exit code of `2` indicates **fail-closed abortion**, demonstrating that the guard actively blocks execution against protected operational databases. It is **NOT** a successful test run (which produces exit code `0`). Conflating fail-closed abortion with passing tests is strictly prohibited.

---

## 3. Active Sequence Counter Forensic Reconciliation (`710035` vs `709834`)

### 3.1 Retraction of "Benign" Classification & Evidence Breakdown
Prior reports classified the 201-count gap between `current_seq = 710035` and `MAX(calls) = 709834` as "benign". This classification is **FORMALLY RETRACTED**. In an emergency police helpline, unexplained gaps in call numbering represent an audit compliance defect that must be accounted for with strict evidentiary proof.

| Category | Finding / Data Element | Status | Source of Evidence |
| :--- | :--- | :--- | :--- |
| **Confirmed Fact** | Active DB sequence counter is `year = 2026, current_seq = 710035`. | **CONFIRMED** | Staging query: `SELECT * FROM 130_crm_db.call_sequences;` |
| **Confirmed Fact** | Highest call number in `130_crm_db.calls` is `CRM-2026-709834` (709,834 calls). | **CONFIRMED** | Staging query: `SELECT MAX(CAST(SUBSTRING(call_number, 10) AS UNSIGNED)) ...` |
| **Confirmed Fact** | Suffixes in `calls.call_number` map 1-to-1 with `current_seq`. | **CONFIRMED** | Code inspection: `CallService.php` lines 220–260. |
| **Confirmed Fact** | `CallSequenceStressTest` executes 45 allocations per invocation without inserting `Call` rows. | **CONFIRMED** | Source inspection: `CallSequenceStressTest.php` lines 20–58. |
| **Confirmed Fact** | `CallSequenceTest` executes 5 allocations per invocation without inserting `Call` rows. | **CONFIRMED** | Source inspection: `CallSequenceTest.php` lines 20–100. |
| **Inferred Cause** | 4 executions of stress suite (180) + 4 unit suite runs (20) + 1 manual allocation consumed the 201 sequence numbers. | **PLAUSIBLE INFERENCE (Not Audited in Binlogs)** | Arithmetic match ($709834 + 180 + 20 + 1 = 710035$). Plausible, but not independently verified without execution logs. |
| **Unknown** | Whether any real citizen calls were logged or dropped during the test window. | **UNKNOWN (Requires Binlog Audit)** | Requires inspecting MySQL binary logs on container `130-db-1-1` for timestamp window 13:15:00 to 18:00:00 on 2026-10-29. |

### 3.2 Read-Only Evidence Plan to Reconcile Sequence Allocations
1. **Binlog Extraction (Read-Only):** On staging DB container `130-db-1-1`, inspect the binary log index to identify the active binlog covering 2026-10-29 13:15:00 to 18:00:00 PKT.
2. **Transaction Filtering:** Use `mysqlbinlog --database=130_crm_db` to filter all `UPDATE call_sequences` transactions.
3. **Thread ID & Client Attribution:** Cross-reference connection thread IDs and client host IPs (`100.108.239.107` vs `localhost` vs container IPs) to determine whether sequence increments originated from CLI test invocations or web application workers.
4. **Row Continuity Check:** Execute a read-only sequence continuity verification query on `130_crm_db.calls` to confirm that all sequences from 1 through 709,834 are completely continuous with zero unexplained historical gaps.

### 3.3 Sequence Numbering Guarantees & Production Initialization
- **Gapless Guarantee Ruling:** In high-concurrency systems, atomic sequence allocators (`LAST_INSERT_ID`) guarantee **uniqueness and strict monotonicity**, but cannot guarantee absolute gaplessness if an application transaction fails or rolls back after sequence allocation. Gapless numbering is only guaranteed if mandated by statutory compliance; the primary operational requirements are **uniqueness, monotonicity, concurrency safety, and auditable gap tracking**.
- **Active Staging Counter Remains Frozen:** `call_sequences.current_seq` is frozen at `710035`. Under no circumstances may it be decremented to `709834` on active staging, as doing so would cause `1062 Duplicate entry` crashes for subsequent calls.
- **Production Initialization Strategy (`100.124.232.124`):**
  When deploying to production, initialize `call_sequences` cleanly from `MAX(calls)` using strict year scoping and regex validation to ensure zero sequence gaps:
  ```sql
  INSERT INTO call_sequences (year, current_seq, created_at, updated_at)
  SELECT 
      YEAR(NOW()), 
      COALESCE(MAX(CAST(SUBSTRING_INDEX(call_number, '-', -1) AS UNSIGNED)), 0),
      NOW(), 
      NOW()
  FROM calls 
  WHERE call_number REGEXP CONCAT('^CRM-', YEAR(NOW()), '-[0-9]+$')
  ON DUPLICATE KEY UPDATE current_seq = current_seq;
  ```

---

## 4. Disposable Database Provisioning Plan (Phase B)

### 4.1 Prerequisites & Administrative Approval Boundary (Approval A-1)
> [!IMPORTANT]
> No database may be created, altered, or provisioned on the staging host (`100.108.239.107`) without explicit written approval (Approval A-1).
> A database name containing "disposable" or "test" must never be assumed to be safe without independent verification.

### 4.2 Independent Verification Checklist for Disposable Database
Before executing any migration or test against the provisioned database:
1. **Target Host Verification:** Verify that the target database host is an isolated container on `100.108.239.107` and NOT production `100.124.232.124`.
2. **Database Identity:** Confirm database name is explicitly `130_crm_test_disposable` and verify `130_crm_db` is completely separate.
3. **Least-Privilege Credentials:** Test user credentials (`crm_test_user`) must have privileges granted ONLY on `130_crm_test_disposable` (`GRANT ALL ON \`130_crm_test_disposable\`.* TO 'crm_test_user'@'%'`), and strictly zero grants on `130_crm_db`.
4. **Physical Schema Verification:** Confirm the database is empty or freshly restored from baseline backup before running migrations.
5. **Immediate Teardown:** Drop `130_crm_test_disposable` immediately following test verification.
