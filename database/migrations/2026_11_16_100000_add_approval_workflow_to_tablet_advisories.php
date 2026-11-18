<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modify status enum to include pending_approval, resolved, rejected
        DB::statement("ALTER TABLE tablet_advisories MODIFY COLUMN status ENUM('draft','pending_approval','published','withdrawn','expired','resolved','rejected') NOT NULL DEFAULT 'pending_approval'");

        // 2. Add approval tracking columns if not already present
        Schema::table('tablet_advisories', function (Blueprint $table) {
            if (!Schema::hasColumn('tablet_advisories', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('created_by')->index();
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('tablet_advisories', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('published_at');
            }

            if (!Schema::hasColumn('tablet_advisories', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablet_advisories', function (Blueprint $table) {
            if (Schema::hasColumn('tablet_advisories', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('tablet_advisories', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('tablet_advisories', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        DB::statement("ALTER TABLE tablet_advisories MODIFY COLUMN status ENUM('draft','published','withdrawn','expired') NOT NULL DEFAULT 'draft'");
    }
};
