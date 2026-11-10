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
        if (Schema::hasTable('calls')) {
            $existingIndexes = collect(DB::select("SHOW INDEX FROM calls"))->pluck('Key_name')->unique()->all();

            if (!in_array('idx_calls_caller_created', $existingIndexes)) {
                Schema::table('calls', function (Blueprint $table) {
                    $table->index(['caller_number', 'created_at'], 'idx_calls_caller_created');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     * Non-destructive forward-only rollback protection:
     * idx_calls_caller_created is a permanent performance asset that predates migration tracking
     * on operational environments. To eliminate metadata table locks and prevent catastrophic
     * query regressions on 709k+ partitioned call records, this forward-only adoption migration
     * retains the index on rollback.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\Log::info(
            "Migration rollback: idx_calls_caller_created retained as permanent structural asset on calls table."
        );
    }
};
