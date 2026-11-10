<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('calls')) {
            return;
        }

        $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

        Schema::table('calls', function (Blueprint $table) use ($existingIndexes) {
            // 1. Covering index for active calls, incident map, and queue monitoring
            if (!in_array('idx_calls_active_lookup', $existingIndexes)) {
                $table->index(['status', 'office_id', 'priority', 'created_at'], 'idx_calls_active_lookup');
            }

            // 2. Covering composite index for 7d/30d analytics and SLA aggregations
            if (!in_array('idx_calls_analytics_covering', $existingIndexes)) {
                $table->index(['created_at', 'office_id', 'call_type_id', 'call_sub_type_id', 'status'], 'idx_calls_analytics_covering');
            }

            // 3. Covering index for agent performance leaderboard
            if (!in_array('idx_calls_agent_perf', $existingIndexes)) {
                $table->index(['status', 'created_at', 'agent_id', 'call_sub_type_id'], 'idx_calls_agent_perf');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('calls')) {
            return;
        }

        $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

        Schema::table('calls', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('idx_calls_active_lookup', $existingIndexes)) {
                $table->dropIndex('idx_calls_active_lookup');
            }
            if (in_array('idx_calls_analytics_covering', $existingIndexes)) {
                $table->dropIndex('idx_calls_analytics_covering');
            }
            if (in_array('idx_calls_agent_perf', $existingIndexes)) {
                $table->dropIndex('idx_calls_agent_perf');
            }
        });
    }
};
