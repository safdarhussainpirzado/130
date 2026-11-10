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
        try {
            Schema::table('geospatial_markers', function (Blueprint $table) {
                $table->index(['lat', 'lng'], 'idx_markers_lat_lng');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('geospatial_markers', function (Blueprint $table) {
                $table->index('side', 'idx_markers_side');
            });
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('geospatial_markers', function (Blueprint $table) {
            $table->dropIndex('idx_markers_lat_lng');
            $table->dropIndex('idx_markers_side');
        });
    }
};
