<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FreeRouteService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey  = (string) config('services.freeroute.api_key', env('FREEROUTE_API_KEY', ''));
        $this->baseUrl = rtrim((string) config('services.freeroute.base_url', 'https://api.maps.freeroute.org'), '/');
        $this->timeout = (int) config('services.freeroute.timeout', 30);
    }

    /**
     * Get high-resolution driving directions GeoJSON line between waypoints.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates Array of [lon, lat] pairs (minimum 2 points)
     * @param string $profile Transport profile: driving-car, driving-hgv, cycling-regular, foot-walking
     * @param bool $useCache Whether to cache response
     * @return array<string, mixed>|null GeoJSON FeatureCollection or null on failure
     */
    public function getDirections(array $coordinates, string $profile = 'driving-car', bool $useCache = true): ?array
    {
        if (count($coordinates) < 2) {
            Log::warning('FreeRouteService: At least 2 coordinates required.');
            return null;
        }

        $cacheKey = 'freeroute_dir_' . md5($profile . '_' . json_encode($coordinates));
        if ($useCache && Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        if (empty($this->apiKey)) {
            return $this->getOsmDirectionsFallback($coordinates, $useCache ? $cacheKey : null);
        }

        try {
            $url = "{$this->baseUrl}/v1/directions/{$profile}";
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout($this->timeout)->post($url, [
                'coordinates' => $coordinates,
                'format'      => 'geojson',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($useCache) {
                    Cache::put($cacheKey, $data, 86400 * 7); // Cache for 7 days
                }
                return $data;
            }

            Log::info('FreeRoute directions returned non-200, switching to OSM routing fallback', [
                'status' => $response->status(),
                'coords' => count($coordinates),
            ]);

            return $this->getOsmDirectionsFallback($coordinates, $useCache ? $cacheKey : null);
        } catch (\Throwable $e) {
            Log::warning('FreeRoute directions exception, falling back to OSM router', ['error' => $e->getMessage()]);
            return $this->getOsmDirectionsFallback($coordinates, $useCache ? $cacheKey : null);
        }
    }

    /**
     * Fallback high-resolution router using OSRM (OpenStreetMap Routing Engine).
     * Provides accurate motorway centerline geometry when region is outside FreeRoute local graph.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates
     * @param string|null $cacheKey
     * @return array<string, mixed>|null
     */
    protected function getOsmDirectionsFallback(array $coordinates, ?string $cacheKey = null): ?array
    {
        try {
            $coordStrings = array_map(function ($c) {
                return round($c[0], 6) . ',' . round($c[1], 6);
            }, $coordinates);
            $coordParam = implode(';', $coordStrings);

            $url = "https://router.project-osrm.org/route/v1/driving/{$coordParam}?overview=full&geometries=geojson";
            $response = Http::withHeaders([
                'User-Agent' => 'NHMP-ERP-GIS/1.0',
                'Accept'     => 'application/json',
            ])->timeout($this->timeout)->retry(3, 500)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === 'Ok' && !empty($data['routes'][0]['geometry']['coordinates'])) {
                    $route = $data['routes'][0];
                    $featureCollection = [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => [
                                    'summary' => [
                                        'distance' => (float) ($route['distance'] ?? 0),
                                        'duration' => (float) ($route['duration'] ?? 0),
                                    ],
                                ],
                                'geometry' => [
                                    'type' => 'LineString',
                                    'coordinates' => $route['geometry']['coordinates'],
                                ],
                            ],
                        ],
                    ];

                    if ($cacheKey) {
                        Cache::put($cacheKey, $featureCollection, 86400 * 7);
                    }
                    return $featureCollection;
                }
            }

            Log::error('OSM directions fallback failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('OSM directions fallback exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Search geocoding to find coordinates of a location or landmark.
     */
    public function geocode(string $query): ?array
    {
        $cacheKey = 'freeroute_geo_' . md5($query);
        if (Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        try {
            $url = "{$this->baseUrl}/v1/geocode/search";
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->get($url, [
                'q' => $query,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, 86400 * 30);
                return $data;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('FreeRouteService geocode exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Reverse geocoding from latitude and longitude.
     */
    public function reverseGeocode(float $lat, float $lon): ?array
    {
        $cacheKey = "freeroute_rev_{$lat}_{$lon}";
        if (Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        try {
            $url = "{$this->baseUrl}/v1/geocode/reverse";
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->get($url, [
                'lat' => $lat,
                'lon' => $lon,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, 86400 * 30);
                return $data;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('FreeRouteService reverseGeocode exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
