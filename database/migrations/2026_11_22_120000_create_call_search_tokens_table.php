<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keyed-HMAC search tokens for encrypted caller name / phone fragments (no plaintext, no FK on the huge calls table). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_search_tokens')) {
            Schema::create('call_search_tokens', function (Blueprint $t) {
                $t->char('token_bidx', 32);
                $t->unsignedBigInteger('call_id');
                $t->primary(['token_bidx', 'call_id']);
                $t->index('call_id', 'idx_call_search_tokens_call');
            });
        }
    }

    public function down(): void
    {
        // Forward-only.
    }
};
