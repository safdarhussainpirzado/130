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
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('device_type', 32)->default('web_crm');
                $table->string('provider', 32)->default('webpush');
                $table->string('token_hash', 64)->unique();
                $table->text('endpoint');
                $table->text('public_key')->nullable();
                $table->text('auth_token')->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index(['office_id', 'is_active']);
                $table->index(['provider', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
