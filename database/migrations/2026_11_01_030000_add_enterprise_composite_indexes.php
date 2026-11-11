<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * High-impact composite indexes identified during production slow-query analysis:
     * 1. calls(agent_id, call_number): eliminates backward table scan for agent directory
     * 2. calls(agent_id, created_at): accelerates agent date-filtered query scans
     * 3. calls(priority, status, created_at): optimizes Priority 1 dispatch desk filtering
     * 4. call_status_history(call_id, created_at): optimizes call history timeline loading
     * 5. login_logs(user_id, login_at): accelerates security audit log scans
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $existing = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique();
            
            if (!$existing->contains('idx_calls_agent_call_number')) {
                $table->index(['agent_id', 'call_number'], 'idx_calls_agent_call_number');
            }
            if (!$existing->contains('idx_calls_agent_created')) {
                $table->index(['agent_id', 'created_at'], 'idx_calls_agent_created');
            }
            if (!$existing->contains('idx_calls_priority_status_created')) {
                $table->index(['priority', 'status', 'created_at'], 'idx_calls_priority_status_created');
            }
        });

        Schema::table('call_status_history', function (Blueprint $table) {
            $existing = collect(DB::select("SHOW INDEX FROM call_status_history"))->pluck('Key_name')->unique();
            
            if (!$existing->contains('idx_csh_call_created')) {
                $table->index(['call_id', 'created_at'], 'idx_csh_call_created');
            }
        });

        Schema::table('login_logs', function (Blueprint $table) {
            $existing = collect(DB::select("SHOW INDEX FROM login_logs"))->pluck('Key_name')->unique();
            
            if (!$existing->contains('idx_login_logs_user_login_at')) {
                $table->index(['user_id', 'login_at'], 'idx_login_logs_user_login_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('idx_calls_agent_call_number');
            $table->dropIndex('idx_calls_agent_created');
            $table->dropIndex('idx_calls_priority_status_created');
        });

        Schema::table('call_status_history', function (Blueprint $table) {
            $table->dropIndex('idx_csh_call_created');
        });

        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropIndex('idx_login_logs_user_login_at');
        });
    }
};
