<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Safely reconciles schema drift:
     * 1. If table is missing, creates it with full schema including timestamps.
     * 2. If table pre-exists (as in active staging), adds missing timestamp columns non-destructively.
     */
    public function up(): void
    {
        if (!Schema::hasTable('call_sequences')) {
            Schema::create('call_sequences', function (Blueprint $table) {
                $table->unsignedInteger('year')->primary();
                $table->unsignedBigInteger('current_seq')->default(0);
                $table->timestamps();
            });
            return;
        }

        // Table exists: adopt schema and non-destructively reconcile missing columns
        Schema::table('call_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('call_sequences', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('current_seq');
            }
            if (!Schema::hasColumn('call_sequences', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     * Forward-only schema adoption:
     * call_sequences is a critical operational table that stores monotonic sequence numbers.
     * Because migration systems cannot determine whether this table or its columns were created
     * by this migration or pre-existed via manual deployment, this adoption migration is
     * strictly forward-only. It refuses to drop the table or columns on rollback to prevent
     * data loss and sequence corruption.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\Log::info(
            "Migration rollback: call_sequences table and columns retained as permanent operational assets."
        );
    }
};
