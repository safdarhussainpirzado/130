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
        // 1. Deactivate Emergency Assistance (ID 7) and Women's Safety Emergency (ID 10)
        DB::table('call_types')
            ->whereIn('id', [7, 10])
            ->orWhereIn('name', ['Emergency Assistance', "Women's Safety Emergency"])
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        // 2. Also deactivate their sub-types if any
        DB::table('call_sub_types')
            ->whereIn('call_type_id', [7, 10])
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        // 3. Re-assign existing calls using ID 7 (Emergency Assistance) to ID 1 (Emergency)
        DB::table('calls')
            ->where('call_type_id', 7)
            ->update([
                'call_type_id' => 1,
                'updated_at' => now(),
            ]);

        // 4. Re-assign existing calls using ID 10 (Women's Safety Emergency) to ID 1 (Emergency)
        // ensuring caller_gender is preserved as 'female' for Women Safety filtering
        DB::table('calls')
            ->where('call_type_id', 10)
            ->update([
                'call_type_id' => 1,
                'caller_gender' => 'female',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('call_types')
            ->whereIn('id', [7, 10])
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
