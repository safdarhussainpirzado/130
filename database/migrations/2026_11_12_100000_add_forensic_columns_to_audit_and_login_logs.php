<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nullable columns added with ALGORITHM=INSTANT: metadata-only, no table copy, no write lock on the big tables.
return new class extends Migration {
    public function up(): void
    {
        foreach (['audit_logs', 'login_logs'] as $t) {
            if (! Schema::hasColumn($t, 'session_hash')) {
                DB::statement("ALTER TABLE `$t` ADD COLUMN `session_hash` CHAR(16) NULL, ADD COLUMN `request_id` CHAR(36) NULL, ADD COLUMN `node` VARCHAR(32) NULL, ALGORITHM=INSTANT");
            }
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'login_logs'] as $t) {
            DB::statement("ALTER TABLE `$t` DROP COLUMN `session_hash`, DROP COLUMN `request_id`, DROP COLUMN `node`, ALGORITHM=INSTANT");
        }
    }
};
