<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Office;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardIncidentRoadSnapTest extends TestCase
{
    use DatabaseTransactions;

    private function snap(int $office, ?string $km, int $seed): ?array
    {
        $m = new \ReflectionMethod(DashboardController::class, 'snapIncidentToRoad');
        $m->setAccessible(true);

        return $m->invoke(app(DashboardController::class), $office, $km, $seed);
    }

    public function test_incident_is_placed_on_the_kml_marker_or_along_the_beat_road(): void
    {
        Cache::flush();
        $beat = Office::create(['name' => 'Snap Test Beat', 'type' => 'beat', 'is_active' => true]);
        foreach ([100.0, 100.5, 101.0, 101.5] as $i => $km) {
            DB::table('geospatial_markers')->insert([
                ['office_id' => $beat->id, 'km' => $km, 'lat' => 30.0 + $i * 0.01, 'lng' => 70.0 + $i * 0.01, 'side' => 'N'],
                ['office_id' => $beat->id, 'km' => $km, 'lat' => 30.0005 + $i * 0.01, 'lng' => 70.0005 + $i * 0.01, 'side' => 'S'],
            ]);
        }

        // KM + side -> exact marker on that carriageway
        $nb = $this->snap($beat->id, '101 NB', 1);
        $sb = $this->snap($beat->id, '101 SB', 1);
        $this->assertEqualsWithDelta(30.02, $nb['lat'], 0.0001);
        $this->assertEqualsWithDelta(30.0205, $sb['lat'], 0.0001);

        // '101-S' style as typed in the form
        $dash = $this->snap($beat->id, '101-S', 1);
        $this->assertEqualsWithDelta(30.0205, $dash['lat'], 0.0001);

        // KM without side -> a marker at that km
        $any = $this->snap($beat->id, '100.5', 1);
        $this->assertEqualsWithDelta(30.01, floor($any['lat'] * 100) / 100, 0.0001);

        // No / unusable KM -> still a real road point of the beat (never an averaged off-road centroid)
        $roadLats = DB::table('geospatial_markers')->where('office_id', $beat->id)->pluck('lat')->map(fn ($v) => (float) $v)->all();
        foreach ([null, '', 'near bridge', '9999'] as $bad) {
            $p = $this->snap($beat->id, $bad, 7);
            $this->assertNotNull($p);
            $this->assertContains($p['lat'], $roadLats, 'Fallback point must be an actual road marker');
        }

        // Beat without markers -> null so the caller falls back to the old centroid
        $this->assertNull($this->snap($beat->id + 100000, '12', 1));
    }
}
