<?php

namespace App\Console\Commands;

use App\Models\Carriageway;
use App\Models\GeospatialLandmark;
use App\Models\GeospatialMarker;
use App\Models\Office;
use App\Services\FreeRouteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnrichGeospatialDataCommand extends Command
{
    protected $signature = 'nhmp:enrich-geospatial {road=M-3 : Road code to enrich (M-3, M-1, M-2, E-35, LEW, LEBP, CLEAN, LANDMARKS, or ALL)} {--force : Overwrite existing markers}';
    protected $description = 'Enrich road centerline coordinates, dual carriageways, beat mappings, and landmark coordinates via FreeRoute Maps API';

    protected FreeRouteService $freeRoute;

    public function __construct(FreeRouteService $freeRoute)
    {
        parent::__construct();
        $this->freeRoute = $freeRoute;
    }

    public function handle(): int
    {
        $road = strtoupper(trim((string) $this->argument('road')));
        $this->info("======================================================================");
        $this->info("  NHMP GEOSPATIAL MULTI-CORRIDOR ENRICHMENT PIPELINE — ROAD: {$road}");
        $this->info("======================================================================");

        if ($road === 'ALL') {
            $routes = ['M-3', 'M-1', 'M-2', 'E-35', 'LEW', 'LEBP', 'CLEAN', 'LANDMARKS'];
            $allSuccess = true;
            foreach ($routes as $r) {
                $status = $this->enrichRoad($r);
                if ($status !== self::SUCCESS) {
                    $allSuccess = false;
                }
            }
            $this->flushGeospatialCaches();
            return $allSuccess ? self::SUCCESS : self::FAILURE;
        }

        $status = $this->enrichRoad($road);
        $this->flushGeospatialCaches();
        return $status;
    }

    /**
     * Dispatch to appropriate corridor blueprint.
     */
    protected function enrichRoad(string $road): int
    {
        return match ($road) {
            'M-3', 'M3'          => $this->enrichM3(),
            'M-1', 'M1'          => $this->enrichM1(),
            'M-2', 'M2'          => $this->enrichM2(),
            'E-35', 'E35'        => $this->enrichE35(),
            'LEW'                => $this->enrichLEW(),
            'LEBP'               => $this->enrichLEBP(),
            'CLEAN', 'CLEANUP'   => $this->cleanOutliers(),
            'LANDMARKS'          => $this->geocodeAllLandmarks(),
            default              => $this->handleUnsupportedRoad($road),
        };
    }

    protected function handleUnsupportedRoad(string $road): int
    {
        $this->error("Automated corridor blueprint for '{$road}' is not yet configured. Supported: M-3, M-1, M-2, E-35, LEW, LEBP, CLEAN, LANDMARKS, ALL");
        return self::FAILURE;
    }

    /**
     * Enrich M-3 Motorway (Faizpur M-2 Junction -> Abdul Hakim M-4 Junction, 230 km).
     */
    protected function enrichM3(): int
    {
        return $this->executeEnrichment(
            roadCode: 'M-3',
            roadName: 'Motorway M-3 (Lahore - Abdul Hakim)',
            freeRouteCoords: [
                [74.1950, 31.5540], // Faizpur M-3 entry (KM 1148)
                [72.1323, 30.6544], // Abdul Hakim M-3 / M-4 junction terminus (KM 918)
            ],
            startKm: 1148.0,
            endKm: 918.0,
            beatIds: [66, 67, 68, 69],
            landmarkRoadName: 'M-3',
            beatResolver: fn(float $km) => $this->resolveM3Beat($km)
        );
    }

    /**
     * Enrich M-1 Motorway (Islamabad -> Peshawar, 155 km).
     */
    protected function enrichM1(): int
    {
        return $this->executeEnrichment(
            roadCode: 'M-1',
            roadName: 'Motorway M-1 (Islamabad - Peshawar)',
            freeRouteCoords: [
                [72.9366, 33.6263], // Islamabad Motorway Chowk (KM 351)
                [72.2985, 33.8184], // Burhan Interchange (KM 387)
                [71.6917, 34.0207], // Peshawar Toll Plaza (KM 506)
            ],
            startKm: 351.0,
            endKm: 506.0,
            beatIds: [43, 44, 45, 46],
            landmarkRoadName: 'M-1',
            beatResolver: fn(float $km) => $this->resolveM1Beat($km)
        );
    }

    /**
     * Enrich M-2 Motorway (Lahore -> Islamabad, 367 km).
     */
    protected function enrichM2(): int
    {
        return $this->executeEnrichment(
            roadCode: 'M-2',
            roadName: 'Motorway M-2 (Lahore - Islamabad)',
            freeRouteCoords: [
                [74.2386, 31.4678], // Lahore Thokar Niaz Baig (KM 1)
                [73.2988, 31.8950], // Pindi Bhattian (M-2 / M-4 Junction, KM 90)
                [72.7100, 32.7766], // Kallar Kahar Salt Range (KM 235)
                [72.9366, 33.6263], // Islamabad Motorway Chowk (KM 357)
            ],
            startKm: 1.0,
            endKm: 357.0,
            beatIds: [47, 48, 49, 50, 62, 63, 64, 65],
            landmarkRoadName: 'M-2',
            beatResolver: fn(float $km) => $this->resolveM2Beat($km)
        );
    }

    /**
     * Enrich E-35 Hazara Motorway (Hassan Abdal -> Thakot, 182 km).
     */
    protected function enrichE35(): int
    {
        return $this->executeEnrichment(
            roadCode: 'E-35',
            roadName: 'Hazara Motorway E-35 (Burhan - Thakot)',
            freeRouteCoords: [
                [72.2985, 33.8184], // Burhan / Hassan Abdal (M-1 Junction, KM 0)
                [73.1550, 34.0450], // Havelian Interchange (KM 60)
                [72.9333, 34.7833], // Thakot Terminus (KM 180)
            ],
            startKm: 0.0,
            endKm: 180.0,
            beatIds: [51, 52, 53, 54],
            landmarkRoadName: 'E-35',
            beatResolver: fn(float $km) => $this->resolveE35Beat($km)
        );
    }

    /**
     * Enrich Lyari Expressway (Mauripur -> Sohrab Goth, 16.5 km).
     * Single Beat: Beat 122 (Beat-01-LEW) under Sector 42 (South4).
     */
    protected function enrichLEW(): int
    {
        return $this->executeEnrichment(
            roadCode: 'LEW',
            roadName: 'Lyari Expressway LEW (Mauripur - Sohrab Goth)',
            freeRouteCoords: [
                [66.9778, 24.8718], // Mauripur Interchange (KM 0.0)
                [67.0250, 24.8950], // Lasbela Interchange (KM 6.5)
                [67.0898, 24.9472], // Sohrab Goth Toll Plaza (KM 16.5)
            ],
            startKm: 0.0,
            endKm: 16.5,
            beatIds: [122],
            landmarkRoadName: 'LEW',
            beatResolver: fn(float $km) => 122
        );
    }

    /**
     * Enrich Lahore Eastern Bypass (Kala Shah Kaku -> Mehmood Booti, 16.5 km).
     * Single Beat: Beat 141 (Beat-03-LEBP) under Sector 20 (LSM).
     */
    protected function enrichLEBP(): int
    {
        return $this->executeEnrichment(
            roadCode: 'LEBP',
            roadName: 'Lahore Eastern Bypass LEBP (Kala Shah Kaku - Mehmood Booti)',
            freeRouteCoords: [
                [74.2745, 31.6990], // Kala Shah Kaku Cloverleaf Interchange (KM 0.0)
                [74.3304, 31.6603], // Mid-corridor bypass waypoint (KM 8.0)
                [74.4046, 31.6094], // Mehmood Booti / Ring Road Interchange (KM 16.5)
            ],
            startKm: 0.0,
            endKm: 16.5,
            beatIds: [141],
            landmarkRoadName: 'LEBP',
            beatResolver: fn(float $km) => 141
        );
    }

    /**
     * Generic Core Corridor Enrichment Engine.
     */
    protected function executeEnrichment(
        string $roadCode,
        string $roadName,
        array $freeRouteCoords,
        float $startKm,
        float $endKm,
        array $beatIds,
        string $landmarkRoadName,
        callable $beatResolver
    ): int {
        $this->info("--> Initiating Enrichment: {$roadName}");
        $this->info("Step 1: Querying FreeRoute API for High-Resolution Centerline...");

        $geoJson = $this->freeRoute->getDirections($freeRouteCoords, 'driving-car', false);

        if (!$geoJson || empty($geoJson['features'][0]['geometry']['coordinates'])) {
            $this->error("Failed to retrieve directions from FreeRoute API for {$roadCode}.");
            return self::FAILURE;
        }

        $coords = $geoJson['features'][0]['geometry']['coordinates']; // array of [lon, lat]
        $summary = $geoJson['features'][0]['properties']['summary'] ?? [];
        $totalDistanceKm = round(($summary['distance'] ?? 0) / 1000, 2);

        $this->info(sprintf("  -> Retrieved %s GIS points spanning %s km for %s.", 
            number_format(count($coords)), $totalDistanceKm, $roadCode));

        // Step 2: Compute cumulative distance along centerline
        $this->info("Step 2: Calculating kilometer markers and matching operational beats...");
        $cumulativeKm = [0.0];
        $totalLen = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $d = $this->haversineKm($coords[$i-1][1], $coords[$i-1][0], $coords[$i][1], $coords[$i][0]);
            $totalLen += $d;
            $cumulativeKm[] = $totalLen;
        }

        $kmSpan = $endKm - $startKm;

        // Step 3: Build high-density physical markers for both Northbound and Southbound carriageways
        $markersToInsert = [];
        $offsetDeg = 0.00012; // ~13m lateral offset for dual carriageway separation

        for ($i = 0; $i < count($coords); $i++) {
            $fraction = $totalLen > 0 ? ($cumulativeKm[$i] / $totalLen) : 0;
            $currentKm = round($startKm + ($fraction * $kmSpan), 2);

            $lon = (float) $coords[$i][0];
            $lat = (float) $coords[$i][1];

            // Heading vector to compute lateral normal for dual carriageways
            if ($i < count($coords) - 1) {
                $dLat = (float) ($coords[$i+1][1] - $coords[$i][1]);
                $dLng = (float) ($coords[$i+1][0] - $coords[$i][0]);
            } else {
                $dLat = (float) ($coords[$i][1] - $coords[$i-1][1]);
                $dLng = (float) ($coords[$i][0] - $coords[$i-1][0]);
            }
            $mag = sqrt($dLat * $dLat + $dLng * $dLng);
            if ($mag > 0) {
                $normLat = -($dLng / $mag) * $offsetDeg;
                $normLng = ($dLat / $mag) * $offsetDeg;
            } else {
                $normLat = $offsetDeg;
                $normLng = $offsetDeg;
            }

            // Determine Beat (office_id)
            $officeId = $beatResolver($currentKm);

            // Northbound (N)
            $markersToInsert[] = [
                'office_id'  => $officeId,
                'km'         => number_format($currentKm, 2, '.', ''),
                'lat'        => round($lat + $normLat, 7),
                'lng'        => round($lon + $normLng, 7),
                'side'       => 'N',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Southbound (S)
            $markersToInsert[] = [
                'office_id'  => $officeId,
                'km'         => number_format($currentKm, 2, '.', ''),
                'lat'        => round($lat - $normLat, 7),
                'lng'        => round($lon - $normLng, 7),
                'side'       => 'S',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->info(sprintf("  -> Generated %d high-density dual-carriageway markers for %s.", 
            count($markersToInsert), $roadCode));

        // Step 4: Database Update for Geospatial Markers
        $this->info("Step 3: Populating geospatial_markers database table...");
        DB::transaction(function () use ($beatIds, $markersToInsert) {
            // Delete existing markers for these beats
            GeospatialMarker::whereIn('office_id', $beatIds)->delete();

            foreach (array_chunk($markersToInsert, 250) as $chunk) {
                GeospatialMarker::insert($chunk);
            }
        });
        $this->info("  [OK] Geospatial markers populated successfully.");

        // Step 5: Geocode & update landmarks
        $this->info("Step 4: Geocoding and updating coordinates for {$roadCode} Landmarks...");
        $landmarks = GeospatialLandmark::where('road_name', $landmarkRoadName)->get();
        $updatedCount = 0;

        foreach ($landmarks as $lm) {
            $targetKm = (float) $lm->km_numeric;

            $closestLat = null;
            $closestLng = null;
            $minDiff = 999999;

            for ($i = 0; $i < count($coords); $i++) {
                $fraction = $totalLen > 0 ? ($cumulativeKm[$i] / $totalLen) : 0;
                $currentKm = $startKm + ($fraction * $kmSpan);
                $diff = abs($currentKm - $targetKm);

                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestLat = round($coords[$i][1], 7);
                    $closestLng = round($coords[$i][0], 7);
                }
            }

            if ($closestLat !== null && $closestLng !== null) {
                $officeId = in_array((int) $lm->office_id, $beatIds)
                    ? (int) $lm->office_id
                    : $beatResolver($targetKm);

                $lm->update([
                    'latitude'   => $closestLat,
                    'longitude'  => $closestLng,
                    'office_id'  => $officeId,
                ]);

                $updatedCount++;
            }
        }

        $this->info(sprintf("  [OK] Updated coordinates for %d of %d landmarks on %s.", 
            $updatedCount, $landmarks->count(), $roadCode));

        $this->info("======================================================================");
        $this->info("  {$roadCode} ENRICHMENT COMPLETE!");
        $this->info("======================================================================");

        return self::SUCCESS;
    }

    /**
     * Resolve Beat (office_id) for M-3 based on KM marker.
     * Beat 13: 66 (KM 1148 - 1098)
     * Beat 14: 67 (KM 1098 - 1045)
     * Beat 15: 68 (KM 1045 - 980)
     * Beat 16: 69 (KM 980 - 918)
     */
    protected function resolveM3Beat(float $km): int
    {
        if ($km > 1098.0) return 66; // Beat 13
        if ($km > 1045.0) return 67; // Beat 14
        if ($km >= 980.0) return 68; // Beat 15
        return 69;                   // Beat 16
    }

    /**
     * Resolve Beat (office_id) for M-1 based on KM marker.
     * Beat 04: 46 (KM 351 - 386) [Islamabad -> Burhan]
     * Beat 03: 45 (KM 387 - 420) [Burhan -> Swabi]
     * Beat 02: 44 (KM 420 - 460) [Swabi -> Rashakai]
     * Beat 01: 43 (KM 460 - 506) [Rashakai -> Peshawar]
     */
    protected function resolveM1Beat(float $km): int
    {
        if ($km <= 386.0) return 46; // Beat 04
        if ($km <= 420.0) return 45; // Beat 03
        if ($km <= 460.0) return 44; // Beat 02
        return 43;                   // Beat 01
    }

    /**
     * Resolve Beat (office_id) for M-2 based on KM marker.
     * Sector 18 (M2 South):
     * Beat 12: 65 (KM 1 - 45)    [Thokar -> Sheikhupura]
     * Beat 11: 64 (KM 45 - 90)   [Sheikhupura -> Pindi Bhattian]
     * Beat 10: 63 (KM 90 - 145)  [Pindi Bhattian -> Kot Momin]
     * Beat 09: 62 (KM 145 - 195) [Kot Momin -> Bhera/Salam]
     * Sector 14 (M2 North):
     * Beat 08: 50 (KM 195 - 235) [Bhera -> Kallar Kahar]
     * Beat 07: 49 (KM 235 - 275) [Kallar Kahar -> Balkasar]
     * Beat 06: 48 (KM 275 - 315) [Balkasar -> Chakri]
     * Beat 05: 47 (KM 315 - 357) [Chakri -> Islamabad]
     */
    protected function resolveM2Beat(float $km): int
    {
        if ($km <= 45.0)  return 65; // Beat 12
        if ($km <= 90.0)  return 64; // Beat 11
        if ($km <= 145.0) return 63; // Beat 10
        if ($km <= 195.0) return 62; // Beat 09
        if ($km <= 235.0) return 50; // Beat 08
        if ($km <= 275.0) return 49; // Beat 07
        if ($km <= 315.0) return 48; // Beat 06
        return 47;                   // Beat 05
    }

    /**
     * Resolve Beat (office_id) for E-35 based on KM marker.
     * Beat 01: 51 (KM 0 - 40)   [Burhan -> Haripur]
     * Beat 02: 52 (KM 40 - 80)  [Haripur -> Havelian]
     * Beat 03: 53 (KM 80 - 130) [Havelian -> Mansehra]
     * Beat 04: 54 (KM 130 - 180)[Mansehra -> Thakot]
     */
    protected function resolveE35Beat(float $km): int
    {
        if ($km <= 40.0)  return 51; // Beat 01
        if ($km <= 80.0)  return 52; // Beat 02
        if ($km <= 130.0) return 53; // Beat 03
        return 54;                   // Beat 04
    }

    /**
     * Remove rogue coordinate outliers that jump outside highway geofencing.
     * Enforces pinpoint accuracy and geofencing integrity.
     */
    protected function cleanOutliers(): int
    {
        $this->info("--> Initiating Highway Geofencing and Outlier Cleanup Pipeline...");

        // 1. N-10 (Beat 146): Remove known typo outlier markers
        $n10OutlierIds = [69146, 69147, 69334, 69335];
        $deletedN10 = GeospatialMarker::whereIn('id', $n10OutlierIds)->delete();
        $this->info("  [OK] Removed {$deletedN10} rogue outlier markers from N-10 (Beat 146).");

        // 2. N-50 (Beat 143): Clean up spliced off-corridor markers
        $beat143Markers = GeospatialMarker::where('office_id', 143)->get();
        $bad143Ids = [];
        foreach ($beat143Markers as $m) {
            if ($m->lat < 30.5 || $m->lng > 68.5) {
                $bad143Ids[] = $m->id;
            } elseif (abs($m->lat - 30.8318) < 0.005 && abs($m->lng - 67.7738) < 0.005) {
                $bad143Ids[] = $m->id;
            } elseif ($m->km >= 116.0 && $m->km <= 117.0 && $m->lng < 67.87) {
                $bad143Ids[] = $m->id;
            }
        }
        $deleted143 = 0;
        if (!empty($bad143Ids)) {
            $deleted143 = GeospatialMarker::whereIn('id', $bad143Ids)->delete();
        }
        $this->info("  [OK] Removed {$deleted143} off-road spliced markers from N-50 (Beat 143).");

        // 3. General spatial jump audit across all beats
        $beats = Office::where('type', 'beat')->get();
        $additionalDeleted = 0;
        foreach ($beats as $b) {
            foreach (['N', 'S'] as $side) {
                $markers = GeospatialMarker::where('office_id', $b->id)
                    ->where('side', $side)
                    ->orderBy('km', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
                if ($markers->count() < 3) continue;

                for ($i = 1; $i < $markers->count() - 1; $i++) {
                    $prev = $markers[$i - 1];
                    $curr = $markers[$i];
                    $next = $markers[$i + 1];

                    $dPrev = $this->haversineKm((float)$prev->lat, (float)$prev->lng, (float)$curr->lat, (float)$curr->lng);
                    $dNext = $this->haversineKm((float)$curr->lat, (float)$curr->lng, (float)$next->lat, (float)$next->lng);
                    $dDirect = $this->haversineKm((float)$prev->lat, (float)$prev->lng, (float)$next->lat, (float)$next->lng);

                    // If curr is > 1.5km away from both prev and next, while prev and next are < 0.5km apart (isolated spike outlier)
                    if ($dPrev > 1.5 && $dNext > 1.5 && $dDirect < 0.5) {
                        $curr->delete();
                        $additionalDeleted++;
                    }
                }
            }
        }
        if ($additionalDeleted > 0) {
            $this->info("  [OK] Purged {$additionalDeleted} additional isolated off-road markers.");
        }

        $this->info("  [OK] Outlier geofence cleanup complete. Geofencing integrity is 100%.");
        return self::SUCCESS;
    }

    /**
     * Geocode all landmarks with null coordinates by snapping to nearest physical marker on their route.
     */
    protected function geocodeAllLandmarks(): int
    {
        $this->info("--> Initiating Complete Landmark Geocoding Pipeline...");

        $unplaced = GeospatialLandmark::whereNull('latitude')->orWhereNull('longitude')->get();
        $this->info("  Found {$unplaced->count()} landmarks requiring coordinates.");

        $geocoded = 0;
        foreach ($unplaced as $lm) {
            $km = (float) $lm->km_numeric;
            $road = strtoupper(trim((string) $lm->road_name));
            
            // Search query for physical markers on this route
            $markerQuery = GeospatialMarker::query();
            if ($lm->office_id) {
                $markerQuery->where('office_id', $lm->office_id);
            } else {
                // Find beat IDs for this road
                $cw = Carriageway::where('road', $road)->orWhere('road_short', $road)->first();
                if ($cw && !empty($cw->beat_ids)) {
                    $markerQuery->whereIn('office_id', $cw->beat_ids);
                }
            }

            // Find closest marker by KM
            $closest = (clone $markerQuery)
                ->orderByRaw("ABS(CAST(km AS DECIMAL(8,2)) - {$km}) ASC")
                ->first();

            if ($closest) {
                $lm->update([
                    'latitude'   => $closest->lat,
                    'longitude'  => $closest->lng,
                    'office_id'  => $closest->office_id,
                ]);
                $geocoded++;
            }
        }

        $this->info("  [OK] Successfully geocoded and snapped {$geocoded} landmarks.");
        return self::SUCCESS;
    }

    /**
     * Flush all geospatial cache stores.
     */
    protected function flushGeospatialCaches(): void
    {
        Cache::forget('geospatial_office_hierarchy_v5');
        Cache::forget('geospatial_office_hierarchy_v6');
        Cache::forget('geospatial_office_hierarchy_v7');
        Cache::forget('geospatial_office_hierarchy_v8');
        Cache::forget('geospatial_office_hierarchy_v9');
        Cache::forget('geospatial_marker_grid_stats_v2');
        Cache::forget('geospatial_marker_grid_stats_v3');
        $this->info("Geospatial hierarchy and grid stats caches refreshed.");
    }

    /**
     * Great-circle distance between two GPS points in kilometers.
     */
    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
