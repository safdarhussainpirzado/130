<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    const CALLS_TABLE = 'calls';
    const CALL_SUB_TYPE_COL = 'call_sub_type_id';
    const CALL_TYPE_COL = 'call_type_id';
    const VEHICLE_TYPE_COL = 'vehicle_type_id';

    /**
     * Run the migrations.
     * Maps legacy categories, vehicle types, and HEIRARCHICAL OFFICES with date sanitization.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::statement("SET SESSION sql_mode = '';");

        // 1. Move data from staging to real tables with OFFICE mapping and DATE sanitization
        $this->migrateCallsFromStaging();
        $this->migrateHistoryFromStaging();

        // 2. Backup Category Tables (for the mapping process)
        $this->backupOldTables();

        // 3. Build Category mappings and update IDs
        $subMapping = $this->buildCallSubTypeMapping();
        $vehMapping = $this->buildVehicleTypeMapping();
        $this->updateCallsTable($subMapping, $vehMapping);

        // 4. Convert all timestamps from GMT to PKT (GMT+5)
        // This includes DDL (Trigger management) which causes implicit commits
        $this->convertTimestampsToPkt();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function migrateCallsFromStaging(): void
    {
        if (!Schema::hasTable('legacy_calls')) {
            return;
        }

        $this->command()->info('Migrating calls from staging (Sanitizing 0000-00-00 dates)...');

        // Map beat_id to new office_id by matching NAMES between legacy_beats and new offices
        // Also use NULLIF to handle '0000-00-00 00:00:00' values that trigger Strict Mode errors
        DB::statement("
            INSERT INTO calls (
                id, call_number, agent_id, caller_number, caller_name, is_reminder_call, 
                call_reminder_count, last_reminder_at, parent_call_id, call_type_id, 
                call_sub_type_id, details, location_details, language_id, is_phone_activity, 
                vehicle_type_id, vehicle_no, carriageway_id, km_marker_text, office_id, 
                caller_lat, caller_lng, status, priority, forwarded_to_level, 
                forwarded_to_user_id, rating, call_start_time, call_pickup_time, 
                call_end_time, wait_time_seconds, agent_call_duration, response_time_sec, 
                resolution_time_sec, pending_remarks, pending_status_by, inprogress_remarks, 
                inprogress_status_by, inprogress_at, completed_remarks, completed_status_by, 
                completed_at, cancelled_remarks, followup_needed, created_at, updated_at
            )
            SELECT 
                lc.id, lc.call_number, lc.agent_id, lc.caller_number, lc.caller_name, lc.is_reminder_call,
                lc.call_reminder_count, 
                NULLIF(lc.last_reminder_at, '0000-00-00 00:00:00'), 
                lc.parent_call_id, lc.call_type_id,
                lc.call_sub_type_id, lc.details, lc.location_details, lc.language_id, lc.is_phone_activity,
                lc.vehicle_type_id, lc.vehicle_no, lc.carriageway_id, lc.km_marker_text,
                (SELECT id FROM offices WHERE name = (SELECT name FROM legacy_beats WHERE id = lc.beat_id LIMIT 1) AND type = 'beat' LIMIT 1),
                lc.caller_lat, lc.caller_lng, lc.status, lc.priority, lc.forwarded_to_level,
                lc.forwarded_to_user_id, lc.rating, 
                NULLIF(lc.call_start_time, '0000-00-00 00:00:00'), 
                NULLIF(lc.call_pickup_time, '0000-00-00 00:00:00'), 
                NULLIF(lc.call_end_time, '0000-00-00 00:00:00'), 
                lc.wait_time_seconds, lc.agent_call_duration, lc.response_time_sec,
                lc.resolution_time_sec, lc.pending_remarks, lc.pending_status_by, lc.inprogress_remarks,
                lc.inprogress_status_by, 
                NULLIF(lc.inprogress_at, '0000-00-00 00:00:00'), 
                lc.completed_remarks, lc.completed_status_by, 
                NULLIF(lc.completed_at, '0000-00-00 00:00:00'), 
                lc.cancelled_remarks, lc.followup_needed, 
                NULLIF(lc.created_at, '0000-00-00 00:00:00'), 
                NULLIF(lc.updated_at, '0000-00-00 00:00:00')
            FROM legacy_calls lc
        ");
    }

    private function migrateHistoryFromStaging(): void
    {
        if (Schema::hasTable('legacy_call_status_history')) {
            $this->command()->info('Migrating history from staging (Sanitizing dates)...');
            DB::statement("
                INSERT IGNORE INTO call_status_history (id, call_id, changed_by, old_status, new_status, remarks, officer_id, created_at)
                SELECT id, call_id, changed_by, old_status, new_status, remarks, officer_id, NULLIF(created_at, '0000-00-00 00:00:00')
                FROM legacy_call_status_history
            ");
        }
    }

    private function backupOldTables(): void
    {
        $tables = ['call_types', 'call_sub_types', 'vehicle_types'];
        foreach ($tables as $table) {
            $backup = $table . '_backup';
            if (!Schema::hasTable($backup)) {
                DB::statement("CREATE TABLE $backup LIKE $table");
                DB::statement("INSERT $backup SELECT * FROM $table");
            }
        }
    }

    private function buildCallSubTypeMapping(): array
    {
        $nameOverrides = [
            'Radiator Issue'        => 'Radiator Problem',
            'Tools Required'        => 'Tool Assistance',
            'Tyre Burst / Puncture' => 'Tyre Burst',
            'Overheating'           => 'Engine Overheating',
            'Officer Misbehavior'   => 'Officer Misconduct',
            'Road Closure'          => 'Road Blockade',
            'Weather / Fog'         => 'Weather Conditions',
            'Route / Direction'     => 'Route Information',
            'Motorway Status'       => 'Road Condition',
            'Animal on Road'        => 'Animal Crossing',
            'Over-speeding'         => 'Overspeeding',
        ];

        $movedToComplaint = ['Animal on Road', 'Road Closure', 'Lane Violation', 'Misbehave', 'Reckless Driving'];

        $mapping = [];
        $complaintTypeId = DB::table('call_types')->where('name', 'Complaint')->value('id');

        $oldSubs = DB::table('call_sub_types_backup as os')
            ->join('call_types_backup as ct', 'ct.id', '=', 'os.call_type_id')
            ->select('os.id as old_id', 'os.name as old_name', 'ct.name as old_call_type')
            ->get();

        foreach ($oldSubs as $old) {
            $newName = $nameOverrides[$old->old_name] ?? $old->old_name;
            $query = DB::table('call_sub_types')->where('name', $newName);

            if (in_array($old->old_name, $movedToComplaint)) {
                $query = $query->where('call_type_id', $complaintTypeId);
            }

            $newId = $query->value('id');
            if ($newId) {
                $mapping[$old->old_id] = [
                    'new_id'        => $newId,
                    'old_call_type' => $old->old_call_type,
                ];
            }
        }
        return $mapping;
    }

    private function buildVehicleTypeMapping(): array
    {
        $mapping = [];
        $oldVehicles = DB::table('vehicle_types_backup')->get();
        foreach ($oldVehicles as $old) {
            $newId = DB::table('vehicle_types')->where('name', $old->name)->value('id');
            if ($newId) {
                $mapping[$old->id] = $newId;
            }
        }
        return $mapping;
    }

    private function updateCallsTable(array $subMapping, array $vehMapping): void
    {
        if (!Schema::hasTable(self::CALLS_TABLE)) return;

        $complaintTypeId = DB::table('call_types')->where('name', 'Complaint')->value('id');

        foreach ($subMapping as $oldId => $data) {
            $newId = $data['new_id'];
            $newParentId = DB::table('call_sub_types')->where('id', $newId)->value('call_type_id');
            $updateData = [self::CALL_SUB_TYPE_COL => $newId];

            if ($newParentId == $complaintTypeId && Schema::hasColumn(self::CALLS_TABLE, self::CALL_TYPE_COL)) {
                $updateData[self::CALL_TYPE_COL] = $complaintTypeId;
            }

            DB::table(self::CALLS_TABLE)->where(self::CALL_SUB_TYPE_COL, $oldId)->update($updateData);
        }

        if (Schema::hasColumn(self::CALLS_TABLE, self::VEHICLE_TYPE_COL)) {
            foreach ($vehMapping as $oldId => $newId) {
                DB::table(self::CALLS_TABLE)->where(self::VEHICLE_TYPE_COL, $oldId)->update([self::VEHICLE_TYPE_COL => $newId]);
            }
        }
    }

    private function convertTimestampsToPkt(): void
    {
        $this->command()->warn('Converting legacy timestamps from GMT to PKT (GMT+5)...');

        // Temporarily drop triggers that prevent updates on audit_logs
        DB::statement("DROP TRIGGER IF EXISTS audit_logs_no_update");
        DB::statement("DROP TRIGGER IF EXISTS audit_logs_no_delete");

        $tables = [
            'calls' => ['created_at', 'updated_at', 'last_reminder_at', 'call_start_time', 'call_pickup_time', 'call_end_time', 'inprogress_at', 'completed_at'],
            'users' => ['created_at', 'updated_at', 'last_login_at', 'locked_until'],
            'audit_logs' => ['created_at'],
            'login_logs' => ['login_at', 'logout_at'],
            'outgoing_calls' => ['called_at'],
            'notifications' => ['created_at', 'read_at'],
            'call_status_history' => ['created_at'],
            'user_scopes' => ['created_at', 'updated_at']
        ];

        foreach ($tables as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            $updateParts = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $updateParts[] = "{$column} = CASE WHEN {$column} IS NOT NULL THEN DATE_ADD({$column}, INTERVAL 5 HOUR) ELSE NULL END";
                }
            }
            if (!empty($updateParts)) {
                DB::statement("UPDATE {$table} SET " . implode(', ', $updateParts));
            }
        }

        // Recreate triggers to maintain append-only integrity
        DB::unprepared("
            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: UPDATE not permitted';
            END
        ");
        DB::unprepared("
            CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: DELETE not permitted';
            END
        ");
    }

    public function down(): void {}
    
    private function command() {
        return new class {
            public function info($msg) { echo "\033[32m" . $msg . "\033[0m\n"; }
            public function warn($msg) { echo "\033[33m" . $msg . "\033[0m\n"; }
        };
    }
};
