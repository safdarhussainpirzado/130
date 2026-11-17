<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * City / coordinates / browser / OS / foreign flag on login_logs.
 * Metadata-only nullable columns: ALGORITHM=INSTANT on MySQL (no table copy, no lock on 1M+ rows).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('login_logs', 'city')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE `login_logs`
                ADD COLUMN `city` VARCHAR(120) NULL,
                ADD COLUMN `region` VARCHAR(120) NULL,
                ADD COLUMN `latitude` DECIMAL(9,6) NULL,
                ADD COLUMN `longitude` DECIMAL(9,6) NULL,
                ADD COLUMN `is_foreign` TINYINT(1) NULL,
                ADD COLUMN `browser` VARCHAR(40) NULL,
                ADD COLUMN `browser_version` VARCHAR(20) NULL,
                ADD COLUMN `os` VARCHAR(40) NULL,
                ADD COLUMN `os_version` VARCHAR(20) NULL,
                ADD COLUMN `device_type` VARCHAR(16) NULL,
                ALGORITHM=INSTANT
            ");
            return;
        }

        Schema::table('login_logs', function (Blueprint $table) {
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->boolean('is_foreign')->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('os_version', 20)->nullable();
            $table->string('device_type', 16)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('login_logs', 'city')) {
            return;
        }

        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropColumn(['city', 'region', 'latitude', 'longitude', 'is_foreign', 'browser', 'browser_version', 'os', 'os_version', 'device_type']);
        });
    }
};
