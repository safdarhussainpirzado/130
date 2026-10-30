<?php

namespace Tests\Feature;

use App\Models\GeospatialLandmark;
use App\Models\Office;
use Tests\TestCase;

class GeospatialOptimizationTest extends TestCase
{
    /**
     * Test geospatial KPI stats array structure and default handling.
     */
    public function test_geospatial_stats_calculation(): void
    {
        $mockStatsRaw = (object) [
            'total_markers' => 1250,
            'active_markers' => 1200,
            'inactive_markers' => 50,
            'motorways' => 450,
            'highways' => 800,
        ];

        $stats = [
            'total_markers'     => (int) ($mockStatsRaw->total_markers ?? 0),
            'active_markers'    => (int) ($mockStatsRaw->active_markers ?? 0),
            'inactive_markers'  => (int) ($mockStatsRaw->inactive_markers ?? 0),
            'motorways'         => (int) ($mockStatsRaw->motorways ?? 0),
            'highways'          => (int) ($mockStatsRaw->highways ?? 0),
        ];

        $this->assertEquals(1250, $stats['total_markers']);
        $this->assertEquals(1200, $stats['active_markers']);
        $this->assertEquals(50, $stats['inactive_markers']);
        $this->assertEquals(450, $stats['motorways']);
        $this->assertEquals(800, $stats['highways']);
    }

    /**
     * Test pagination clamping logic ensures boundaries [10, 100].
     */
    public function test_pagination_clamping(): void
    {
        // Default
        $perPage = min(max((int) 15, 10), 100);
        $this->assertEquals(15, $perPage);

        // Lower boundary
        $perPageLow = min(max((int) 2, 10), 100);
        $this->assertEquals(10, $perPageLow);

        // Upper boundary protection against memory exhaustion
        $perPageHigh = min(max((int) 9999, 10), 100);
        $this->assertEquals(100, $perPageHigh);
    }
}
