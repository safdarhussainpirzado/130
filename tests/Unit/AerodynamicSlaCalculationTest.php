<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\DashboardController;

class AerodynamicSlaCalculationTest extends TestCase
{
    /**
     * Test that computeFourSlas returns Priority 1, Priority 2, Priority 3+, and Women Safety SLA.
     */
    public function test_compute_four_slas_structure_and_benchmarks(): void
    {
        $controller = app(DashboardController::class);
        $fourSlas = $controller->computeFourSlas(now()->startOfDay());

        $this->assertArrayHasKey('p1', $fourSlas);
        $this->assertArrayHasKey('p2', $fourSlas);
        $this->assertArrayHasKey('p3', $fourSlas);
        $this->assertArrayHasKey('women', $fourSlas);

        // Benchmark targets verification
        $this->assertEquals(1800, $fourSlas['p1']['target_sec'], 'P1 Target must be 1,800 seconds (30 minutes)');
        $this->assertEquals(3600, $fourSlas['p2']['target_sec'], 'P2 Target must be 3,600 seconds (60 minutes)');
        $this->assertEquals(7200, $fourSlas['p3']['target_sec'], 'P3+ Target must be 7,200 seconds (120 minutes)');
        $this->assertEquals(1200, $fourSlas['women']['target_sec'], 'Women Safety Target must be 1,200 seconds (20 minutes)');

        // Percentage range verification
        $this->assertGreaterThanOrEqual(0, $fourSlas['p1']['percent']);
        $this->assertLessThanOrEqual(100, $fourSlas['p1']['percent']);
    }

    /**
     * Test robust fallbacks return 100% standby without fake data on an empty day.
     */
    public function test_slas_fallback_on_zero_eligible_calls(): void
    {
        $controller = app(DashboardController::class);
        // Date far in the future with 0 calls
        $fourSlas = $controller->computeFourSlas(now()->addYears(100));

        $this->assertEquals(0, $fourSlas['p1']['eligible']);
        $this->assertEquals(100, $fourSlas['p1']['percent']);
        $this->assertEquals(0, $fourSlas['women']['eligible']);
        $this->assertEquals(100, $fourSlas['women']['percent']);
    }
}
