<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements keyed HMAC blind indexes for encrypted user fields:
     * - email_bidx (exact email lookup)
     * - username_bidx (exact username lookup)
     * - user_search_tokens child table (tokenized name/prefix search)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_bidx')) {
                $table->string('email_bidx', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'username_bidx')) {
                $table->string('username_bidx', 64)->nullable()->after('username');
            }
        });

        $hasEmailIndex = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'idx_users_email_bidx'"))->isNotEmpty();
        if (! $hasEmailIndex) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email_bidx', 'idx_users_email_bidx');
            });
        }

        $hasUsernameIndex = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'idx_users_username_bidx'"))->isNotEmpty();
        if (! $hasUsernameIndex) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('username_bidx', 'idx_users_username_bidx');
            });
        }

        if (! Schema::hasTable('user_search_tokens')) {
            Schema::create('user_search_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_bidx', 64);
                $table->timestamps();

                $table->unique(['user_id', 'token_bidx'], 'uniq_user_token_bidx');
                $table->index('token_bidx', 'idx_user_search_tokens_bidx');
            });
        }
    }

    /**
     * Reverse the migrations (Forward-only schema safety).
     */
    public function down(): void
    {
        // Forward-only retention policy: Do not drop security search tables or index columns in production
    }
};
