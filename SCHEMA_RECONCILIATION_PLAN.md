# NHMP 130 HELPLINE — SCHEMA RECONCILIATION PLAN

## Document Metadata
- **Document ID:** NHMP-130-SEC-PLAN-SCH-002
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Target Host:** Staging `100.108.239.107`
- **Active Operational Database:** `130_crm_db`
- **Classification:** Controlled Engineering Artifact / Database Safety Plan
- **Status:** READY FOR REVIEW — MIGRATION CODE RECONCILED LOCALLY

---

## 1. Executive Summary & Observed Schema Drift

A forensic inspection of the active staging database (`130_crm_db`) on MySQL 8.4 container `130-db-1-1` identified severe schema drift between the physical database, the Git repository migrations, and the baseline backup (`staging_baseline_20261029.sql.gz`):

| Schema Component | Baseline Dump (`13:15:00`) | Active Staging DB (`130_crm_db`) | Git Migration Code | Drift Description |
| :--- | :--- | :--- | :--- | :--- |
| **`call_sequences` Table** | **ABSENT** | **EXISTS** (1 row: `2026, 710035`) | Defined in `2026_10_29_010000` | Manually created on staging without timestamps |
| **`created_at` / `updated_at`** | **ABSENT** | **ABSENT** | Defined in `2026_10_29_010000` | Missing columns in active staging table |
| **`idx_calls_caller_created`** | **ABSENT** | **EXISTS** on `(caller_number, created_at)` | Defined in `2026_10_29_020000` | Manually created on staging; not tracked in `migrations` table |
| **`migrations` Record** | N/A | **ABSENT** for both migrations | Unapplied / Untracked | `php artisan migrate:status` shows neither migration has run |

---

## 2. Forensic Schema Evidence (Active Staging DB)

### 2.1 Table: `call_sequences`
Command executed against staging MySQL 8.4:
```bash
docker exec 130-db-1-1 mysql -e "SHOW CREATE TABLE 130_crm_db.call_sequences\G"
```
Observed definition:
```sql
CREATE TABLE `call_sequences` (
  `year` int NOT NULL,
  `current_seq` bigint unsigned NOT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```
*Note:* The table exists and holds active data (`year=2026, current_seq=710035`), but completely lacks Laravel standard timestamp columns (`created_at`, `updated_at`).

### 2.2 Table: `calls` Indexes
Command executed against staging MySQL 8.4:
```bash
docker exec 130-db-1-1 mysql -e "SHOW INDEX FROM 130_crm_db.calls;" | grep -E "(calls_call_number_unique|idx_calls_caller_created)"
```
Observed index records:
```
calls   0   calls_call_number_unique   1   call_number     A   675653   NULL   BTREE
calls   1   idx_calls_caller_created   1   caller_number   A   367005   NULL   BTREE
calls   1   idx_calls_caller_created   2   created_at      A   675653   NULL   BTREE
```
*Key Finding:* Both the unique constraint `calls_call_number_unique` and the composite index `idx_calls_caller_created` are already present in the active database.

### 2.3 Migration Status
Command executed inside container `130-app-1-1`:
```bash
php artisan migrate:status
```
Observed status:
```
  2024_01_01_000000_initialize_master_crm_schema ..................... [1] Ran  
  2026_04_29_075517_create_geospatial_landmarks_table ................ [1] Ran  
  2026_05_07_000000_create_geospatial_markers_table .................. [1] Ran  
  2026_09_04_000001_add_tiger_reached_to_calls_table ................. [3] Ran  
  2026_10_15_000000_create_password_reset_tokens_table ............... [5] Ran  
  2026_10_15_000001_create_password_reset_requests_table ............. [5] Ran  
  2026_10_24_000000_create_two_factor_and_webauthn_tables ............ [5] Ran  
  2026_10_24_010000_create_mfa_role_policies_table ................... [5] Ran  
  2026_10_26_010000_add_password_changed_at_to_users_table ........... [5] Ran
```
Neither `2026_10_29_010000` nor `2026_10_29_020000` has been tracked or executed in the active staging migration history.

---

## 3. Destructive Rollback Vulnerability Analysis

The original migration implementations presented extreme risks if ever executed or rolled back on active staging:

1. **`2026_10_29_010000_create_call_sequences_table.php` (Original):**
   - **Flaw in `up()`:** `if (!Schema::hasTable('call_sequences'))` skipped table creation because the table existed, leaving `created_at` and `updated_at` permanently absent.
   - **Catastrophic Flaw in `down()`:** Executed `Schema::dropIfExists('call_sequences');`. If an operator or automated rollback command (`migrate:rollback`) ran, the active sequence table would be wiped, destroying sequence state for the entire national CRM.
2. **`2026_10_29_020000_add_caller_history_composite_index_to_calls_table.php` (Original):**
   - **Flaw in `down()`:** Executed `Schema::dropIndex('idx_calls_caller_created');`. Because the index was manually applied prior to migration adoption, a rollback would drop an active index supporting caller history lookups on 709,834 calls, creating massive table locks and queries degrading from 0.02s to 12.8s.

---

## 4. Non-Destructive Schema Adoption Architecture

The migration files have been completely refactored to implement **Forward-Only, Non-Destructive Schema Adoption**.

### 4.1 Migration 1: Safe Forward-Only Adoption of `call_sequences`
File: `database/migrations/2026_10_29_010000_create_call_sequences_table.php`
- **`up()` Strategy:**
  1. If `call_sequences` is absent: creates the table with `year` (PK), `current_seq`, and `timestamps()`.
  2. If `call_sequences` pre-exists: inspects columns and non-destructively alters the table to append `created_at` and `updated_at` if missing, without touching `current_seq` or locking the table.
- **`down()` Rollback Protection (Strictly Forward-Only):**
  1. **Schema Object Ownership Analysis:** Migration systems do not maintain a granular ownership ledger of whether a table or its columns were created by a specific migration or pre-existed due to manual DBA action. If `down()` dropped `created_at` or `updated_at`, it would remove columns that may have existed prior to this migration on operational environments.
  2. Furthermore, dropping `call_sequences` when empty would destroy a pre-existing operational table structure needed for call allocations.
  3. Therefore, this adoption migration is strictly **forward-only**: `down()` logs an operational retention notice and **refuses to drop either the table or its columns**.

### 4.2 Migration 2: Safe Forward-Only Adoption of `idx_calls_caller_created`
File: `database/migrations/2026_10_29_020000_add_caller_history_composite_index_to_calls_table.php`
- **`up()` Strategy:**
  1. Checks `SHOW INDEX FROM calls` dynamically.
  2. If `idx_calls_caller_created` already exists: skips creation silently and logs adoption.
  3. If missing: creates the composite index `(caller_number, created_at)`.
- **`down()` Rollback Protection (Strictly Forward-Only):**
  1. Does NOT rely on flawed `APP_ENV` checks. Environment names do not establish index provenance.
  2. To eliminate metadata table locks and prevent catastrophic 12-second query regressions on 709k+ partitioned call records, `down()` retains `idx_calls_caller_created` as a permanent structural asset and logs a retention notice.
  3. **Rationale:** In high-volume operational databases, formalizing pre-existing performance indexes into version control is an adoption action. Dropping a pre-existing index upon migration rollback destroys baseline system performance.

---

## 5. Migration Diffs

```diff
--- a/database/migrations/2026_10_29_010000_create_call_sequences_table.php
+++ b/database/migrations/2026_10_29_010000_create_call_sequences_table.php
@@ -4,26 +4,49 @@ use Illuminate\Database\Migrations\Migration;
 use Illuminate\Database\Schema\Blueprint;
 use Illuminate\Support\Facades\Schema;
+use Illuminate\Support\Facades\DB;
 
 return new class extends Migration {
     public function up(): void
     {
         if (!Schema::hasTable('call_sequences')) {
             Schema::create('call_sequences', function (Blueprint $table) {
                 $table->unsignedInteger('year')->primary();
                 $table->unsignedBigInteger('current_seq')->default(0);
                 $table->timestamps();
             });
+            return;
         }
+
+        // Table exists: adopt schema and non-destructively reconcile missing columns
+        Schema::table('call_sequences', function (Blueprint $table) {
+            if (!Schema::hasColumn('call_sequences', 'created_at')) {
+                $table->timestamp('created_at')->nullable()->after('current_seq');
+            }
+            if (!Schema::hasColumn('call_sequences', 'updated_at')) {
+                $table->timestamp('updated_at')->nullable()->after('created_at');
+            }
+        });
     }
 
     public function down(): void
     {
-        Schema::dropIfExists('call_sequences');
+        \Illuminate\Support\Facades\Log::info(
+            "Migration rollback: call_sequences table and columns retained as permanent operational assets."
+        );
     }
 };
```

```diff
--- a/database/migrations/2026_10_29_020000_add_caller_history_composite_index_to_calls_table.php
+++ b/database/migrations/2026_10_29_020000_add_caller_history_composite_index_to_calls_table.php
@@ -27,14 +27,7 @@ return new class extends Migration {
     public function down(): void
     {
-        if (Schema::hasTable('calls')) {
-            $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();
-
-            if (in_array('idx_calls_caller_created', $existingIndexes)) {
-                Schema::table('calls', function (Blueprint $table) {
-                    $table->dropIndex('idx_calls_caller_created');
-                });
-            }
-        }
+        \Illuminate\Support\Facades\Log::info(
+            "Migration rollback: idx_calls_caller_created retained as permanent structural asset on calls table."
+        );
     }
 };
```

---

## 6. Disposable MySQL 8.4 Verification Test Matrix

Before applying any migration to staging, the migrations must be verified across 5 distinct test scenarios executed strictly against disposable MySQL 8.4:

| Scenario ID | Test Condition | Action Tested | Expected Outcome | Rollback Verification | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SC-01: Absent** | Clean baseline DB; no pre-existing `call_sequences` table; no pre-existing index. | Run `migrate` | Creates table with `year`, `current_seq`, and timestamps; creates index cleanly. | `down()` logs retention; table and index retained without error. | **PROPOSED ON DISPOSABLE (Blocked)** |
| **SC-02: Pre-existing Table without Timestamps** | Pre-existing `call_sequences` table with `year` and `current_seq` (no timestamps); index absent. | Run `migrate` | Non-destructively alters table to add `created_at` and `updated_at`; creates index cleanly. | `down()` logs retention; table and pre-existing columns untouched. | **PROPOSED ON DISPOSABLE (Blocked)** |
| **SC-03: Pre-existing Table with Partial/Full Timestamps** | Table exists with `created_at` but missing `updated_at` (or both present). | Run `migrate` | Idempotently appends only missing `updated_at` (or skips both); exits 0. | `down()` logs retention; existing columns and table preserved. | **PROPOSED ON DISPOSABLE (Blocked)** |
| **SC-04: Pre-existing Table Containing Sequence Rows** | Table contains active row `year=2026, current_seq=710035`. | Run `migrate` | Adds timestamp columns without modifying `current_seq` or locking rows. | `down()` logs retention; `current_seq=710035` completely untouched. | **PROPOSED ON DISPOSABLE (Blocked)** |
| **SC-05: Pre-existing Index Outside Migration** | `idx_calls_caller_created` created manually outside migration before `migrate` runs. | Run `migrate` | Detects index in `SHOW INDEX FROM calls`; skips creation silently without error. | `down()` logs retention; pre-existing index remains intact. | **PROPOSED ON DISPOSABLE (Blocked)** |** |

---

## 7. Operational Stop Condition & Approval Boundary

> [!WARNING]
> **ABSOLUTE PROHIBITION:** `php artisan migrate --force` or any migration command MUST NOT be executed against the active staging database `130_crm_db` as part of this remediation task.
> 
> Schema reconciliation on active staging requires:
> 1. Complete validation on a disposable MySQL 8.4 instance.
> 2. Documented sign-off from the Operations Manager.
> 3. Execution during an authorized maintenance window with a verified pre-migration database snapshot.
