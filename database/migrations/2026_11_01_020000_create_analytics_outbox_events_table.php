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
        if (Schema::hasTable('analytics_outbox_events')) {
            return;
        }

        Schema::create('analytics_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_uuid', 36)->unique();
            $table->unsignedBigInteger('call_id');
            $table->string('event_type', 50); // call_created, status_changed, call_reassigned
            $table->date('stat_date');
            $table->unsignedTinyInteger('stat_hour');
            $table->unsignedInteger('old_zone_id')->default(0);
            $table->unsignedInteger('old_sector_id')->default(0);
            $table->unsignedInteger('old_beat_id')->default(0);
            $table->unsignedInteger('new_zone_id')->default(0);
            $table->unsignedInteger('new_sector_id')->default(0);
            $table->unsignedInteger('new_beat_id')->default(0);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->unsignedSmallInteger('call_type_id')->default(0);
            $table->string('call_category', 50)->default('general');
            $table->unsignedTinyInteger('priority')->default(3);
            $table->unsignedInteger('response_time_sec')->nullable();
            $table->unsignedInteger('resolution_time_sec')->nullable();
            $table->boolean('is_sla_met')->nullable();
            $table->boolean('is_sla_eligible')->default(false);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processed, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for high-throughput poll worker and queue processing
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['call_id', 'created_at'], 'idx_call_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_outbox_events');
    }
};
