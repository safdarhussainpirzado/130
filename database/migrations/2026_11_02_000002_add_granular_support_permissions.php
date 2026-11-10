<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'support.view',                          'display_name' => 'View Support Requests & Dashboard',             'module' => 'support', 'status' => 'active'],
            ['name' => 'support.create',                        'display_name' => 'Submit New Support Requests',                   'module' => 'support', 'status' => 'active'],
            ['name' => 'support.cancel',                        'display_name' => 'Cancel Own Pending Support Requests',           'module' => 'support', 'status' => 'active'],
            ['name' => 'support.transfer_apply',               'display_name' => 'Apply for Organizational Transfer',              'module' => 'support', 'status' => 'active'],
            ['name' => 'support.transfer_verify_source',        'display_name' => 'Verify & Approve Source Stage of Transfer',      'module' => 'support', 'status' => 'active'],
            ['name' => 'support.transfer_confirm_destination', 'display_name' => 'Confirm Destination Arrival & Execute Transfer',  'module' => 'support', 'status' => 'active'],
            ['name' => 'support.transfer_approve',             'display_name' => 'Verify & Approve Organizational Transfers',      'module' => 'support', 'status' => 'active'],
            ['name' => 'support.account_lock',                 'display_name' => 'Request Emergency Account Lock',                 'module' => 'support', 'status' => 'active'],
            ['name' => 'support.account_unlock',               'display_name' => 'Authorize Identity & Unlock Blocked Accounts',    'module' => 'support', 'status' => 'active'],
            ['name' => 'support.password_reset_authorize',     'display_name' => 'Authorize Password Recovery & Issue Tokens',     'module' => 'support', 'status' => 'active'],
            ['name' => 'support.mfa_assist',                   'display_name' => 'Authorize MFA Reset & Enrollment Assistance',    'module' => 'support', 'status' => 'active'],
            ['name' => 'support.manage',                       'display_name' => 'Manage & Action Support Requests',               'module' => 'support', 'status' => 'active'],
            ['name' => 'support.analytics',                    'display_name' => 'View Support Analytics & SLA Metrics',           'module' => 'support', 'status' => 'active'],
        ];

        // 1. Insert permissions
        $permMap = [];
        foreach ($permissions as $perm) {
            $existing = DB::table('permissions')->where('name', $perm['name'])->first();
            if (!$existing) {
                $id = DB::table('permissions')->insertGetId(array_merge($perm, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
                $id = $existing->id;
                DB::table('permissions')->where('id', $id)->update([
                    'display_name' => $perm['display_name'],
                    'module'       => 'support',
                    'status'       => 'active',
                    'updated_at'   => $now,
                ]);
            }
            $permMap[$perm['name']] = $id;
        }

        // 2. Define role assignments
        // 1: super_admin, 2: operation_admin, 3: zone_admin, 4: sector_admin, 5: beat_operator, 6: agent_supervisor, 7: agent
        $rolePermissions = [
            // Super Admin: All support permissions
            1 => array_values($permMap),

            // Operation Admin: All support permissions
            2 => array_values($permMap),

            // Zone Administrator:
            3 => [
                $permMap['support.view'],
                $permMap['support.create'],
                $permMap['support.cancel'],
                $permMap['support.transfer_apply'],
                $permMap['support.transfer_verify_source'],
                $permMap['support.transfer_confirm_destination'],
                $permMap['support.transfer_approve'],
                $permMap['support.account_lock'],
            ],

            // Sector Administrator:
            4 => [
                $permMap['support.view'],
                $permMap['support.create'],
                $permMap['support.cancel'],
                $permMap['support.transfer_apply'],
                $permMap['support.transfer_verify_source'],
                $permMap['support.transfer_confirm_destination'],
                $permMap['support.transfer_approve'],
                $permMap['support.account_lock'],
            ],

            // Beat Wireless Operator:
            5 => [
                $permMap['support.create'],
                $permMap['support.cancel'],
                $permMap['support.transfer_apply'],
                $permMap['support.transfer_confirm_destination'],
                $permMap['support.transfer_approve'],
                $permMap['support.account_lock'],
            ],

            // Call Center Supervisor:
            6 => [
                $permMap['support.create'],
                $permMap['support.cancel'],
                $permMap['support.transfer_apply'],
                $permMap['support.account_lock'],
            ],

            // Call Center Agent:
            7 => [
                $permMap['support.create'],
                $permMap['support.cancel'],
                $permMap['support.transfer_apply'],
                $permMap['support.account_lock'],
            ],
        ];

        foreach ($rolePermissions as $roleId => $pIds) {
            if (!DB::table('roles')->where('id', $roleId)->exists()) {
                continue;
            }
            foreach ($pIds as $pId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $pId]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $names = [
            'support.view',
            'support.create',
            'support.cancel',
            'support.transfer_apply',
            'support.transfer_verify_source',
            'support.transfer_confirm_destination',
            'support.transfer_approve',
            'support.account_lock',
            'support.account_unlock',
            'support.password_reset_authorize',
            'support.mfa_assist',
            'support.manage',
            'support.analytics',
        ];

        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
