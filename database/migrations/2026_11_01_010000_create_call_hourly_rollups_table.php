<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('call_hourly_rollups')) {
            return;
        }

        Schema::create('call_hourly_rollups', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->unsignedTinyInteger('stat_hour'); // 0 - 23
            $table->unsignedInteger('zone_id')->default(0);
            $table->unsignedInteger('sector_id')->default(0);
            $table->unsignedInteger('beat_id')->default(0);
            $table->unsignedSmallInteger('call_type_id')->default(0);
            $table->string('call_category', 50)->default('general');
            $table->unsignedTinyInteger('priority')->default(3);
            $table->string('status', 20)->default('completed');

            // Call Volume Counters
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('completed_calls')->default(0);
            $table->unsignedInteger('pending_calls')->default(0);

            // Metrics: Sums and Denominator Counts (Weighted Averages without Simpson's paradox)
            $table->unsignedBigInteger('sum_response_seconds')->default(0);
            $table->unsignedInteger('response_count')->default(0);
            $table->unsignedBigInteger('sum_resolution_seconds')->default(0);
            $table->unsignedInteger('resolution_count')->default(0);

            // SLA Performance Counters
            $table->unsignedInteger('sla_met_count')->default(0);
            $table->unsignedInteger('sla_eligible_count')->default(0);

            $table->timestamps();

            // Strict Unique Constraint across all dimensions for atomic UPSERTs
            $table->unique(
                ['stat_date', 'stat_hour', 'zone_id', 'sector_id', 'beat_id', 'call_type_id', 'priority', 'status'],
                'uk_rollup_dimension'
            );

            // Optimized composite query indexes
            $table->index(['stat_date', 'stat_hour'], 'idx_date_hour');
            $table->index(['zone_id', 'stat_date'], 'idx_zone_date');
            $table->index(['sector_id', 'stat_date'], 'idx_sector_date');
            $table->index(['beat_id', 'stat_date'], 'idx_beat_date');
            $table->index(['call_type_id', 'stat_date'], 'idx_calltype_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_hourly_rollups');
    }
};
