<?php

namespace App\Services\Humsafar;

use App\Models\GeospatialMarker;

/**
 * Snaps a surveyed coordinate to the nearest geospatial marker (strict tolerance) and derives
 * km / carriageway side / beat. Opposite carriageways are never mixed: when markers on both sides
 * are within tolerance and no direction is supplied, the result is `needs_verification`.
 */
class SurveyGeoEnricher
{
    public const TOLERANCE_M = 300;

    /** @return array{geo_status:string, marker_id:?int, office_id:?int, km:?float, direction:?string, distance_m:?int} */
    public function enrich(float $lat, float $lng, ?string $direction = null): array
    {
        $deg = self::TOLERANCE_M / 111000 * 1.5; // cheap bounding box prefilter
        $candidates = GeospatialMarker::query()
            ->whereBetween('lat', [$lat - $deg, $lat + $deg])
            ->whereBetween('lng', [$lng - $deg, $lng + $deg])
            ->get()
            ->map(fn ($m) => [$m, self::haversineM($lat, $lng, (float) $m->lat, (float) $m->lng)])
            ->filter(fn ($p) => $p[1] <= self::TOLERANCE_M)
            ->sortBy(fn ($p) => $p[1])
            ->values();

        $none = ['geo_status' => 'unmatched', 'marker_id' => null, 'office_id' => null, 'km' => null, 'direction' => null, 'distance_m' => null];
        if ($candidates->isEmpty()) {
            return $none;
        }

        $dir = $direction ? strtoupper($direction) : null;
        if ($dir) {
            $same = $candidates->first(fn ($p) => strtoupper((string) $p[0]->side) === $dir);
            if (! $same) {
                return array_merge($none, ['geo_status' => 'needs_verification']);
            }
            $pick = $same;
        } else {
            $sides = $candidates->map(fn ($p) => strtoupper((string) $p[0]->side))->unique();
            if ($sides->count() > 1) {
                return array_merge($none, ['geo_status' => 'needs_verification']);
            }
            $pick = $candidates->first();
        }

        [$m, $d] = $pick;

        return [
            'geo_status' => 'matched', 'marker_id' => $m->id, 'office_id' => $m->office_id,
            'km' => (float) $m->km, 'direction' => $m->side ? strtoupper($m->side) : null, 'distance_m' => (int) round($d),
        ];
    }

    public static function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
