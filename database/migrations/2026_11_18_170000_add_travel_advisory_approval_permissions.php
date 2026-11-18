<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $permissions = [
            [
                'name'         => 'travel_advisory.view',
                'display_name' => 'View Travel Advisories',
                'module'       => 'travel_advisory',
                'status'       => 'active',
                'description'  => 'Permission to view active travel advisory grids and metrics.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.create',
                'display_name' => 'Log Travel Advisory / Fog',
                'module'       => 'travel_advisory',
                'status'       => 'active',
                'description'  => 'Permission to log new corridor alerts, fog updates, and road works.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.manage',
                'display_name' => 'Manage Travel Advisories',
                'module'       => 'travel_advisory',
                'status'       => 'active',
                'description'  => 'Permission to resolve, extend, or update active corridor advisories.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.approve',
                'display_name' => 'Approve Travel Advisories',
                'module'       => 'travel_advisory',
                'status'       => 'active',
                'description'  => 'Permission for Call Center Supervisors and Admins to approve pending advisories and broadcast them live.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.reject',
                'display_name' => 'Reject Travel Advisories',
                'module'       => 'travel_advisory',
                'status'       => 'active',
                'description'  => 'Permission for Call Center Supervisors and Admins to reject inaccurate or duplicate travel advisories.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm['name']],
                $perm
            );
        }

        $allPermMap = DB::table('permissions')
            ->whereIn('name', [
                'travel_advisory.view',
                'travel_advisory.create',
                'travel_advisory.manage',
                'travel_advisory.approve',
                'travel_advisory.reject',
            ])
            ->pluck('id', 'name');

        // 1. Roles with full supervisor/admin permissions (view, create, manage, approve, reject)
        $supervisorRoles = DB::table('roles')
            ->whereIn('name', ['super_admin', 'agent_supervisor', 'operation_admin'])
            ->get();

        foreach ($supervisorRoles as $role) {
            foreach ($allPermMap as $permName => $permId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }

        // 2. Sector Admin: can view, create, manage (resolve/extend), but CANNOT approve or reject
        $sectorRoles = DB::table('roles')
            ->whereIn('name', ['sector_admin'])
            ->get();

        $sectorPermNames = ['travel_advisory.view', 'travel_advisory.create', 'travel_advisory.manage'];

        foreach ($sectorRoles as $role) {
            foreach ($sectorPermNames as $permName) {
                if (isset($allPermMap[$permName])) {
                    DB::table('role_permissions')->updateOrInsert([
                        'role_id'       => $role->id,
                        'permission_id' => $allPermMap[$permName],
                    ]);
                }
            }

            // Explicitly remove approve/reject if previously assigned
            $forbiddenPermIds = DB::table('permissions')
                ->whereIn('name', ['travel_advisory.approve', 'travel_advisory.reject'])
                ->pluck('id');

            DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $forbiddenPermIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->whereIn('name', ['travel_advisory.approve', 'travel_advisory.reject'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();
    }
};
