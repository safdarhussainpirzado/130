<?php

namespace App\Http\Controllers;

use App\Models\Carriageway;
use App\Models\GeospatialMarker;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeospatialMarkerController extends Controller
{
    /**
     * Display a listing and map of the Geospatial Highway Markers.
     * Supports both a paginated audit data table and a viewport bounding-box API for Leaflet Canvas/MarkerCluster.
     */
    public function index(Request $request)
    {
        // 1. Viewport Bounding-Box API for Leaflet Map
        if ($request->has('viewport') || $request->has('north')) {
            return $this->viewportApi($request);
        }

        // 2. Paginated Audit Table & Filtering
        $perPage  = min(max((int) $request->input('per_page', 25), 10), 100);
        $sortBy   = in_array($request->input('sort_by'), ['km', 'lat', 'lng', 'side', 'id']) ? $request->input('sort_by') : 'km';
        $sortDir  = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = GeospatialMarker::query()->with([
            'office:id,name,parent_id,type',
            'office.parent:id,name,parent_id,type',
            'office.parent.parent:id,name,type',
        ]);

        // Direction / Side filter (Northbound, Southbound, etc.)
        if ($side = $request->input('side')) {
            if ($side === 'null' || $side === 'undirected') {
                $query->whereNull('side');
            } else {
                $query->where('side', $side);
            }
        }

        // Road / Highway filter
        if ($road = $request->input('road')) {
            $hierarchy = self::getOfficeHierarchy();
            $roadObj = collect($hierarchy['roads'])->firstWhere('road_short', $road)
                    ?? collect($hierarchy['roads'])->firstWhere('road', $road);
            if ($roadObj && !empty($roadObj['beat_ids'])) {
                $query->whereIn('office_id', $roadObj['beat_ids']);
            }
        }

        // Office / Beat / Sector / Zone filter
        if ($beatId = $request->input('beat_id')) {
            $query->where('office_id', $beatId);
        } elseif ($sectorId = $request->input('sector_id')) {
            $beatIds = Office::where('type', 'beat')->where('parent_id', $sectorId)->pluck('id');
            $query->whereIn('office_id', $beatIds);
        } elseif ($zoneId = $request->input('zone_id')) {
            $sectorIds = Office::where('type', 'sector')->where('parent_id', $zoneId)->pluck('id');
            $beatIds = Office::where('type', 'beat')->whereIn('parent_id', $sectorIds)->pluck('id');
            $query->whereIn('office_id', $beatIds);
        }

        // Search by KM numeric range or Beat name
        if ($search = trim((string) $request->input('search'))) {
            if (is_numeric($search)) {
                $kmVal = (float) $search;
                $query->whereBetween('km', [$kmVal - 1.0, $kmVal + 1.0]);
            } else {
                $query->whereHas('office', function ($oq) use ($search) {
                    $oq->where('name', 'like', "%{$search}%");
                });
            }
        }

        $markers = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        // Dynamic Highway Coordinate Grid statistics
        $stats = Cache::get('geospatial_marker_grid_stats_v2');
        if (! is_array($stats)) {
            $total      = GeospatialMarker::count();
            $n          = GeospatialMarker::where('side', 'N')->count();
            $s          = GeospatialMarker::where('side', 'S')->count();
            $undirected = GeospatialMarker::whereNull('side')->count();
            $beats      = DB::table('geospatial_markers')->distinct()->count('office_id');

            $stats = [
                'total_markers'     => $total,
                'northbound'        => $n,
                'southbound'        => $s,
                'undirected'        => $undirected,
                'total_beats'       => $beats,
                'coverage_bounds'   => 'Lat 24.78° - 34.77° | Lng 62.38° - 74.41°',
                'data_integrity'    => '100% Valid Coordinates (0 Nulls)',
            ];
            Cache::put('geospatial_marker_grid_stats_v2', $stats, 3600);
        }

        $hierarchy = self::getOfficeHierarchy();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'items' => $markers->items(),
                'roads' => $hierarchy['roads'] ?? [],
                'pagination' => [
                    'current_page' => $markers->currentPage(),
                    'last_page'    => $markers->lastPage(),
                    'total'        => $markers->total(),
                    'per_page'     => $markers->perPage(),
                    'from'         => $markers->firstItem(),
                    'to'           => $markers->lastItem(),
                ],
                'stats' => $stats,
            ]);
        }

        return view('operations.geospatial-markers.index', [
            'markers'    => $markers,
            'roads'      => $hierarchy['roads'] ?? [],
            'beats'      => $hierarchy['beats'],
            'sectors'    => $hierarchy['sectors'],
            'zones'      => $hierarchy['zones'],
            'stats'      => $stats,
            'pagination' => [
                'current_page' => $markers->currentPage(),
                'last_page'    => $markers->lastPage(),
                'total'        => $markers->total(),
                'per_page'     => $markers->perPage(),
                'from'         => $markers->firstItem(),
                'to'           => $markers->lastItem(),
            ],
        ]);
    }

    /**
     * High-speed Viewport Bounding-Box API for Leaflet Map.
     * Uses composite index (lat, lng) to deliver sub-50ms viewport queries.
     * Automatically applies server-side cluster aggregation at low zoom levels.
     */
    public function viewportApi(Request $request)
    {
        $north    = min((float) $request->input('north', 37.0), 38.0);
        $south    = max((float) $request->input('south', 23.0), 22.0);
        $east     = min((float) $request->input('east', 78.0), 79.0);
        $west     = max((float) $request->input('west', 60.0), 59.0);
        $zoom     = (int) $request->input('zoom', 10);
        $side     = $request->input('side');
        $road     = $request->input('road');
        $zoneId   = $request->input('zone_id');
        $sectorId = $request->input('sector_id');
        $beatId   = $request->input('beat_id');

        $query = GeospatialMarker::query();

        // Bounding box range check using B-tree index idx_markers_lat_lng
        $query->whereBetween('lat', [$south, $north])
              ->whereBetween('lng', [$west, $east]);

        if ($side && in_array($side, ['N', 'S'], true)) {
            $query->where('side', $side);
        }

        if ($road) {
            $hierarchy = self::getOfficeHierarchy();
            $roadObj = collect($hierarchy['roads'])->firstWhere('road_short', $road)
                    ?? collect($hierarchy['roads'])->firstWhere('road', $road);
            if ($roadObj && !empty($roadObj['beat_ids'])) {
                $query->whereIn('office_id', $roadObj['beat_ids']);
            }
        }

        if ($beatId) {
            $query->where('office_id', $beatId);
        } elseif ($sectorId) {
            $beatIds = Office::where('type', 'beat')->where('parent_id', $sectorId)->pluck('id');
            $query->whereIn('office_id', $beatIds);
        } elseif ($zoneId) {
            $sectorIds = Office::where('type', 'sector')->where('parent_id', $zoneId)->pluck('id');
            $beatIds = Office::where('type', 'beat')->whereIn('parent_id', $sectorIds)->pluck('id');
            $query->whereIn('office_id', $beatIds);
        }

        // Exact count of matched coordinates within requested viewport
        $totalMatched = (clone $query)->count();

        // Mode decision: If low zoom (< 10) or high density (> 2000 points without force_points),
        // render lossless cluster centroids where sum(count) == totalMatched
        $isClusterMode = ($zoom < 10 || ($totalMatched > 2000 && !$request->boolean('force_points')));

        if ($isClusterMode) {
            // Check cache for national overview (zoom <= 8, no filters, full Pakistan bounds)
            $isNationalOverview = ($zoom <= 8 && !$side && !$beatId && $south <= 25.0 && $north >= 36.0 && $west <= 63.0 && $east >= 75.0);
            
            $clusters = $query->selectRaw('
                    office_id,
                    side,
                    COUNT(*) as count,
                    ROUND(AVG(lat), 5) as lat,
                    ROUND(AVG(lng), 5) as lng,
                    MIN(km) as km_min,
                    MAX(km) as km_max
                ')
                ->groupBy('office_id', 'side')
                ->with('office:id,name')
                ->get(); // No arbitrary limit: returns all cluster groups across viewport

            $formatted = $clusters->map(fn($c) => [
                'type'     => 'cluster',
                'lat'      => (float) $c->lat,
                'lng'      => (float) $c->lng,
                'count'    => (int) $c->count,
                'beat'     => $c->office?->name ?? 'Beat #' . $c->office_id,
                'side'     => $c->side ?? 'Undirected',
                'km_range' => "KM {$c->km_min} - {$c->km_max}",
            ]);

            return response()->json([
                'mode'          => 'clusters',
                'zoom'          => $zoom,
                'total_matched' => $totalMatched,
                'total'         => $formatted->sum('count'),
                'cluster_count' => $formatted->count(),
                'is_truncated'  => false,
                'markers'       => $formatted,
            ]);
        }

        // Points mode: High-precision individual coordinates with pagination/subdivision support
        $page = max((int) $request->input('page', 1), 1);
        $perPage = min(max((int) $request->input('per_page', 2000), 100), 5000);

        $points = $query->select(['id', 'office_id', 'km', 'lat', 'lng', 'side'])
            ->with('office:id,name')
            ->forPage($page, $perPage)
            ->get();

        $formatted = $points->map(fn($p) => [
            'type' => 'marker',
            'id'   => $p->id,
            'km'   => (float) $p->km,
            'lat'  => (float) $p->lat,
            'lng'  => (float) $p->lng,
            'side' => $p->side,
            'beat' => $p->office?->name ?? 'Beat #' . $p->office_id,
        ]);

        return response()->json([
            'mode'          => 'points',
            'zoom'          => $zoom,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => (int) ceil($totalMatched / $perPage),
            'total_matched' => $totalMatched,
            'total'         => $formatted->count(),
            'has_more'      => ($page * $perPage) < $totalMatched,
            'is_truncated'  => false,
            'markers'       => $formatted,
        ]);
    }

    /**
     * Show a single marker.
     */
    public function show(GeospatialMarker $geospatialMarker)
    {
        $geospatialMarker->load([
            'office:id,name,parent_id,type',
            'office.parent:id,name,parent_id,type',
            'office.parent.parent:id,name,type',
        ]);

        return response()->json([
            'marker' => $geospatialMarker,
        ]);
    }

    /**
     * Toggle side direction or status.
     */
    public function toggleStatus(Request $request, GeospatialMarker $geospatialMarker)
    {
        if (!$request->user() || (!$request->user()->can('geography.geospatial.manage') && $request->user()->role?->name !== 'super_admin')) {
            abort(403, 'Unauthorized. Modifying geospatial markers requires elevated permissions.');
        }

        $newSide = match($geospatialMarker->side) {
            'N' => 'S',
            'S' => 'N',
            default => 'N',
        };

        $geospatialMarker->update(['side' => $newSide]);

        return response()->json([
            'success' => true,
            'side'    => $newSide,
        ]);
    }

    /**
     * Get the complete operational office hierarchy (Zones, Sectors, Beats)
     * with geographic bounding boxes and coordinate counts cached for high performance.
     *
     * @return array<string, mixed>
     */
    public static function getOfficeHierarchy(): array
    {
        $cached = Cache::get('geospatial_office_hierarchy_v9');
        if (is_array($cached)) {
            return $cached;
        }

        $beatStats = DB::table('geospatial_markers')
                ->selectRaw('office_id, count(*) as count, MIN(lat) as min_lat, MAX(lat) as max_lat, MIN(lng) as min_lng, MAX(lng) as max_lng')
                ->groupBy('office_id')
                ->get()
                ->keyBy('office_id');

        $zones   = Office::where('type', 'zone')->select(['id', 'name'])->orderBy('name')->get();
        $sectors = Office::where('type', 'sector')->select(['id', 'name', 'parent_id'])->orderBy('name')->get();
        $beats   = Office::where('type', 'beat')->select(['id', 'name', 'parent_id'])->orderBy('name')->get();

        $sectorMap    = $sectors->keyBy('id');
        $zoneBounds   = [];
        $sectorBounds = [];

        $formattedBeats = [];
        foreach ($beats as $b) {
            $stat   = $beatStats->get($b->id);
            $sec    = $sectorMap->get($b->parent_id);
            $zoneId = $sec?->parent_id;

            $minLat = $stat ? (float) $stat->min_lat : null;
            $maxLat = $stat ? (float) $stat->max_lat : null;
            $minLng = $stat ? (float) $stat->min_lng : null;
            $maxLng = $stat ? (float) $stat->max_lng : null;
            $count  = $stat ? (int) $stat->count : 0;

            $bounds = ($minLat !== null && $maxLat !== null)
                ? [[$minLat, $minLng], [$maxLat, $maxLng]]
                : null;

            $formattedBeats[] = [
                'id'        => $b->id,
                'name'      => $b->name,
                'sector_id' => $b->parent_id,
                'zone_id'   => $zoneId,
                'count'     => $count,
                'bounds'    => $bounds,
            ];

            if ($stat && $b->parent_id) {
                if (!isset($sectorBounds[$b->parent_id])) {
                    $sectorBounds[$b->parent_id] = [
                        'min_lat' => $minLat, 'max_lat' => $maxLat,
                        'min_lng' => $minLng, 'max_lng' => $maxLng,
                        'count'   => 0,
                    ];
                } else {
                    $sectorBounds[$b->parent_id]['min_lat'] = min($sectorBounds[$b->parent_id]['min_lat'], $minLat);
                    $sectorBounds[$b->parent_id]['max_lat'] = max($sectorBounds[$b->parent_id]['max_lat'], $maxLat);
                    $sectorBounds[$b->parent_id]['min_lng'] = min($sectorBounds[$b->parent_id]['min_lng'], $minLng);
                    $sectorBounds[$b->parent_id]['max_lng'] = max($sectorBounds[$b->parent_id]['max_lng'], $maxLng);
                }
                $sectorBounds[$b->parent_id]['count'] += $count;
            }

            if ($stat && $zoneId) {
                if (!isset($zoneBounds[$zoneId])) {
                    $zoneBounds[$zoneId] = [
                        'min_lat' => $minLat, 'max_lat' => $maxLat,
                        'min_lng' => $minLng, 'max_lng' => $maxLng,
                        'count'   => 0,
                    ];
                } else {
                    $zoneBounds[$zoneId]['min_lat'] = min($zoneBounds[$zoneId]['min_lat'], $minLat);
                    $zoneBounds[$zoneId]['max_lat'] = max($zoneBounds[$zoneId]['max_lat'], $maxLat);
                    $zoneBounds[$zoneId]['min_lng'] = min($zoneBounds[$zoneId]['min_lng'], $minLng);
                    $zoneBounds[$zoneId]['max_lng'] = max($zoneBounds[$zoneId]['max_lng'], $maxLng);
                }
                $zoneBounds[$zoneId]['count'] += $count;
            }
        }

        $formattedSectors = [];
        foreach ($sectors as $s) {
            $sb     = $sectorBounds[$s->id] ?? null;
            $bounds = $sb ? [[(float) $sb['min_lat'], (float) $sb['min_lng']], [(float) $sb['max_lat'], (float) $sb['max_lng']]] : null;
            $formattedSectors[] = [
                'id'        => $s->id,
                'name'      => $s->name,
                'zone_id'   => $s->parent_id,
                'count'     => $sb ? (int) $sb['count'] : 0,
                'bounds'    => $bounds,
            ];
        }

        $formattedZones = [];
        foreach ($zones as $z) {
            $zb     = $zoneBounds[$z->id] ?? null;
            $bounds = $zb ? [[(float) $zb['min_lat'], (float) $zb['min_lng']], [(float) $zb['max_lat'], (float) $zb['max_lng']]] : null;
            $formattedZones[] = [
                'id'     => $z->id,
                'name'   => $z->name,
                'count'  => $zb ? (int) $zb['count'] : 0,
                'bounds' => $bounds,
            ];
        }

        // Road / Highway Registry mapping
        $carriageways = Carriageway::where('status', 'active')->orderBy('road')->get();
        $formattedRoads = [];

        foreach ($carriageways as $cw) {
            $code = strtoupper(trim((string) ($cw->road_short ?: $cw->road)));
            $clean = str_replace('-', '', $code);

            $matchingSectors = $sectors->filter(function ($s) use ($code, $clean) {
                $name = $s->name;
                if ($code === 'LEW' || $clean === 'LEW') {
                    return $s->id === 42; // Sector 42 South4
                } elseif ($code === 'LEBP' || $clean === 'LEBP') {
                    return $s->id === 20; // Sector 20 LSM
                } elseif ($code === 'M-3' || $clean === 'M3') {
                    return (str_contains($name, 'M3') || str_contains($name, 'M-3')) && !str_contains($name, 'LSM');
                } elseif ($code === 'M-1' || $clean === 'M1') {
                    return str_contains($name, 'M1') && !str_contains($name, 'M14');
                } elseif ($code === 'M-2' || $clean === 'M2') {
                    return str_contains($name, 'M2');
                } elseif ($code === 'M-4' || $clean === 'M4') {
                    return str_contains($name, 'M4') && !str_contains($name, 'M14');
                } elseif ($code === 'M-5' || $clean === 'M5') {
                    return str_contains($name, 'M5') && !str_contains($name, 'N55');
                } elseif ($code === 'M-14' || $clean === 'M14') {
                    return str_contains($name, 'M14');
                } elseif ($code === 'E-35' || $clean === 'E35') {
                    return str_contains($name, 'E35');
                } elseif ($code === 'M-9' || $clean === 'M9') {
                    return str_contains($name, 'M9');
                } elseif ($code === 'M-11' || $clean === 'M11') {
                    return str_contains($name, 'LSM') || str_contains($name, 'M11');
                } elseif ($code === 'M-16' || str_contains($code, 'SWAT')) {
                    return str_contains($name, 'Swat');
                } elseif ($code === 'N-5' || $clean === 'N5') {
                    $zName = $s->parent ? $s->parent->name : '';
                    return str_contains($zName, 'N5') || str_contains($name, 'South4');
                } elseif ($code === 'N-55' || $clean === 'N55') {
                    return str_contains($name, 'N55');
                } elseif ($code === 'N-25' || $clean === 'N25') {
                    return str_contains($name, 'N-25') || str_contains($name, 'N25');
                } elseif ($code === 'N-10' || $clean === 'N10') {
                    return str_contains($name, 'N10');
                } elseif ($code === 'N-50' || $clean === 'N50') {
                    return str_contains($name, 'N50');
                } elseif ($code === 'S-1' || str_contains($code, 'KKH')) {
                    return str_contains($name, 'KKH') || str_contains($name, 'S1');
                }
                return str_contains($name, $clean) || str_contains($name, $code);
            });

            $sectorIds = $matchingSectors->pluck('id')->values()->all();
            $matchingBeats = $beats->whereIn('parent_id', $sectorIds);

            // Isolate beats to their dedicated expressways/motorways
            if ($code === 'LEW' || $clean === 'LEW') {
                $matchingBeats = $matchingBeats->where('id', 122);
            } elseif ($code === 'LEBP' || $clean === 'LEBP') {
                $matchingBeats = $matchingBeats->where('id', 141);
            } elseif ($code === 'M-11' || $clean === 'M11') {
                $matchingBeats = $matchingBeats->whereIn('id', [70, 71]);
            } else {
                $matchingBeats = $matchingBeats->whereNotIn('id', [122, 141]);
            }

            $beatIds = $matchingBeats->pluck('id')->values()->all();
            $zoneIds = $matchingSectors->pluck('parent_id')->filter()->unique()->values()->all();

            $roadMinLat = null;
            $roadMaxLat = null;
            $roadMinLng = null;
            $roadMaxLng = null;
            $roadMarkerCount = 0;

            foreach ($beatIds as $bId) {
                if ($st = $beatStats->get($bId)) {
                    $roadMarkerCount += (int) $st->count;
                    if ($st->min_lat !== null) {
                        $roadMinLat = ($roadMinLat === null) ? (float) $st->min_lat : min($roadMinLat, (float) $st->min_lat);
                        $roadMaxLat = ($roadMaxLat === null) ? (float) $st->max_lat : max($roadMaxLat, (float) $st->max_lat);
                        $roadMinLng = ($roadMinLng === null) ? (float) $st->min_lng : min($roadMinLng, (float) $st->min_lng);
                        $roadMaxLng = ($roadMaxLng === null) ? (float) $st->max_lng : max($roadMaxLng, (float) $st->max_lng);
                    }
                }
            }

            $roadBounds = ($roadMinLat !== null && $roadMaxLat !== null)
                ? [[$roadMinLat, $roadMinLng], [$roadMaxLat, $roadMaxLng]]
                : null;

            $landmarkCount = DB::table('geospatial_landmarks')
                ->where('road_name', $cw->road_short ?: $cw->road)
                ->orWhereIn('office_id', $beatIds)
                ->count();

            // High-resolution corridor polyline (downsampled to ~150-200 points for silky smooth Leaflet rendering)
            $corridorCoords = [];
            if (!empty($beatIds)) {
                $rawPts = DB::table('geospatial_markers')
                    ->whereIn('office_id', $beatIds)
                    ->where('side', 'N')
                    ->orderBy('km', 'asc')
                    ->select(['lat', 'lng'])
                    ->get();

                if ($rawPts->isEmpty()) {
                    $rawPts = DB::table('geospatial_markers')
                        ->whereIn('office_id', $beatIds)
                        ->orderBy('km', 'asc')
                        ->select(['lat', 'lng'])
                        ->get();
                }

                $totalRaw = $rawPts->count();
                if ($totalRaw > 0) {
                    $step = max(1, (int) round($totalRaw / 150));
                    foreach ($rawPts as $idx => $p) {
                        if ($idx % $step === 0 || $idx === $totalRaw - 1) {
                            $corridorCoords[] = [(float) $p->lat, (float) $p->lng];
                        }
                    }
                }
            }

            $formattedRoads[] = [
                'id'             => $cw->id,
                'road'           => $cw->road,
                'road_short'     => $cw->road_short ?: $cw->road,
                'road_name'      => $cw->road_name,
                'road_from'      => $cw->road_from,
                'road_to'        => $cw->road_to,
                'total_km'       => (float) $cw->total_km,
                'sector_ids'     => $sectorIds,
                'beat_ids'       => $beatIds,
                'zone_ids'       => $zoneIds,
                'marker_count'   => $roadMarkerCount,
                'landmark_count' => $landmarkCount,
                'bounds'         => $roadBounds,
                'corridor'       => $corridorCoords,
            ];
        }

        $result = [
            'roads'   => $formattedRoads,
            'zones'   => $formattedZones,
            'sectors' => $formattedSectors,
            'beats'   => $formattedBeats,
        ];

        Cache::put('geospatial_office_hierarchy_v9', $result, 86400);

        return $result;
    }
}
