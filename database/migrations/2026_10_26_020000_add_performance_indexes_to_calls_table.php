<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

            if (!in_array('idx_calls_created_at', $existingIndexes)) {
                $table->index('created_at', 'idx_calls_created_at');
            }
            if (!in_array('idx_calls_status_created_at', $existingIndexes)) {
                $table->index(['status', 'created_at'], 'idx_calls_status_created_at');
            }
            if (!in_array('idx_calls_agent_status', $existingIndexes)) {
                $table->index(['agent_id', 'status'], 'idx_calls_agent_status');
            }
            if (!in_array('idx_calls_type_created', $existingIndexes)) {
                $table->index(['call_type_id', 'created_at'], 'idx_calls_type_created');
            }
            if (!in_array('idx_calls_office_created', $existingIndexes)) {
                $table->index(['office_id', 'created_at'], 'idx_calls_office_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

            if (in_array('idx_calls_created_at', $existingIndexes)) {
                $table->dropIndex('idx_calls_created_at');
            }
            if (in_array('idx_calls_status_created_at', $existingIndexes)) {
                $table->dropIndex('idx_calls_status_created_at');
            }
            if (in_array('idx_calls_agent_status', $existingIndexes)) {
                $table->dropIndex('idx_calls_agent_status');
            }
            if (in_array('idx_calls_type_created', $existingIndexes)) {
                $table->dropIndex('idx_calls_type_created');
            }
            if (in_array('idx_calls_office_created', $existingIndexes)) {
                $table->dropIndex('idx_calls_office_created');
            }
        });
    }
};
