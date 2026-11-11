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
        Schema::table('geospatial_landmarks', function (Blueprint $table) {
            if (!Schema::hasColumn('geospatial_landmarks', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location_name');
            }
            if (!Schema::hasColumn('geospatial_landmarks', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            $table->index(['road_name', 'km_numeric'], 'idx_gl_road_km');
            $table->index(['latitude', 'longitude'], 'idx_gl_lat_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('geospatial_landmarks', function (Blueprint $table) {
            $table->dropIndex('idx_gl_road_km');
            $table->dropIndex('idx_gl_lat_lng');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
