<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('role_id')->index();
            }
            if (!Schema::hasColumn('users', 'unblock_token')) {
                $table->string('unblock_token', 128)->nullable()->after('is_blocked')->index();
            }
            if (!Schema::hasColumn('users', 'unblock_token_expires_at')) {
                $table->timestamp('unblock_token_expires_at')->nullable()->after('unblock_token');
            }
        });

        // Initialize is_blocked from legacy is_active if present
        if (Schema::hasColumn('users', 'is_active')) {
            DB::table('users')->where('is_active', false)->update(['is_blocked' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'unblock_token_expires_at')) {
                $table->dropColumn('unblock_token_expires_at');
            }
            if (Schema::hasColumn('users', 'unblock_token')) {
                $table->dropColumn('unblock_token');
            }
            if (Schema::hasColumn('users', 'is_blocked')) {
                $table->dropColumn('is_blocked');
            }
        });
    }
};
