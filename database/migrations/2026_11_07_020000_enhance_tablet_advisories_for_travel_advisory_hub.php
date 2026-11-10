<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enriches tablet_advisories table with hierarchical operational units (Zone, Sector, Beat),
     * landmarks, visibility, speed limits, and spatial coordinates for the Humsafar Travel Advisory Hub.
     */
    public function up(): void
    {
        Schema::table('tablet_advisories', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->after('route_name')->index();
            $table->unsignedBigInteger('sector_id')->nullable()->after('office_id')->index();
            $table->unsignedBigInteger('zone_id')->nullable()->after('sector_id')->index();
            $table->unsignedBigInteger('carriageway_id')->nullable()->after('zone_id')->index();

            $table->string('start_landmark', 100)->nullable()->after('end_km');
            $table->string('end_landmark', 100)->nullable()->after('start_landmark');

            $table->unsignedSmallInteger('visibility_meters')->nullable()->after('end_landmark');
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable()->after('visibility_meters');
            $table->string('advisory_type', 30)->nullable()->after('category')->index(); // fog, road_closure, road_works, weather, traffic, diversion
            $table->string('reported_by_tier', 20)->default('beat')->after('created_by')->index(); // beat, sector, zone, central
            $table->string('corridor_key', 60)->nullable()->after('reported_by_tier')->index();

            $table->decimal('start_lat', 10, 7)->nullable()->after('corridor_key');
            $table->decimal('start_lng', 10, 7)->nullable()->after('start_lat');
            $table->decimal('end_lat', 10, 7)->nullable()->after('start_lng');
            $table->decimal('end_lng', 10, 7)->nullable()->after('end_lat');
            $table->string('closure_type', 30)->nullable()->after('end_lng'); // full, lane_1, lane_2, shoulder, diversion

            $table->foreign('office_id')->references('id')->on('offices')->onDelete('set null');
            $table->foreign('sector_id')->references('id')->on('offices')->onDelete('set null');
            $table->foreign('zone_id')->references('id')->on('offices')->onDelete('set null');
            $table->foreign('carriageway_id')->references('id')->on('carriageways')->onDelete('set null');

            $table->index(['route_name', 'advisory_type', 'status'], 'idx_adv_route_type_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablet_advisories', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['sector_id']);
            $table->dropForeign(['zone_id']);
            $table->dropForeign(['carriageway_id']);

            $table->dropIndex('idx_adv_route_type_status');
            $table->dropColumn([
                'office_id',
                'sector_id',
                'zone_id',
                'carriageway_id',
                'start_landmark',
                'end_landmark',
                'visibility_meters',
                'speed_limit_kmh',
                'advisory_type',
                'reported_by_tier',
                'corridor_key',
                'start_lat',
                'start_lng',
                'end_lat',
                'end_lng',
                'closure_type',
            ]);
        });
    }
};
