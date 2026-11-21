<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SurveyLocation;
use App\Services\Humsafar\SurveyGeoEnricher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/commuter/nearby-locations
 * Bounding-box prefilter on indexed lat/lng, then Haversine, sorted by distance. Only approved + active.
 * Recommended client refresh: when moved > 1 km or every 2 minutes; honour 429 Retry-After.
 */
class NearbyLocationsController extends Controller
{
    public const MAX_RADIUS_M = 25000;
    public const MAX_LIMIT = 50;

    public function __invoke(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['nullable', 'integer', 'min:100'], 'direction' => ['nullable', 'in:N,S,E,W,n,s,e,w'],
            'category' => ['nullable', 'integer'], 'limit' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $lat = (float) $v['lat'];
        $lng = (float) $v['lng'];
        $radius = min((int) ($v['radius_m'] ?? 5000), self::MAX_RADIUS_M);
        $limit = min((int) ($v['limit'] ?? 20), self::MAX_LIMIT);
        $page = (int) ($v['page'] ?? 1);
        $dir = isset($v['direction']) ? strtoupper($v['direction']) : null;

        $dLat = $radius / 111320;
        $dLng = $radius / (111320 * max(0.01, cos(deg2rad($lat))));

        $q = SurveyLocation::public()->with(['category:id,name,icon', 'services:id,name'])
            ->whereBetween('lat', [$lat - $dLat, $lat + $dLat])->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
        if (! empty($v['category'])) {
            $q->where('category_id', $v['category']);
        }

        $rows = $q->limit(1000)->get()->map(function (SurveyLocation $l) use ($lat, $lng, $dir) {
            $d = SurveyGeoEnricher::haversineM($lat, $lng, $l->lat, $l->lng);
            $sameSide = $dir && $l->direction ? (strtoupper($l->direction) === $dir ? 0 : 1) : 0;

            return [$l, $d, $sameSide];
        })->filter(fn ($r) => $r[1] <= $radius)
            ->sort(fn ($a, $b) => [$a[2], $a[1]] <=> [$b[2], $b[1]])->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $limit, $limit)->values()->map(fn ($r) => [
            'uuid' => $r[0]->uuid, 'name' => $r[0]->name, 'category' => $r[0]->category?->name, 'category_id' => $r[0]->category_id,
            'services' => $r[0]->services->pluck('name'), 'address' => $r[0]->address, 'lat' => $r[0]->lat, 'lng' => $r[0]->lng,
            'distance_m' => (int) round($r[1]), 'direction' => $r[0]->direction, 'km' => $r[0]->km,
            'is_24h' => $r[0]->is_24h, 'opens_at' => $r[0]->opens_at, 'closes_at' => $r[0]->closes_at,
            'open_now' => $r[0]->isOpenAt(now('Asia/Karachi')), 'phone_numbers' => $r[0]->phone_numbers,
        ]);

        return response()->json(['success' => true, 'data' => $slice, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'radius_m' => $radius]]);
    }
}
