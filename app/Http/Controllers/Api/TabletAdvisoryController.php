<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TabletAdvisory;
use App\Services\Cache\ResilientCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TabletAdvisoryController extends Controller
{
    /**
     * Active Advisory Feed for Tablets (Public-facing, highly cached).
     * Supports ETag / 304 Not Modified, cursor pagination, and route filtering.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $route = $request->query('route') ? strtoupper(trim((string)$request->query('route'))) : null;
        $category = $request->query('category');
        $severity = $request->query('severity');
        $beforeId = $request->query('before_id') ? (int) $request->query('before_id') : null;
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $cacheKey = "tablet_advisories:feed:" . md5(json_encode([$route, $category, $severity, $beforeId, $limit]));

        // Retrieve from tagged cache or compute with stampede protection
        $data = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            60, // 60s base TTL
            function () use ($route, $category, $severity, $beforeId, $limit) {
                $query = TabletAdvisory::query()
                    ->published()
                    ->select([
                        'id',
                        'uuid',
                        'title',
                        'category',
                        'severity',
                        'route_name',
                        'carriageway',
                        'start_km',
                        'end_km',
                        'start_landmark',
                        'end_landmark',
                        'start_lat',
                        'start_lng',
                        'end_lat',
                        'end_lng',
                        'closure_type',
                        'visibility_meters',
                        'speed_limit_kmh',
                        'message',
                        'is_emergency',
                        'published_at',
                        'expires_at'
                    ]);

                if ($route) {
                    $query->forRoute($route);
                }
                if ($category) {
                    $query->where('category', $category);
                }
                if ($severity) {
                    $query->where('severity', $severity);
                }
                if ($beforeId) {
                    $query->where('id', '<', $beforeId);
                }

                $items = $query->orderBy('is_emergency', 'desc')
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get();

                $nextCursor = $items->count() === $limit ? $items->last()->id : null;

                return [
                    'advisories' => $items,
                    'next_cursor' => $nextCursor,
                    'count' => $items->count(),
                    'generated_at' => now()->toIso8601String(),
                ];
            }
        );

        // Compute ETag for conditional GET / 304 response
        $etag = '"' . md5(json_encode($data)) . '"';
        $clientEtag = $request->header('If-None-Match');

        if ($clientEtag && trim($clientEtag) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=15, must-revalidate');
        }

        return response()->json([
            'status' => 'success',
            'data' => $data['advisories'],
            'pagination' => [
                'next_cursor' => $data['next_cursor'],
                'limit' => $limit,
            ],
            'meta' => [
                'generated_at' => $data['generated_at'],
                'server_time' => now()->toIso8601String(),
            ]
        ])
        ->header('ETag', $etag)
        ->header('Cache-Control', 'public, max-age=15, must-revalidate')
        ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Emergency Broadcast Alerts (P1 Priority feed for active tablets).
     */
    public function emergencyAlerts(): JsonResponse
    {
        $cacheKey = 'tablet_advisories:emergency_alerts_broadcast';

        $alerts = ResilientCacheService::rememberWithStampedeProtection(
            $cacheKey,
            30, // 30s base TTL
            function () {
                return TabletAdvisory::query()
                    ->published()
                    ->emergency()
                    ->select([
                        'id',
                        'uuid',
                        'title',
                        'category',
                        'severity',
                        'route_name',
                        'carriageway',
                        'start_km',
                        'end_km',
                        'message',
                        'published_at'
                    ])
                    ->orderBy('published_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        );

        return response()->json([
            'status' => 'success',
            'emergency_count' => count($alerts),
            'alerts' => $alerts,
            'server_time' => now()->toIso8601String(),
        ])
        ->header('Cache-Control', 'public, max-age=10, must-revalidate');
    }

    /**
     * Single Advisory Detail.
     */
    public function show(string $uuid): JsonResponse
    {
        $uuid = trim($uuid);
        if (!Str::isUuid($uuid)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid advisory UUID format'], 400);
        }

        $advisory = TabletAdvisory::where('uuid', $uuid)->first();
        if (!$advisory) {
            return response()->json(['status' => 'error', 'message' => 'Advisory not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $advisory
        ]);
    }

    /**
     * Create New Advisory (Authenticated Staff/Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:weather,road_closure,congestion,fog,security,maintenance,emergency_alert',
            'severity' => 'required|string|in:info,advisory,warning,critical',
            'route_name' => 'required|string|max:50',
            'carriageway' => 'required|string|in:northbound,southbound,both,all',
            'start_km' => 'nullable|numeric|min:0',
            'end_km' => 'nullable|numeric|min:0',
            'message' => 'required|string|max:5000',
            'status' => 'nullable|string|in:draft,published,withdrawn',
            'is_emergency' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }
        $validated['created_by'] = $request->user()?->id;

        $advisory = TabletAdvisory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tablet advisory created successfully',
            'data' => $advisory
        ], 201);
    }

    /**
     * Update Advisory Status (Publish / Withdraw / Expire with instant cache invalidation).
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:draft,published,withdrawn,expired',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $advisory = TabletAdvisory::where('uuid', $uuid)->first();
        if (!$advisory) {
            return response()->json(['status' => 'error', 'message' => 'Advisory not found'], 404);
        }

        $newStatus = $request->input('status');
        $advisory->status = $newStatus;
        if ($newStatus === 'published' && !$advisory->published_at) {
            $advisory->published_at = now();
        }
        $advisory->save(); // Triggers boot saved hook -> instant flushAdvisoryCache()

        return response()->json([
            'status' => 'success',
            'message' => "Advisory status updated to {$newStatus} and cache invalidated instantly.",
            'data' => $advisory
        ]);
    }
}
