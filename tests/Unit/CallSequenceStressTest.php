<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CallService;
use Illuminate\Support\Facades\DB;

class CallSequenceStressTest extends TestCase
{
    /**
     * Test high-frequency sequential allocation for uniqueness and correctness.
     */
    public function test_high_frequency_sequential_uniqueness(): void
    {
        $service = new CallService();
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
            $this->assertEquals($s1 + 1, $s2, "Sequence between {$allocated[$i]} and {$allocated[$i+1]} must increment by 1.");
        }
    }
}
