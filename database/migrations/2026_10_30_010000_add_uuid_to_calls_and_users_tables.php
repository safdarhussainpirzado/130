<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces UUIDv7 across Calls and Users tables for anti-enumeration and canonical routing.
     */
    public function up(): void
    {
        // 1. Add uuid column to users table if not exists
        if (!Schema::hasColumn('users', 'uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            // Backfill existing users with UUIDv7
            $users = DB::table('users')->whereNull('uuid')->select('id')->get();
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['uuid' => (string) Str::uuid7()]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->unique('uuid', 'users_uuid_unique');
            });
        }

        // 2. Add uuid column to calls table if not exists
        if (!Schema::hasColumn('calls', 'uuid')) {
            Schema::table('calls', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            // Backfill existing calls in chunks with UUIDv7
            $chunkSize = 1000;
            while (true) {
                $calls = DB::table('calls')
                    ->whereNull('uuid')
                    ->select('id')
                    ->limit($chunkSize)
                    ->get();

                if ($calls->isEmpty()) {
                    break;
                }

                $cases = [];
                $ids = [];
                foreach ($calls as $call) {
                    $uuid = (string) Str::uuid7();
                    $cases[] = "WHEN id = {$call->id} THEN '{$uuid}'";
                    $ids[] = $call->id;
                }

                $casesSql = implode(' ', $cases);
                $idsSql = implode(',', $ids);
                DB::statement("UPDATE calls SET uuid = CASE {$casesSql} END WHERE id IN ({$idsSql})");
            }

            Schema::table('calls', function (Blueprint $table) {
                $table->unique('uuid', 'calls_uuid_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('calls', 'uuid')) {
            Schema::table('calls', function (Blueprint $table) {
                $table->dropUnique('calls_uuid_unique');
                $table->dropColumn('uuid');
            });
        }

        if (Schema::hasColumn('users', 'uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_uuid_unique');
                $table->dropColumn('uuid');
            });
        }
    }
};
