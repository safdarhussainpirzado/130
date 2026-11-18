<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops advisory_type column and unifies advisory categorization under category.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tablet_advisories', 'advisory_type')) {
            // Attempt dropping the composite index on advisory_type first
            try {
                DB::statement("ALTER TABLE tablet_advisories DROP INDEX idx_adv_route_type_status");
            } catch (\Throwable $e) {
                // Index may already be dropped or not exist
            }

            Schema::table('tablet_advisories', function (Blueprint $table) {
                $table->dropColumn('advisory_type');
            });
        }

        // Broaden category column to standard VARCHAR(50) so it accepts all travel advisory types
        try {
            DB::statement("ALTER TABLE tablet_advisories MODIFY COLUMN category VARCHAR(50) NOT NULL DEFAULT 'advisory'");
        } catch (\Throwable $e) {
            // Fallback for non-MySQL drivers if any
        }

        // Add index on (route_name, category, status) for optimized high-concurrency feed lookups
        try {
            Schema::table('tablet_advisories', function (Blueprint $table) {
                $table->index(['route_name', 'category', 'status'], 'idx_adv_route_cat_status');
            });
        } catch (\Throwable $e) {
            // Index already present
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('tablet_advisories', function (Blueprint $table) {
                $table->dropIndex('idx_adv_route_cat_status');
            });
        } catch (\Throwable $e) {}

        if (!Schema::hasColumn('tablet_advisories', 'advisory_type')) {
            Schema::table('tablet_advisories', function (Blueprint $table) {
                $table->string('advisory_type', 30)->nullable()->after('category')->index();
                $table->index(['route_name', 'advisory_type', 'status'], 'idx_adv_route_type_status');
            });
        }
    }
};
