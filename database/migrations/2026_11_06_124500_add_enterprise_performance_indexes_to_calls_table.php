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
        if (!Schema::hasTable('calls')) {
            return;
        }

        $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

        Schema::table('calls', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_calls_type_status_office_created', $existingIndexes, true)) {
                $table->index(['call_type_id', 'status', 'office_id', 'created_at', 'id'], 'idx_calls_type_status_office_created');
            }
            if (!in_array('idx_calls_office_created_id', $existingIndexes, true)) {
                $table->index(['office_id', 'created_at', 'id'], 'idx_calls_office_created_id');
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
            if (in_array('idx_calls_type_status_office_created', $existingIndexes, true)) {
                $table->dropIndex('idx_calls_type_status_office_created');
            }
            if (in_array('idx_calls_office_created_id', $existingIndexes, true)) {
                $table->dropIndex('idx_calls_office_created_id');
            }
        });
    }
};
