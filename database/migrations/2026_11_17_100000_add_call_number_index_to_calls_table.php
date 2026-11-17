<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Online index build (no table copy, no write lock): the Calls search and the CRM-... fast path had no index on call_number.
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::selectOne("select count(*) c from information_schema.statistics where table_schema = database() and table_name = 'calls' and index_name = 'idx_calls_call_number'")->c;
        if (! $exists) {
            DB::statement('ALTER TABLE `calls` ADD INDEX `idx_calls_call_number` (`call_number`), ALGORITHM=INPLACE, LOCK=NONE');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `calls` DROP INDEX `idx_calls_call_number`, ALGORITHM=INPLACE, LOCK=NONE');
    }
};
