<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Carriageway;
use App\Models\GeospatialLandmark;
use App\Models\TabletAdvisory;
use App\Services\Cache\ResilientCacheService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TravelAdvisoryPublicApiController extends Controller
{
    /**
     * Get list of supported routes, terminals, and quick city pairs.
     * Heavily cached in Redis with ETag support.
     */
    public function routes(Request $request): Response|JsonResponse
    {
        $cacheKey = 'travel_advisory:public:routes:v1';

        $data = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            300, // 5 minutes TTL
            function () {
                $carriageways = Carriageway::whereIn('type', ['Motorway', 'Highway', 'Strategic Route'])
                    ->orderBy('type')
                    ->orderBy('road')
                    ->get();

                $countsByRoute = TabletAdvisory::active()
                    ->selectRaw("UPPER(REPLACE(route_name, '-', '')) as clean_route, COUNT(*) as total, SUM(CASE WHEN advisory_type = 'fog' THEN 1 ELSE 0 END) as fog_count, SUM(CASE WHEN advisory_type = 'road_closure' THEN 1 ELSE 0 END) as closure_count")
                    ->groupBy('clean_route')
                    ->get()
                    ->keyBy('clean_route');

                $routesList = $carriageways->map(function ($cw) use ($countsByRoute) {
                    $cleanKey = strtoupper(str_replace('-', '', (string)$cw->road));
                    $meta = $countsByRoute->get($cleanKey);

                    return [
                        'id'               => $cw->road_short ?: $cw->road,
                        'road_key'         => $cw->road,
                        'name'             => $cw->road_name ?: ($cw->type . ' ' . $cw->road_short),
                        'short_name'       => $cw->road_short ?: $cw->road,
                        'type'             => $cw->type, // Motorway, Highway
                        'origin'           => $cw->road_from,
                        'destination'      => $cw->road_to,
                        'total_km'         => (float) $cw->total_km,
                        'active_alerts'    => $meta ? (int) $meta->total : 0,
                        'fog_alerts'       => $meta ? (int) $meta->fog_count : 0,
                        'closure_alerts'   => $meta ? (int) $meta->closure_count : 0,
                    ];
                });

                // Curated popular journeys for quick 1-click commuter routing
                $popularJourneys = [
                    ['from' => 'Islamabad', 'to' => 'Lahore', 'road' => 'M-2', 'label' => 'Islamabad ⇄ Lahore (M-2)'],
                    ['from' => 'Peshawar', 'to' => 'Islamabad', 'road' => 'M-1', 'label' => 'Peshawar ⇄ Islamabad (M-1)'],
                    ['from' => 'Lahore', 'to' => 'Abdul Hakim', 'road' => 'M-3', 'label' => 'Lahore ⇄ Abdul Hakim (M-3)'],
                    ['from' => 'Pindi Bhattian', 'to' => 'Multan', 'road' => 'M-4', 'label' => 'Pindi Bhattian ⇄ Multan (M-4)'],
                    ['from' => 'Multan', 'to' => 'Sukkur', 'road' => 'M-5', 'label' => 'Multan ⇄ Sukkur (M-5)'],
                    ['from' => 'Karachi', 'to' => 'Hyderabad', 'road' => 'M-9', 'label' => 'Karachi ⇄ Hyderabad (M-9)'],
                    ['from' => 'Lahore', 'to' => 'Rawalpindi', 'road' => 'N-5', 'label' => 'Lahore ⇄ Rawalpindi (N-5 GT Road)'],
                ];

                return [
                    'routes'           => $routesList,
                    'popular_journeys' => $popularJourneys,
                    'timestamp'        => Carbon::now()->toIso8601String(),
                ];
            }
        );

        return $this->respondWithEtag($request, $data);
    }

    /**
     * Query Advisories for a selected corridor & route filter.
     * Matches the NHMP Humsafar split view (Route map + Route advisories cards).
     */
    public function query(Request $request): Response|JsonResponse
    {
        $road = $request->query('road', 'M-2');
        $from = $request->query('from');
        $to = $request->query('to');
        $type = $request->query('type', 'all'); // all, road_closure, fog, weather, traffic, road_works, diversion
        $direction = $request->query('direction', 'both'); // both, northbound, southbound

        $cleanRoad = strtoupper(trim((string)$road));
        $isAllRoads = ($cleanRoad === 'ALL' || empty($cleanRoad));

        $carriageway = null;
        if (! $isAllRoads) {
            $carriageway = Carriageway::where('road', str_replace('-', '', $cleanRoad))
                ->orWhere('road_short', $cleanRoad)
                ->first();
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($isAllRoads) {
            $from = $from ?: 'All Origins';
            $to = $to ?: 'All Destinations';
        } else {
            $from = $from ?: ($carriageway?->road_from ?: 'Islamabad');
            $to = $to ?: ($carriageway?->road_to ?: 'Lahore');
        }

        $cacheKey = 'travel_advisory:query:' . md5(json_encode([$road, $from, $to, $type, $direction]));

        $payload = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            60, // 60s TTL
            function () use ($road, $cleanRoad, $isAllRoads, $carriageway, $from, $to, $type, $direction) {
                // 1. Fetch active advisories for this route (or all active if ALL)
                $query = TabletAdvisory::active();
                if (! $isAllRoads) {
                    $query->forRoute($cleanRoad);
                }

                $allRouteAdvisories = $query
                    ->orderBy('published_at', 'desc')
                    ->get();

                // Compute category counters for pills/tabs
                $counters = [
                    'all'          => $allRouteAdvisories->count(),
                    'road_closure' => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'road_closure' || $a->category === 'road_closure')->count(),
                    'fog'          => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'fog' || $a->category === 'fog')->count(),
                    'weather'      => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'weather' || $a->category === 'weather')->count(),
                    'traffic'      => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'traffic' || $a->category === 'congestion')->count(),
                    'road_works'   => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'road_works' || $a->category === 'maintenance')->count(),
                    'diversion'    => $allRouteAdvisories->filter(fn($a) => $a->advisory_type === 'diversion')->count(),
                ];

                // 2. Filter advisories according to selected tab type
                $filtered = $allRouteAdvisories;
                if ($type && strtolower($type) !== 'all') {
                    $tab = strtolower($type);
                    $filtered = $filtered->filter(function ($item) use ($tab) {
                        if ($tab === 'fog') return $item->advisory_type === 'fog' || $item->category === 'fog';
                        if ($tab === 'road_closure' || $tab === 'closure') return $item->advisory_type === 'road_closure' || $item->category === 'road_closure';
                        if ($tab === 'road_works' || $tab === 'roadworks') return $item->advisory_type === 'road_works' || $item->category === 'maintenance';
                        if ($tab === 'traffic') return $item->advisory_type === 'traffic' || $item->category === 'congestion';
                        if ($tab === 'weather') return $item->advisory_type === 'weather' || $item->category === 'weather';
                        if ($tab === 'diversion' || $tab === 'diversions') return $item->advisory_type === 'diversion';
                        return true;
                    });
                }

                // 3. Filter by direction if specified
                if ($direction && strtolower($direction) !== 'both') {
                    $filtered = $filtered->filter(function ($item) use ($direction) {
                        return strtolower((string)$item->carriageway) === 'both' || strtolower((string)$item->carriageway) === strtolower($direction);
                    });
                }

                // Format advisories for frontend cards and map
                $advisoriesList = $filtered->values()->map(function ($adv) {
                    return $this->formatAdvisoryForClient($adv);
                });

                // Corridor route metadata
                if ($isAllRoads) {
                    $routeCode = 'ALL';
                    $routeName = 'All National Highways & Motorways';
                    $summaryBadge = $advisoriesList->count() > 0
                        ? "{$advisoriesList->count()} active advisories nationwide"
                        : 'Normal conditions across all routes';
                } else {
                    $routeCode = $carriageway ? ($carriageway->road_short ?: $carriageway->road) : $cleanRoad;
                    $routeName = $carriageway ? ($carriageway->road_name ?: ($cleanRoad . ' Motorway')) : ($cleanRoad . ' Motorway');
                    $summaryBadge = $advisoriesList->count() > 0
                        ? "{$advisoriesList->count()} advisories on your selected route"
                        : 'Normal conditions on selected route';
                }

                $earliestExpiry = $filtered->whereNotNull('expires_at')->min('expires_at');
                $earliestExpiryIso = null;
                $earliestExpiryPkt = null;
                if ($earliestExpiry) {
                    $c = $earliestExpiry instanceof Carbon ? $earliestExpiry : Carbon::parse($earliestExpiry);
                    $earliestExpiryIso = $c->toIso8601String();
                    $earliestExpiryPkt = $c->timezone('Asia/Karachi')->format('H:i \P\K\T');
                }

                return [
                    'route' => [
                        'code'             => $routeCode,
                        'name'             => $routeName,
                        'from'             => $from,
                        'to'               => $to,
                        'direction'        => $direction,
                        'advisories_count' => $advisoriesList->count(),
                        'summary_badge'    => $summaryBadge,
                        'last_updated'     => Carbon::now()->timezone('Asia/Karachi')->format('H:i \P\K\T'),
                        'expires_at'       => $earliestExpiryIso,
                        'expires_time'     => $earliestExpiryPkt ? "Expires {$earliestExpiryPkt}" : null,
                    ],
                    'counters'   => $counters,
                    'advisories' => $advisoriesList,
                    'sample_mode'=> true,
                ];
            }
        );

        return $this->respondWithEtag($request, $payload);
    }

    /**
     * Top Marquee Ticker Feed (Roadworks Radar UK style).
     * High-priority real-time advisory stream for top ticker and alert banners.
     */
    public function ticker(Request $request): Response|JsonResponse
    {
        $cacheKey = 'travel_advisory:public:ticker:v1';

        $data = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            60,
            function () {
                $advisories = TabletAdvisory::active()
                    ->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'warning' THEN 2 ELSE 3 END")
                    ->orderBy('published_at', 'desc')
                    ->take(10)
                    ->get();

                $items = $advisories->map(function ($adv) {
                    $badge = 'Live on ' . ($adv->route_name ?: 'Motorway');
                    $subType = match ($adv->advisory_type) {
                        'fog'          => 'Fog advisory',
                        'road_closure' => 'Road closed',
                        'road_works'   => 'Lane closed',
                        'traffic'      => 'Slow traffic',
                        'diversion'    => 'Diversion',
                        default        => 'Advisory',
                    };

                    $location = $adv->start_landmark ?: "KM {$adv->start_km}";
                    if ($adv->end_landmark) {
                        $location .= " to {$adv->end_landmark}";
                    }

                    $extra = '';
                    if ($adv->visibility_meters && $adv->visibility_meters < 200) {
                        $extra = " (Vis < {$adv->visibility_meters}m)";
                    } elseif ($adv->closure_type) {
                        $extra = " (" . str_replace('_', ' ', ucfirst($adv->closure_type)) . ")";
                    }

                    return [
                        'id'         => $adv->id,
                        'badge'      => $badge,
                        'type'       => $subType,
                        'severity'   => $adv->severity,
                        'route_name' => $adv->route_name,
                        'title'      => $adv->title,
                        'text'       => "{$location} - {$adv->title}{$extra}",
                        'full_text'  => "{$badge} | {$adv->title} ({$location}) - {$subType}{$extra}",
                        'link'       => "/travel-advisory?road=" . urlencode($adv->route_name ?: 'M-2'),
                    ];
                });

                return [
                    'ticker_items' => $items,
                    'count'        => $items->count(),
                    'updated_at'   => Carbon::now()->timezone('Asia/Karachi')->format('H:i \P\K\T'),
                ];
            }
        );

        return $this->respondWithEtag($request, $data);
    }

    /**
     * GeoJSON Endpoint: Corridor geometry and 3D hazard markers for Leaflet/Mapbox embed.
     */
    public function geojson(Request $request): Response|JsonResponse
    {
        $road = $request->query('road', 'M-2');
        $cacheKey = 'travel_advisory:geojson:' . md5((string)$road);

        $geojson = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            120,
            function () use ($road) {
                $cleanRoad = strtoupper(trim((string)$road));
                $isAllRoads = ($cleanRoad === 'ALL' || empty($cleanRoad));

                $query = TabletAdvisory::active()
                    ->whereNotNull('start_lat')
                    ->whereNotNull('start_lng');

                if (! $isAllRoads) {
                    $query->forRoute($cleanRoad);
                }

                $advisories = $query->get();

                $features = [];

                foreach ($advisories as $adv) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float)$adv->start_lng, (float)$adv->start_lat],
                        ],
                        'properties' => [
                            'id'                => $adv->id,
                            'title'             => $adv->title,
                            'advisory_type'     => $adv->advisory_type ?: 'warning',
                            'severity'          => $adv->severity,
                            'location'          => $adv->start_landmark ?: "KM {$adv->start_km}",
                            'end_location'      => $adv->end_landmark ?: "KM {$adv->end_km}",
                            'visibility_meters' => $adv->visibility_meters,
                            'speed_limit_kmh'   => $adv->speed_limit_kmh,
                            'closure_type'      => $adv->closure_type,
                            'message'           => $adv->message,
                            'icon_type'         => $this->mapIconType($adv->advisory_type),
                            'pin_color'         => $this->mapPinColor($adv->advisory_type, $adv->severity),
                            'published_at'      => $adv->published_at?->toIso8601String(),
                            'expires_at'        => $adv->expires_at?->toIso8601String(),
                        ],
                    ];

                    // If end coordinates exist, create a span LineString feature
                    if ($adv->end_lat && $adv->end_lng) {
                        $features[] = [
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => [
                                    [(float)$adv->start_lng, (float)$adv->start_lat],
                                    [(float)$adv->end_lng, (float)$adv->end_lat],
                                ],
                            ],
                            'properties' => [
                                'parent_advisory_id' => $adv->id,
                                'type'               => 'advisory_span',
                                'advisory_type'      => $adv->advisory_type,
                                'severity'           => $adv->severity,
                                'color'              => $this->mapSpanColor($adv->advisory_type),
                            ],
                        ];
                    }
                }

                return [
                    'type'     => 'FeatureCollection',
                    'features' => $features,
                ];
            }
        );

        return $this->respondWithEtag($request, $geojson);
    }

    /**
     * Interchanges and Landmarks for auto-suggest in Route Selector.
     */
    public function landmarks(Request $request): JsonResponse
    {
        $road = $request->query('road', 'M-2');
        $landmarks = GeospatialLandmark::where('road_name', $road)
            ->whereNotNull('location_name')
            ->select('id', 'location_name', 'km_marker', 'nearby_cities', 'bound_direction')
            ->orderByRaw('CAST(km_marker AS UNSIGNED) DESC')
            ->get();

        return response()->json([
            'road'      => $road,
            'landmarks' => $landmarks,
        ]);
    }

    /**
     * Helper to format an advisory model into rich client-ready presentation payload.
     */
    private function formatAdvisoryForClient(TabletAdvisory $adv): array
    {
        $directionLabel = match (strtolower((string)$adv->carriageway)) {
            'northbound' => 'Islamabad-bound',
            'southbound' => 'Lahore-bound',
            'both'       => 'Both directions',
            default      => ucfirst((string)$adv->carriageway) . '-bound',
        };

        $locationLabel = $adv->route_name ?: 'Motorway';
        if ($adv->start_landmark && $adv->end_landmark) {
            $locationLabel .= " · {$adv->start_landmark} to {$adv->end_landmark}";
        } elseif ($adv->start_landmark) {
            $locationLabel .= " · Near {$adv->start_landmark}";
        } elseif ($adv->start_km && $adv->end_km) {
            $locationLabel .= " · KM {$adv->start_km} to KM {$adv->end_km}";
        } elseif ($adv->start_km) {
            $locationLabel .= " · Near KM {$adv->start_km}";
        }

        $categoryDisplay = match ($adv->advisory_type) {
            'fog'          => 'Fog & visibility',
            'road_works'   => 'Road works',
            'traffic'      => 'Traffic',
            'road_closure' => 'Road closure',
            'diversion'    => 'Diversions',
            'weather'      => 'Weather',
            default        => ucfirst(str_replace('_', ' ', $adv->advisory_type ?: 'General Alert')),
        };

        $updatedPkt = $adv->published_at
            ? $adv->published_at->timezone('Asia/Karachi')->format('H:i \P\K\T')
            : Carbon::now()->timezone('Asia/Karachi')->format('H:i \P\K\T');

        $expiresPkt = $adv->expires_at
            ? $adv->expires_at->timezone('Asia/Karachi')->format('H:i \P\K\T')
            : null;

        return [
            'id'                 => $adv->id,
            'uuid'               => $adv->uuid,
            'title'              => $adv->title,
            'advisory_type'      => $adv->advisory_type,
            'category'           => $adv->category,
            'category_display'   => $categoryDisplay,
            'severity'           => $adv->severity,
            'route_name'         => $adv->route_name,
            'location_display'   => $locationLabel,
            'direction_label'    => $directionLabel,
            'carriageway'        => $adv->carriageway,
            'start_landmark'     => $adv->start_landmark,
            'end_landmark'       => $adv->end_landmark,
            'start_km'           => (float)$adv->start_km,
            'end_km'             => (float)$adv->end_km,
            'visibility_meters'  => $adv->visibility_meters,
            'speed_limit_kmh'    => $adv->speed_limit_kmh,
            'closure_type'       => $adv->closure_type,
            'message'            => $adv->message,
            'icon'               => $this->mapIconType($adv->advisory_type),
            'badge_color'        => $this->mapPinColor($adv->advisory_type, $adv->severity),
            'start_coords'       => ($adv->start_lat && $adv->start_lng) ? [(float)$adv->start_lat, (float)$adv->start_lng] : null,
            'end_coords'         => ($adv->end_lat && $adv->end_lng) ? [(float)$adv->end_lat, (float)$adv->end_lng] : null,
            'published_at'       => $adv->published_at?->toIso8601String(),
            'expires_at'         => $adv->expires_at?->toIso8601String(),
            'expires_at_pkt'     => $expiresPkt,
            'expires_time'       => $expiresPkt ? "Expires {$expiresPkt}" : null,
            'updated_time'       => "Updated {$updatedPkt}",
            'sample_tag'         => 'Sample',
        ];
    }

    private function mapIconType(?string $type): string
    {
        return match ($type) {
            'fog'          => 'fog',
            'road_works'   => 'road_works',
            'traffic'      => 'traffic',
            'road_closure' => 'road_closure',
            'diversion'    => 'diversion',
            'weather'      => 'weather',
            default        => 'alert',
        };
    }

    private function mapPinColor(?string $type, ?string $severity): string
    {
        if ($severity === 'critical' || $type === 'road_closure') {
            return '#dc2626'; // Red
        }
        if ($type === 'fog') {
            return '#d97706'; // Amber / Gold
        }
        if ($type === 'road_works') {
            return '#f97316'; // Orange
        }
        if ($type === 'traffic') {
            return '#ef4444'; // Red-orange
        }
        return '#2563eb'; // Blue
    }

    private function mapSpanColor(?string $type): string
    {
        return match ($type) {
            'fog'          => '#eab308',
            'road_closure' => '#ef4444',
            'road_works'   => '#f97316',
            'traffic'      => '#f87171',
            default        => '#3b82f6',
        };
    }

    /**
     * ETag and conditional 304 Not Modified handler.
     */
    private function respondWithEtag(Request $request, array $data): Response|JsonResponse
    {
        $serialized = json_encode($data);
        $etag = '"' . md5($serialized) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, [
                'ETag'          => $etag,
                'Cache-Control' => 'public, max-age=60, stale-while-revalidate=30',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response()->json($data, 200, [
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=30',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
