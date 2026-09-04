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
        // 1. Modify calls table status enum to include tiger_reached
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE calls MODIFY COLUMN status ENUM('pending', 'in_progress', 'tiger_reached', 'completed', 'cancelled', 'junk', 'forwarded') NOT NULL DEFAULT 'pending'");
        }

        // 2. Add tiger reached tracking & duration columns
        Schema::table('calls', function (Blueprint $table) {
            if (!Schema::hasColumn('calls', 'tiger_reached_at')) {
                $table->timestamp('tiger_reached_at')->nullable()->after('inprogress_at');
            }
            if (!Schema::hasColumn('calls', 'tiger_reached_status_by')) {
                $table->unsignedBigInteger('tiger_reached_status_by')->nullable()->after('tiger_reached_at');
            }
            if (!Schema::hasColumn('calls', 'tiger_reached_remarks')) {
                $table->text('tiger_reached_remarks')->nullable()->after('tiger_reached_status_by');
            }
            if (!Schema::hasColumn('calls', 'tiger_response_time_sec')) {
                $table->unsignedInteger('tiger_response_time_sec')->nullable()->after('response_time_sec');
            }
            if (!Schema::hasColumn('calls', 'assistance_duration_sec')) {
                $table->unsignedInteger('assistance_duration_sec')->nullable()->after('tiger_response_time_sec');
            }
            if (!Schema::hasColumn('calls', 'tiger_id')) {
                $table->string('tiger_id', 50)->nullable()->after('km_marker_text');
            }
            if (!Schema::hasColumn('calls', 'patrol_officer_id')) {
                $table->string('patrol_officer_id', 100)->nullable()->after('tiger_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'tiger_reached_at',
                'tiger_reached_status_by',
                'tiger_reached_remarks',
                'tiger_response_time_sec',
                'assistance_duration_sec',
                'tiger_id',
                'patrol_officer_id',
            ]);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE calls MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled', 'junk', 'forwarded') NOT NULL DEFAULT 'pending'");
        }
    }
};
