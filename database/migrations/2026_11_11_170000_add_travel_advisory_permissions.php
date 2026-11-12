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
                'description'  => 'Permission to view active travel advisory grids and metrics.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.create',
                'display_name' => 'Log Travel Advisory / Fog',
                'module'       => 'travel_advisory',
                'description'  => 'Permission to log new corridor alerts, fog updates, and road works.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'travel_advisory.manage',
                'display_name' => 'Manage Travel Advisories',
                'module'       => 'travel_advisory',
                'description'  => 'Permission to resolve, extend, or terminate active corridor advisories.',
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

        // Attach to sector_admin and super_admin
        $permIds = DB::table('permissions')
            ->whereIn('name', ['travel_advisory.view', 'travel_advisory.create', 'travel_advisory.manage'])
            ->pluck('id', 'name');

        $roles = DB::table('roles')
            ->whereIn('name', ['sector_admin', 'super_admin'])
            ->get();

        foreach ($roles as $role) {
            foreach ($permIds as $permId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->whereIn('name', ['travel_advisory.view', 'travel_advisory.create', 'travel_advisory.manage'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();
    }
};
