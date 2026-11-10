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
        // 1. Seed Hamsafar Roles if not present
        $now = now();
        $rolesToInsert = [
            [
                'name'         => 'patrol_officer',
                'display_name' => 'Patrol Officer',
                'status'       => 'active',
                'scope_level'  => 'beat',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'commuter',
                'display_name' => 'Commuter / Road User',
                'status'       => 'active',
                'scope_level'  => 'national',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];

        foreach ($rolesToInsert as $role) {
            if (! DB::table('roles')->where('name', $role['name'])->exists()) {
                DB::table('roles')->insert($role);
            }
        }

        // 2. Commuter Profiles Table
        Schema::create('commuter_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('google_id')->nullable()->index();
            $table->text('avatar_url')->nullable();
            $table->enum('gender', ['female', 'male', 'prefer_not_to_say'])->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->boolean('is_cnic_verified')->default(false);
            $table->json('emergency_contacts')->nullable();
            $table->json('vehicle_profiles')->nullable();
            $table->json('saved_routes')->nullable();
            $table->string('preferred_language', 10)->default('ur');
            $table->text('device_fcm_token')->nullable();
            $table->timestamps();
        });

        // 3. Patrol Officer Profiles Table
        Schema::create('patrol_officer_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('badge_number', 50)->nullable()->index();
            $table->foreignId('assigned_beat_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('device_uuid')->nullable()->index();
            $table->text('device_fcm_token')->nullable();
            $table->boolean('is_on_duty')->default(false)->index();
            $table->uuid('current_duty_session_id')->nullable()->index();
            $table->timestamps();
        });

        // 4. Patrol Duty Sessions Table
        Schema::create('patrol_duty_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('officer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assisting_officer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('beat_office_id')->constrained('offices')->cascadeOnDelete();
            $table->string('vehicle_callsign', 50)->index();
            $table->string('vehicle_reg_no', 50)->nullable();
            $table->enum('status', ['AVAILABLE', 'BUSY', 'OFF_DUTY', 'EN_ROUTE', 'AT_SCENE'])->default('AVAILABLE')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->decimal('initial_latitude', 10, 7)->nullable();
            $table->decimal('initial_longitude', 10, 7)->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_speed_kph', 5, 2)->nullable();
            $table->decimal('last_bearing_degrees', 5, 2)->nullable();
            $table->string('last_carriageway', 100)->nullable();
            $table->timestamp('last_telemetry_at')->nullable()->index();
            $table->timestamps();

            // Composite index for rapid beat-level telemetry lookups
            $table->index(['beat_office_id', 'status', 'last_telemetry_at'], 'idx_beat_status_telemetry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_duty_sessions');
        Schema::dropIfExists('patrol_officer_profiles');
        Schema::dropIfExists('commuter_profiles');
        DB::table('roles')->whereIn('name', ['patrol_officer', 'commuter'])->delete();
    }
};
