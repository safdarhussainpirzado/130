<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mfa_role_policies')) {
            Schema::create('mfa_role_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
                $table->boolean('mfa_required')->default(true);
                $table->timestamp('scheduled_re_enable_at')->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // Seed initial role policies
        $roles = DB::table('roles')->get();
        $now = now();

        foreach ($roles as $role) {
            // Check if already seeded
            $exists = DB::table('mfa_role_policies')->where('role_id', $role->id)->exists();
            if ($exists) {
                continue;
            }

            // By default, the 3 operational wireless roles are temporarily not required
            // while individual wireless-operator identities are being arranged.
            // Privileged and other staff roles are enforced.
            $isExemptWireless = in_array($role->name, ['beat_operator', 'sector_admin', 'zone_admin'], true);

            DB::table('mfa_role_policies')->insert([
                'role_id'                => $role->id,
                'mfa_required'           => !$isExemptWireless,
                'scheduled_re_enable_at' => null,
                'reason'                 => $isExemptWireless
                    ? 'Initial rollout: wireless operator individual identities in arrangement'
                    : 'Standard enterprise MFA enforcement baseline',
                'updated_by'             => null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }

        // Seed permissions for viewing and managing MFA role policies
        $viewPermId = DB::table('permissions')->where('name', 'security.mfa_roles.view')->value('id');
        if (!$viewPermId) {
            $viewPermId = DB::table('permissions')->insertGetId([
                'name'         => 'security.mfa_roles.view',
                'display_name' => 'View Role MFA Policies',
                'module'       => 'security',
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        $managePermId = DB::table('permissions')->where('name', 'security.mfa_roles.manage')->value('id');
        if (!$managePermId) {
            $managePermId = DB::table('permissions')->insertGetId([
                'name'         => 'security.mfa_roles.manage',
                'display_name' => 'Manage Role MFA Policies',
                'module'       => 'security',
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Attach permissions to super_admin and operation_admin
        $privilegedRoleIds = DB::table('roles')
            ->whereIn('name', ['super_admin', 'operation_admin'])
            ->pluck('id');

        foreach ($privilegedRoleIds as $roleId) {
            foreach ([$viewPermId, $managePermId] as $permId) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_permissions')->insert([
                        'role_id'       => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_role_policies');

        $permIds = DB::table('permissions')
            ->whereIn('name', ['security.mfa_roles.view', 'security.mfa_roles.manage'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();
    }
};
