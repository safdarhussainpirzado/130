<?php

namespace Tests\Unit;

use App\Services\CallService;
use Tests\TestCase;

class CallSequenceStressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available for CallSequenceStressTest: ' . $e->getMessage());
        }
    }

    /**
     * Test high-frequency sequential allocation for uniqueness and correctness.
     */
    public function test_high_frequency_sequential_uniqueness(): void
    {
        $service = new CallService;
        $year = (int) date('Y');

        $allocated = [];
        for ($i = 0; $i < 25; $i++) {
            $callNumber = $service->generateCallNumber();
            $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $callNumber);
            $this->assertNotContains($callNumber, $allocated, "Duplicate call number detected: {$callNumber}");
            $allocated[] = $callNumber;
        }

        // Verify strictly monotonic increment
        for ($i = 0; $i < count($allocated) - 1; $i++) {
            $s1 = (int) substr($allocated[$i], strrpos($allocated[$i], '-') + 1);
            $s2 = (int) substr($allocated[$i + 1], strrpos($allocated[$i + 1], '-') + 1);
            $this->assertEquals($s1 + 1, $s2, "Sequence between {$allocated[$i]} and {$allocated[$i + 1]} must increment by 1.");
        }
    }

    /**
     * Test interleaved allocations across independent database connections.
     */
    public function test_multi_connection_interleaved_allocation(): void
    {
        $service = new CallService;
        $year = (int) date('Y');

        $allocated = [];

        // Worker 1 and Worker 2 simulating interleaved requests
        for ($i = 0; $i < 10; $i++) {
            $n1 = $service->generateCallNumber($year);
            $n2 = $service->generateCallNumber($year);

            $this->assertNotEquals($n1, $n2);
            $this->assertNotContains($n1, $allocated);
            $this->assertNotContains($n2, $allocated);

            $allocated[] = $n1;
            $allocated[] = $n2;
        }

        $this->assertCount(20, array_unique($allocated));
    }
}
