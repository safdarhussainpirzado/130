<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CallService;
use App\Models\Call;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CallSequenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        User::firstOrCreate(['username' => 'test_operator_seq'], [
            'full_name' => 'Test Sequence Operator',
            'email' => 'test.seq@nhmp.gov.pk',
            'mobile_no' => '03001234567',
            'cnic' => '12345-1234567-1',
            'password' => Hash::make('Secret123!'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        DB::table('call_types')->insertOrIgnore([
            'id' => 1,
            'name' => 'Emergency Rescue',
            'category' => 'emergency',
            'priority' => 1,
            'color_hex' => '#E74C3C',
            'is_active' => 1,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test sequence years
        DB::table('call_sequences')->whereIn('year', [2027, 2029, 2030, 2031])->delete();
        DB::table('calls')->where('call_number', 'like', 'CRM-2029-%')
            ->orWhere('call_number', 'like', 'CRM-2030-%')
            ->orWhere('call_number', 'like', 'CRM-2031-%')->delete();
        parent::tearDown();
    }

    public function test_generate_call_number_format_and_sequence(): void
    {
        $service = new CallService();
        $year = (int) date('Y');

        $num1 = $service->generateCallNumber();
        $num2 = $service->generateCallNumber();

        $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $num1);
        $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $num2);

        $seq1 = (int) substr($num1, strrpos($num1, '-') + 1);
        $seq2 = (int) substr($num2, strrpos($num2, '-') + 1);

        $this->assertEquals($seq1 + 1, $seq2, "Subsequent call numbers must be strictly sequential.");
    }

    public function test_year_rollover_creates_independent_sequence_for_new_year(): void
    {
        $service = new CallService();
        $testYear = 2027;

        // Ensure test year is clean
        DB::table('call_sequences')->where('year', $testYear)->delete();

        // First call in new year must start at sequence 1
        $num1 = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00001", $num1);

        // Sequence row must be persisted in call_sequences table
        $row = DB::table('call_sequences')->where('year', $testYear)->first();
        $this->assertNotNull($row);
        $this->assertEquals(1, $row->current_seq);

        // Second call in new year must increment to 2
        $num2 = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00002", $num2);
    }

    public function test_initialization_from_max_existing_calls_when_sequence_row_is_absent(): void
    {
        $service = new CallService();
        $testYear = 2029;

        // Ensure test year sequence is absent
        DB::table('call_sequences')->where('year', $testYear)->delete();

        // Simulate pre-existing legacy call with sequence 42
        $user = User::where('username', 'test_operator_seq')->first() ?? User::first();
        DB::table('calls')->insert([
            'call_number' => "CRM-{$testYear}-00042",
            'caller_number' => '03001234567',
            'agent_id' => $user->id,
            'call_type_id' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Next generated call number must automatically initialize to 42 and return 43
        $num = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00043", $num);
    }

    public function test_transaction_rollback_preserves_monotonic_sequence_gap(): void
    {
        $service = new CallService();
        $year = (int) date('Y');

        $beforeSeq = (int) DB::table('call_sequences')->where('year', $year)->value('current_seq');

        try {
            DB::transaction(function () use ($service) {
                $service->generateCallNumber();
                throw new \RuntimeException("Simulated transactional failure during call handling");
            });
        } catch (\RuntimeException $e) {
            // Expected rollback
        }

        $afterSeq = (int) DB::table('call_sequences')->where('year', $year)->value('current_seq');

        // In MySQL InnoDB, transactional rollback reverts the row update, preventing sequence leakage
        $this->assertEquals($beforeSeq, $afterSeq, "When transaction rolls back, InnoDB reverts call_sequences update, preventing sequence gaps.");
    }

    public function test_connection_affinity_uses_write_pdo(): void
    {
        $service = new CallService();
        $year = (int) date('Y');

        $num = $service->generateCallNumber();
        $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $num);

        // Verify PDO on the default connection executed and matches sequence table state
        $pdo = DB::connection()->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $currentInDb = (int) DB::table('call_sequences')->where('year', $year)->value('current_seq');
        $seqFromNum = (int) substr($num, strrpos($num, '-') + 1);
        $this->assertLessThanOrEqual($currentInDb, $seqFromNum);
    }

    public function test_first_allocation_in_year_with_no_existing_calls(): void
    {
        $service = new CallService();
        $testYear = 2030;

        DB::table('call_sequences')->where('year', $testYear)->delete();
        DB::table('calls')->where('call_number', 'like', "CRM-{$testYear}-%")->delete();

        // Must allocate sequence 1 cleanly without zero or stale state
        $num = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00001", $num);

        $row = DB::table('call_sequences')->where('year', $testYear)->first();
        $this->assertNotNull($row);
        $this->assertEquals(1, $row->current_seq);

        DB::table('call_sequences')->where('year', $testYear)->delete();
    }

    public function test_mixed_version_coexistence_with_legacy_allocator(): void
    {
        $service = new CallService();
        $testYear = 2031;

        DB::table('call_sequences')->where('year', $testYear)->delete();

        // 1. Initialized by new allocator
        $num1 = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00001", $num1);

        // 2. Simulated legacy node executing lockForUpdate
        DB::transaction(function () use ($testYear) {
            $seq = DB::table('call_sequences')->where('year', $testYear)->lockForUpdate()->first();
            $next = $seq->current_seq + 1;
            DB::table('call_sequences')->where('year', $testYear)->update(['current_seq' => $next]);
        });

        // 3. Next allocation by new allocator must observe legacy increment to 3
        $num3 = $service->generateCallNumber($testYear);
        $this->assertEquals("CRM-{$testYear}-00003", $num3);

        DB::table('call_sequences')->where('year', $testYear)->delete();
    }

    public function test_bounded_retry_recovers_from_duplicate_key_collision(): void
    {
        $service = new CallService();
        $user = User::where('username', 'test_operator_seq')->first() ?? User::first();
        if (!$user) {
            $this->markTestSkipped('No user found for test.');
        }

        // Generate next sequence
        $year = (int) date('Y');
        $currentSeq = (int) DB::table('call_sequences')->where('year', $year)->value('current_seq');
        $collidingNumber = sprintf('CRM-%d-%05d', $year, $currentSeq + 1);

        // Insert colliding call record directly to simulate external insert race
        DB::table('calls')->insert([
            'call_number' => $collidingNumber,
            'caller_number' => '03009999999',
            'agent_id' => $user->id,
            'call_type_id' => 1,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // createCall must catch the collision, advance sequence, and succeed with next number
        $call = $service->createCall([
            'caller_number' => '03008888888',
            'call_type_id' => 1,
        ], $user);

        $this->assertNotNull($call);
        $this->assertNotEquals($collidingNumber, $call->call_number);
        $this->assertDatabaseHas('calls', ['id' => $call->id]);

        // Cleanup test calls
        DB::table('calls')->where('call_number', $collidingNumber)->delete();
        DB::table('call_status_history')->where('call_id', $call->id)->delete();
        $call->delete();
    }
}
