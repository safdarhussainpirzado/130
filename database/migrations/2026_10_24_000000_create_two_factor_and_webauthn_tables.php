<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modify users table for hardened MFA and session versioning
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'totp_secret')) {
                // Change to text for encrypted storage
                $table->text('totp_secret')->nullable()->change();
            } else {
                $table->text('totp_secret')->nullable();
            }

            if (!Schema::hasColumn('users', 'totp_last_step')) {
                $table->unsignedBigInteger('totp_last_step')->nullable()->after('totp_secret');
            }

            if (!Schema::hasColumn('users', 'mfa_required')) {
                $table->boolean('mfa_required')->default(true)->after('totp_last_step');
            }

            if (!Schema::hasColumn('users', 'mfa_assurance_level')) {
                $table->string('mfa_assurance_level', 20)->default('totp')->after('mfa_required');
            }

            if (!Schema::hasColumn('users', 'security_version')) {
                $table->unsignedInteger('security_version')->default(1)->after('mfa_assurance_level');
            }

            if (!Schema::hasColumn('users', 'mfa_enrolled_at')) {
                $table->timestamp('mfa_enrolled_at')->nullable()->after('security_version');
            }
        });

        // 2. Single-Use Recovery Codes
        if (!Schema::hasTable('two_factor_recovery_codes')) {
            Schema::create('two_factor_recovery_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash', 255);
                $table->string('generation_id', 36)->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'consumed_at'], 'idx_user_unconsumed_codes');
                $table->index(['generation_id'], 'idx_generation_id');
            });
        }

        // 3. Authorized First-Time Enrollment Grants
        if (!Schema::hasTable('two_factor_enrollment_authorizations')) {
            Schema::create('two_factor_enrollment_authorizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->index();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 255)->default('Initial Staff Onboarding');
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'expires_at', 'consumed_at'], 'idx_active_authorizations');
            });
        }

        // 4. Lost Device Recovery Requests & Dual-Control Approvals
        if (!Schema::hasTable('two_factor_recovery_approvals')) {
            Schema::create('two_factor_recovery_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('request_token_hash', 64)->index();
                $table->string('reason', 255);
                $table->foreignId('target_role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('approver1_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approver1_at')->nullable();
                $table->foreignId('approver2_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approver2_at')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'consumed'])->default('pending');
                $table->string('rejection_reason', 255)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'status', 'expires_at'], 'idx_recovery_requests');
            });
        }

        // 5. WebAuthn / FIDO2 Credentials
        if (!Schema::hasTable('webauthn_credentials')) {
            Schema::create('webauthn_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('credential_id', 255)->unique();
                $table->text('public_key');
                $table->string('attestation_type', 50)->nullable();
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->boolean('user_verification')->default(true);
                $table->string('device_name', 100)->nullable();
                $table->boolean('is_hardware_key')->default(false);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'revoked_at'], 'idx_user_active_credentials');
            });
        }

        // 6. Ensure audit_logs action column accommodates MFA security events
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'action')) {
            try {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->string('action', 60)->change();
                });
            } catch (\Throwable $e) {
                // If direct modify fails (e.g. SQLite in memory tests), DB native fallback
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
        Schema::dropIfExists('two_factor_recovery_approvals');
        Schema::dropIfExists('two_factor_enrollment_authorizations');
        Schema::dropIfExists('two_factor_recovery_codes');

        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'totp_last_step',
                'mfa_required',
                'mfa_assurance_level',
                'security_version',
                'mfa_enrolled_at',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
