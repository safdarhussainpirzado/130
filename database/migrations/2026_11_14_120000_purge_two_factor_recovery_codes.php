<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Policy change: the system no longer issues or stores 2FA emergency recovery
 * codes. Remove every code hash that was already generated so none can be
 * redeemed (lost authenticators go through the dual-control recovery flow).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('two_factor_recovery_codes')) {
            DB::table('two_factor_recovery_codes')->delete();
        }
    }

    public function down(): void
    {
        // Deleted hashes cannot be restored (and must not be).
    }
};
