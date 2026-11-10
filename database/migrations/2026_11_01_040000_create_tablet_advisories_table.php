<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Schema for the high-concurrency Tablet Advisory Platform.
     */
    public function up(): void
    {
        Schema::create('tablet_advisories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 255);
            $table->enum('category', [
                'advisory',
                'weather',
                'road_closure',
                'congestion',
                'fog',
                'security',
                'maintenance',
                'emergency_alert'
            ])->default('advisory');
            $table->enum('severity', ['info', 'advisory', 'warning', 'critical'])->default('advisory');
            $table->string('route_name', 50)->index(); // M-1, M-2, M-5, N-5, etc.
            $table->enum('carriageway', ['northbound', 'southbound', 'both', 'all'])->default('both');
            $table->decimal('start_km', 8, 2)->nullable();
            $table->decimal('end_km', 8, 2)->nullable();
            $table->text('message');
            $table->enum('status', ['draft', 'published', 'withdrawn', 'expired'])->default('draft')->index();
            $table->boolean('is_emergency')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // High-concurrency composite indexes
            $table->index(['status', 'is_emergency', 'published_at'], 'idx_adv_active_emergency');
            $table->index(['route_name', 'status', 'published_at'], 'idx_adv_route_active');
            $table->index(['category', 'status', 'published_at'], 'idx_adv_category_active');

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablet_advisories');
    }
};
