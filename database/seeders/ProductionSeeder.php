<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ProductionSeeder extends Seeder
{
    /**
     * Run the production-ready structural database seeds.
     * Includes:
     * - NHMP Hierarchy (Regions, Zones, Sectors, Beats, Designations)
     * - Roles & Permissions
     * - Call Types, Sub-Types & Vehicles
     * - System Settings
     * - Single Super Admin (nhmp_admin)
     */
    public function run(): void
    {
        $this->command->info('Starting Production Seeder (Structural Only)...');
        $now = Carbon::now();

        // 1. NHMP Structure (Regions, Zones, Sectors, Beats, Designations, Carriageways)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->command->info('Starting NHMP Production-Ready Data Sync...');

        // 1. Geography (IG HQ -> Regions -> Zones -> Sectors -> Beats)
        $this->call(OfficeSeeder::class);

        // 2. Designations
        $this->call(DesignationSeeder::class);

        // 4. Operational Assets
        $this->call(CarriagewaySeeder::class);
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Roles & Permissions setup
        $this->call(RolesAndPermissionsSeeder::class);

        // 4. Call Types, Sub-Types & Vehicle Types (Categorized & Styled)
        $this->call(NHMPCategorySeeder::class);

        // 6. System Settings
        $this->seedSystemSettings();

        $this->command->info('Production Seeder completed successfully!');
    }

    private function seedSystemSettings(): void
    {
        $settings = [
            ['key_name' => 'app_name',              'value' => 'NHMP 130 Helpline CRM'],
            ['key_name' => 'dashboard_refresh_sec', 'value' => '30'],
            ['key_name' => 'alert_refresh_sec',     'value' => '15'],
            ['key_name' => 'session_timeout_agent', 'value' => '15'],
            ['key_name' => 'session_timeout_admin', 'value' => '15'],
            ['key_name' => 'sla_p1_minutes',        'value' => '10'],
            ['key_name' => 'call_edit_window_min',  'value' => '10'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $setting['key_name']],
                ['value' => $setting['value']]
            );
        }
    }
}
