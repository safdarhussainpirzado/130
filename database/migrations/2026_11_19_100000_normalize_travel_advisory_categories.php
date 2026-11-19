<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * - Diversion is its own category (was road_works + closure_type = diversion).
 * - security / emergency_alert are no longer categories: such rows become generic advisories flagged is_emergency.
 * - Anything critical is always is_emergency.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('tablet_advisories')->where('closure_type', 'diversion')->where('category', '!=', 'diversion')
            ->update(['category' => 'diversion']);

        DB::table('tablet_advisories')->whereIn('category', ['security', 'emergency_alert'])
            ->update(['category' => 'advisory', 'is_emergency' => 1]);

        DB::table('tablet_advisories')->where('severity', 'critical')->where('is_emergency', 0)
            ->update(['is_emergency' => 1]);
    }

    public function down(): void
    {
        // Data normalisation only; not reversible (original categories are not retained).
    }
};
