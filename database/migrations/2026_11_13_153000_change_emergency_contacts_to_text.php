<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change emergency_contacts from JSON to TEXT on commuter_profiles and womens_journeys.
 *
 * This allows storing authenticated AES-256-GCM ciphertexts (GracefulEncrypted)
 * which are base64-encoded envelopes that MySQL's JSON validator rejects.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('commuter_profiles') && Schema::hasColumn('commuter_profiles', 'emergency_contacts')) {
            DB::statement("ALTER TABLE `commuter_profiles` MODIFY COLUMN `emergency_contacts` TEXT NULL");
        }

        if (Schema::hasTable('womens_journeys') && Schema::hasColumn('womens_journeys', 'emergency_contacts')) {
            DB::statement("ALTER TABLE `womens_journeys` MODIFY COLUMN `emergency_contacts` TEXT NULL");
        }
    }

    public function down(): void
    {
        // Forward-only retention policy: do not revert to JSON as ciphertext strings violate MySQL JSON validation.
    }
};
