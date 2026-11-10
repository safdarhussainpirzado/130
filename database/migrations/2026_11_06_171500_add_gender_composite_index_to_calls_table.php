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
            $table->index(['caller_gender', 'status', 'created_at'], 'idx_calls_gender_status_created');
            $table->index(['caller_gender', 'call_type_id'], 'idx_calls_gender_call_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('idx_calls_gender_status_created');
            $table->dropIndex('idx_calls_gender_call_type');
        });
    }
};
