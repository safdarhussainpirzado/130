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
        Schema::create('geospatial_landmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete(); // The Beat ID
            $table->string('road_name')->index(); // e.g., M-1, M-2, N-5
            $table->string('bound_direction')->nullable(); // e.g., North, South
            $table->string('km_marker')->nullable(); // e.g., 504, 24-N
            $table->decimal('km_numeric', 8, 2)->nullable()->index(); // for sorting/ranging
            $table->string('zone_name')->nullable(); // Raw name from Excel
            $table->string('sector_name')->nullable(); // Raw name from Excel
            $table->string('beat_name')->nullable(); // Raw name from Excel
            $table->string('location_name')->nullable(); // e.g., "Jhari Kass Interchange"
            $table->string('nearby_cities')->nullable(); // e.g., "Haripur"
            $table->string('fuel_station')->nullable(); // e.g., "1 KM"
            $table->text('agent_prompt')->nullable(); // e.g., "Ask the commuter if they recently passed Jhari Kass Interchange or Haripur city."
            $table->string('contact_numbers')->nullable(); // Beat phone numbers
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geospatial_landmarks');
    }
};
