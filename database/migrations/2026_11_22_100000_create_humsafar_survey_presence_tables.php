<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Humsafar Track B (staging only): survey catalogue/locations, officer presence + availability,
 * help-completion evidence, help feedback, commuter presence, and the humsafar.* permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_categories')) {
            Schema::create('survey_categories', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120)->unique();
                $t->string('icon', 60)->nullable();
                $t->string('description', 255)->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('survey_services')) {
            Schema::create('survey_services', function (Blueprint $t) {
                $t->id();
                $t->foreignId('category_id')->constrained('survey_categories')->cascadeOnDelete();
                $t->string('name', 120);
                $t->string('icon', 60)->nullable();
                $t->string('description', 255)->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['category_id', 'name']);
            });
        }
        if (! Schema::hasTable('survey_locations')) {
            Schema::create('survey_locations', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('category_id')->constrained('survey_categories');
                $t->string('name', 160);
                $t->text('address')->nullable();
                $t->double('lat', 12, 8);
                $t->double('lng', 12, 8);
                $t->json('phone_numbers')->nullable();
                $t->boolean('is_24h')->default(false);
                $t->time('opens_at')->nullable();
                $t->time('closes_at')->nullable();
                $t->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete(); // beat
                $t->foreignId('marker_id')->nullable()->constrained('geospatial_markers')->nullOnDelete();
                $t->decimal('km', 9, 2)->nullable();
                $t->string('carriageway', 40)->nullable();
                $t->string('direction', 2)->nullable();
                $t->string('geo_status', 24)->default('unmatched'); // matched|needs_verification|unmatched
                $t->string('status', 16)->default('draft');        // draft|submitted|approved|rejected
                $t->boolean('is_active')->default(true);
                $t->foreignId('surveyed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('reviewed_at')->nullable();
                $t->string('review_note', 255)->nullable();
                $t->timestamps();
                $t->index(['lat', 'lng'], 'idx_survey_loc_latlng');
                $t->index(['category_id', 'status'], 'idx_survey_loc_cat_status');
                $t->index(['office_id', 'status']);
            });
        }
        if (! Schema::hasTable('survey_location_service')) {
            Schema::create('survey_location_service', function (Blueprint $t) {
                $t->foreignId('survey_location_id')->constrained('survey_locations')->cascadeOnDelete();
                $t->foreignId('survey_service_id')->constrained('survey_services')->cascadeOnDelete();
                $t->primary(['survey_location_id', 'survey_service_id']);
            });
        }
        if (! Schema::hasTable('officer_presence')) {
            Schema::create('officer_presence', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $t->timestamp('last_seen_at')->nullable()->index();
                $t->double('last_lat', 12, 8)->nullable();
                $t->double('last_lng', 12, 8)->nullable();
                $t->string('app_version', 24)->nullable();
                $t->string('availability', 24)->default('available');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('officer_availability_log')) {
            Schema::create('officer_availability_log', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('status', 24);
                $t->string('reason', 255)->nullable();
                $t->timestamp('started_at');
                $t->timestamp('ended_at')->nullable();
                $t->unsignedInteger('duration_sec')->nullable();
                $t->timestamps();
                $t->index(['user_id', 'started_at']);
            });
        }
        if (! Schema::hasTable('call_evidence')) {
            Schema::create('call_evidence', function (Blueprint $t) {
                $t->id();
                $t->foreignId('call_id')->unique()->constrained('calls')->cascadeOnDelete();
                $t->string('path', 255);
                $t->string('mime', 40);
                $t->unsignedInteger('size_bytes');
                $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('uploaded_at');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('help_feedback')) {
            Schema::create('help_feedback', function (Blueprint $t) {
                $t->id();
                $t->foreignId('call_id')->unique()->constrained('calls')->cascadeOnDelete();
                $t->foreignId('commuter_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
                $t->unsignedTinyInteger('timeliness');
                $t->unsignedTinyInteger('behaviour');
                $t->unsignedTinyInteger('helpfulness');
                $t->unsignedTinyInteger('overall');
                $t->string('comment', 1000)->nullable();
                $t->timestamp('submitted_at');
                $t->timestamps();
                $t->index('officer_id');
            });
        }
        if (! Schema::hasTable('commuter_presence')) {
            Schema::create('commuter_presence', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $t->double('lat', 12, 8)->nullable();
                $t->double('lng', 12, 8)->nullable();
                $t->float('heading')->nullable();
                $t->float('speed')->nullable();
                $t->boolean('sharing')->default(true);
                $t->timestamp('last_seen_at')->nullable()->index();
                $t->timestamps();
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions(): void
    {
        $now = Carbon::now();
        $defs = [
            ['humsafar.view', 'Humsafar Section', 'View the Humsafar navigation section'],
            ['humsafar.survey.manage', 'Manage Survey Locations', 'Review, approve and reject survey locations; manage categories and services'],
            ['humsafar.officers.view', 'View Online Officers', 'View live officer presence and availability'],
            ['humsafar.commuters.view', 'View Commuter Directory', 'View the commuter directory (masked mobile numbers)'],
            ['humsafar.commuters.reveal', 'Reveal Commuter Mobile', 'Reveal a commuter full mobile number (audited)'],
            ['humsafar.commuters.monitor', 'Live Commuter Monitoring', 'View live commuters who share their location (audited)'],
            ['humsafar.feedback.view', 'View Help Feedback', 'View commuter help feedback and officer averages'],
        ];
        foreach ($defs as [$name, $display, $desc]) {
            DB::table('permissions')->updateOrInsert(['name' => $name], [
                'display_name' => $display, 'module' => 'humsafar', 'status' => 'active',
                'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $ids = DB::table('permissions')->where('module', 'humsafar')->pluck('id', 'name');
        $grant = [
            'super_admin' => $ids->keys()->all(),
            'operation_admin' => ['humsafar.view', 'humsafar.survey.manage', 'humsafar.officers.view', 'humsafar.feedback.view'],
            'agent_supervisor' => ['humsafar.view', 'humsafar.officers.view', 'humsafar.feedback.view'],
        ];
        foreach ($grant as $role => $names) {
            $rid = DB::table('roles')->where('name', $role)->value('id');
            if (! $rid) {
                continue;
            }
            foreach ($names as $n) {
                if (isset($ids[$n])) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $rid, 'permission_id' => $ids[$n]]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only (staging feature): never drop survey/presence data automatically.
    }
};
