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
        // 1. Extend calls table status enum & add operational tracking fields
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE calls MODIFY COLUMN status ENUM('pending', 'assigned', 'acknowledged', 'in_progress', 'tiger_reached', 'review_pending', 'completed', 'cancelled', 'junk', 'forwarded') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('calls', function (Blueprint $table) {
            if (!Schema::hasColumn('calls', 'commuter_user_id')) {
                $table->foreignId('commuter_user_id')->nullable()->after('agent_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('calls', 'patrol_duty_session_id')) {
                $table->foreignId('patrol_duty_session_id')->nullable()->after('patrol_officer_id')->constrained('patrol_duty_sessions')->nullOnDelete();
            }
            if (!Schema::hasColumn('calls', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('patrol_duty_session_id');
            }
            if (!Schema::hasColumn('calls', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('calls', 'acknowledged_by')) {
                $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
            }
            if (!Schema::hasColumn('calls', 'cannot_respond_reason')) {
                $table->text('cannot_respond_reason')->nullable()->after('acknowledged_by');
            }
            if (!Schema::hasColumn('calls', 'public_summary')) {
                $table->text('public_summary')->nullable()->after('completed_remarks');
            }
            if (!Schema::hasColumn('calls', 'internal_control_note')) {
                $table->text('internal_control_note')->nullable()->after('public_summary');
            }
            if (!Schema::hasColumn('calls', 'completion_outcome')) {
                $table->string('completion_outcome', 50)->nullable()->after('internal_control_note');
            }
        });

        // 2. Create case_messages table for bidirectional communication
        if (!Schema::hasTable('case_messages')) {
            Schema::create('case_messages', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
                $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('sender_type', ['commuter', 'patrol_officer', 'control_operator'])->index();
                $table->enum('audience', ['shared_case', 'internal_control'])->default('shared_case')->index();
                $table->enum('message_type', ['text', 'location', 'image', 'voice_note'])->default('text');
                $table->text('content')->nullable();
                $table->text('media_url')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['call_id', 'audience', 'created_at'], 'idx_case_msg_feed');
            });
        }

        // 3. Create womens_journeys table (WJ-204)
        if (!Schema::hasTable('womens_journeys')) {
            Schema::create('womens_journeys', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('journey_code', 30)->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('origin_city', 100);
                $table->string('origin_stop', 150);
                $table->string('destination_city', 100);
                $table->string('destination_stop', 150);
                $table->string('psv_company_name', 150);
                $table->string('psv_reg_no', 50);
                $table->string('psv_seat_no', 20)->nullable();
                $table->json('emergency_contacts')->nullable();
                $table->enum('status', ['active', 'alert_triggered', 'completed', 'cancelled'])->default('active')->index();
                $table->decimal('last_latitude', 10, 7)->nullable();
                $table->decimal('last_longitude', 10, 7)->nullable();
                $table->timestamp('last_check_in_at')->nullable();
                $table->timestamp('discreet_alert_at')->nullable();
                $table->foreignId('desk_operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'idx_wj_user_status');
            });
        }

        // 4. Create womens_journey_updates table (Notes & Milestones)
        if (!Schema::hasTable('womens_journey_updates')) {
            Schema::create('womens_journey_updates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('womens_journey_id')->constrained('womens_journeys')->cascadeOnDelete();
                $table->enum('update_type', ['check_in', 'note', 'silent_alert', 'safe_arrival'])->index();
                $table->text('content')->nullable();
                $table->string('location_name', 150)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['womens_journey_id', 'created_at'], 'idx_wj_updates_feed');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('womens_journey_updates');
        Schema::dropIfExists('womens_journeys');
        Schema::dropIfExists('case_messages');

        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['commuter_user_id']);
            $table->dropForeign(['patrol_duty_session_id']);
            $table->dropColumn([
                'commuter_user_id',
                'patrol_duty_session_id',
                'assigned_at',
                'acknowledged_at',
                'acknowledged_by',
                'cannot_respond_reason',
                'public_summary',
                'internal_control_note',
                'completion_outcome',
            ]);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE calls MODIFY COLUMN status ENUM('pending', 'in_progress', 'tiger_reached', 'completed', 'cancelled', 'junk', 'forwarded') NOT NULL DEFAULT 'pending'");
        }
    }
};
