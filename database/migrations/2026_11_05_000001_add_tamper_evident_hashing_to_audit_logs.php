<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds cryptographic previous_hash and record_hash to audit_logs
     * to enforce a tamper-evident audit ledger (Merkle hash chaining).
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'previous_hash')) {
                $table->string('previous_hash', 64)->nullable()->after('user_agent')->index();
            }
            if (!Schema::hasColumn('audit_logs', 'record_hash')) {
                $table->string('record_hash', 64)->nullable()->after('previous_hash')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'record_hash')) {
                $table->dropColumn('record_hash');
            }
            if (Schema::hasColumn('audit_logs', 'previous_hash')) {
                $table->dropColumn('previous_hash');
            }
        });
    }
};
