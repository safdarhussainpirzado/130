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
        if (!Schema::hasTable('password_reset_requests')) {
            Schema::create('password_reset_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('requester_name');
                $table->string('requester_email')->nullable();
                $table->string('requester_username');
                $table->string('requester_role');
                $table->string('target_group')->default('operation_admin'); // who handles it
                $table->string('status')->default('pending'); // pending, completed, rejected
                $table->text('user_notes')->nullable();
                $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('actioned_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
