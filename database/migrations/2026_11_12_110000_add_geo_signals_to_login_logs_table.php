<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Geo & Risk signals to login_logs table.
 *
 * Enforces ALGORITHM=INSTANT on MySQL 8.4 so adding metadata-only nullable columns
 * completes instantaneously without copying the table or locking the 1M+ row helpline dataset.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('login_logs', 'country')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE `login_logs`
                ADD COLUMN `country` VARCHAR(2) NULL,
                ADD COLUMN `asn` INT UNSIGNED NULL,
                ADD COLUMN `org` VARCHAR(255) NULL,
                ADD COLUMN `is_datacenter` TINYINT(1) NULL,
                ADD COLUMN `is_vpn_or_proxy` TINYINT(1) NULL,
                ADD COLUMN `is_tor` TINYINT(1) NULL,
                ADD COLUMN `geo_decision` VARCHAR(32) NULL,
                ADD COLUMN `risk_score` SMALLINT UNSIGNED NULL,
                ADD COLUMN `risk_reasons` JSON NULL,
                ADD COLUMN `device_hash` VARCHAR(64) NULL,
                ALGORITHM=INSTANT
            ");
        } else {
            Schema::table('login_logs', function (Blueprint $table) {
                $table->string('country', 2)->nullable();
                $table->unsignedInteger('asn')->nullable();
                $table->string('org', 255)->nullable();
                $table->boolean('is_datacenter')->nullable();
                $table->boolean('is_vpn_or_proxy')->nullable();
                $table->boolean('is_tor')->nullable();
                $table->string('geo_decision', 32)->nullable();
                $table->unsignedSmallInteger('risk_score')->nullable();
                $table->json('risk_reasons')->nullable();
                $table->string('device_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('login_logs', 'country')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE `login_logs`
                DROP COLUMN `country`,
                DROP COLUMN `asn`,
                DROP COLUMN `org`,
                DROP COLUMN `is_datacenter`,
                DROP COLUMN `is_vpn_or_proxy`,
                DROP COLUMN `is_tor`,
                DROP COLUMN `geo_decision`,
                DROP COLUMN `risk_score`,
                DROP COLUMN `risk_reasons`,
                DROP COLUMN `device_hash`,
                ALGORITHM=INSTANT
            ");
        } else {
            Schema::table('login_logs', function (Blueprint $table) {
                $table->dropColumn([
                    'country',
                    'asn',
                    'org',
                    'is_datacenter',
                    'is_vpn_or_proxy',
                    'is_tor',
                    'geo_decision',
                    'risk_score',
                    'risk_reasons',
                    'device_hash',
                ]);
            });
        }
    }
};
