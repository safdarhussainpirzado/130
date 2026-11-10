<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (!Schema::hasColumn('calls', 'caller_gender')) {
                $table->string('caller_gender', 20)->nullable()->after('caller_name');
                $table->index('caller_gender', 'idx_calls_caller_gender');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (Schema::hasColumn('calls', 'caller_gender')) {
                $table->dropIndex('idx_calls_caller_gender');
                $table->dropColumn('caller_gender');
            }
        });
    }
};
