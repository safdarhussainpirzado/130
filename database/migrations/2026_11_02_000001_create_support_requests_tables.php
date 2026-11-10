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
        // 1. Support Requests Master Table
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 50)->index(); // password_recovery, mfa_support, account_block, org_transfer, other_account
            $table->string('category', 80)->nullable()->index();
            $table->string('subject', 255);
            $table->text('description');
            $table->string('current_status', 50)->default('submitted')->index(); // submitted, pending_source_approval, pending_destination_confirmation, pending_manager_review, approved, rejected, completed, cancelled, expired
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index();
            
            // Organizational hierarchy relations (nullable for account-level requests)
            $table->foreignId('source_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('destination_office_id')->nullable()->constrained('offices')->nullOnDelete();
            
            // Workflow assignment & metadata
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->json('metadata')->nullable();
            
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Composite indexes for fast query resolution on high traffic
            $table->index(['current_status', 'request_type']);
            $table->index(['source_office_id', 'current_status']);
            $table->index(['destination_office_id', 'current_status']);
            $table->index(['requester_id', 'created_at']);
        });

        // 2. Immutable Support Request Audit Actions
        Schema::create('support_request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')->constrained('support_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 60)->index(); // submitted, source_approved, source_rejected, destination_confirmed, destination_rejected, manager_approved, manager_rejected, cancelled, account_locked, account_unlocked, password_reset_issued
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->string('authentication_method', 50)->default('session'); // password, password_totp, webauthn, session
            $table->text('remarks')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['support_request_id', 'created_at']);
        });

        // 3. User Organizational Assignment History (Audit trail of transfers)
        Schema::create('user_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('previous_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('new_office_id')->constrained('offices')->cascadeOnDelete();
            $table->foreignId('transfer_request_id')->nullable()->constrained('support_requests')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'effective_at']);
        });

        // 4. Secure Password Recovery Single-Use Tokens
        Schema::create('password_recovery_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')->constrained('support_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // SHA-256 hash of 64-char high-entropy token
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'expires_at']);
        });

        // 5. Seed Support Management Permissions
        $now = now();
        $permissions = [
            ['name' => 'support.view', 'display_name' => 'View Support Requests', 'module' => 'support', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'support.manage', 'display_name' => 'Manage & Action Support Requests', 'module' => 'support', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'support.transfer_approve', 'display_name' => 'Verify & Approve Organizational Transfers', 'module' => 'support', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'support.analytics', 'display_name' => 'View Support Analytics', 'module' => 'support', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($permissions as $perm) {
            $existing = DB::table('permissions')->where('name', $perm['name'])->first();
            if (!$existing) {
                $id = DB::table('permissions')->insertGetId($perm);
            } else {
                $id = $existing->id;
            }

            // Assign to super_admin (1) and operation_admin (2) if roles exist
            foreach ([1, 2] as $roleId) {
                if (DB::table('roles')->where('id', $roleId)->exists()) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $id]
                    );
                }
            }

            // Assign support.transfer_approve to zone_admin (3), sector_admin (4), beat_operator (5) if roles exist
            if ($perm['name'] === 'support.transfer_approve') {
                foreach ([3, 4, 5] as $roleId) {
                    if (DB::table('roles')->where('id', $roleId)->exists()) {
                        DB::table('role_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $id]
                        );
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_recovery_tokens');
        Schema::dropIfExists('user_assignment_history');
        Schema::dropIfExists('support_request_actions');
        Schema::dropIfExists('support_requests');

        $permissionNames = ['support.view', 'support.manage', 'support.transfer_approve', 'support.analytics'];
        $permIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();
    }
};
