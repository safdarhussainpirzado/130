<?php

namespace App\Http\Controllers;

use App\Models\Carriageway;
use App\Models\GeospatialLandmark;
use App\Models\GeospatialMarker;
use App\Models\Office;
use App\Models\Role;
use App\Models\TabletAdvisory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TravelAdvisoryController extends Controller
{
    /**
     * Authorize that the current authenticated user has sector operator access.
     * Restricted to sector_admin role (and super_admin for system management).
     */
    private function authorizeSectorAdmin(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $roleName = $user->role?->name;
        if (! in_array($roleName, [Role::SECTOR_ADMIN, Role::SUPER_ADMIN], true) && ! ($user instanceof \App\Models\User && $user->hasPermission('travel_advisory.view'))) {
            abort(403, 'Unauthorized. Access to Travel Advisory operations is restricted to sector operators (sector_admin).');
        }
    }
    /**
     * Public Commuter Travel Advisory Portal (matches Humsafar mockup + Roadworks Radar UK).
     */
    public function publicPortal(Request $request): View
    {
        $road = $request->query('road', 'M-2');
        $from = $request->query('from', 'Islamabad');
        $to = $request->query('to', 'Lahore');
        $selectedType = $request->query('type', 'all');
        $direction = $request->query('direction', 'both');

        // Available Carriageways
        $carriageways = Cache::remember('travel_advisory:all_carriageways', 600, function () {
            return Carriageway::whereIn('type', ['Motorway', 'Highway', 'Strategic Route'])
                ->orderBy('type')
                ->orderBy('road')
                ->get();
        });

        // Curated popular journeys
        $popularJourneys = [
            ['from' => 'Islamabad', 'to' => 'Lahore', 'road' => 'M-2'],
            ['from' => 'Peshawar', 'to' => 'Islamabad', 'road' => 'M-1'],
            ['from' => 'Lahore', 'to' => 'Abdul Hakim', 'road' => 'M-3'],
            ['from' => 'Pindi Bhattian', 'to' => 'Multan', 'road' => 'M-4'],
            ['from' => 'Multan', 'to' => 'Sukkur', 'road' => 'M-5'],
            ['from' => 'Karachi', 'to' => 'Sukkur', 'road' => 'M-9'],
            ['from' => 'Karachi', 'to' => 'Hyderabad', 'road' => 'M-9'],
            ['from' => 'Lahore', 'to' => 'Rawalpindi', 'road' => 'N-5'],
        ];

        // Active advisories for the chosen road
        $cleanRoad = strtoupper(trim((string)$road));
        $allRoadAdvisories = TabletAdvisory::active()
            ->forRoute($cleanRoad)
            ->orderBy('published_at', 'desc')
            ->get();

        // Counters for category tabs
        $counters = [
            'all'          => $allRoadAdvisories->count(),
            'road_closure' => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'road_closure' || $a->category === 'road_closure')->count(),
            'fog'          => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'fog' || $a->category === 'fog')->count(),
            'weather'      => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'weather' || $a->category === 'weather')->count(),
            'traffic'      => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'traffic' || $a->category === 'congestion')->count(),
            'road_works'   => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'road_works' || $a->category === 'maintenance')->count(),
            'diversion'    => $allRoadAdvisories->filter(fn($a) => $a->advisory_type === 'diversion')->count(),
        ];

        // Filtered advisories for right accordion list
        $advisories = $allRoadAdvisories;
        if ($selectedType && strtolower($selectedType) !== 'all') {
            $tab = strtolower($selectedType);
            $advisories = $advisories->filter(function ($item) use ($tab) {
                if ($tab === 'fog') return $item->advisory_type === 'fog' || $item->category === 'fog';
                if ($tab === 'road_closure') return $item->advisory_type === 'road_closure' || $item->category === 'road_closure';
                if ($tab === 'road_works') return $item->advisory_type === 'road_works' || $item->category === 'maintenance';
                if ($tab === 'traffic') return $item->advisory_type === 'traffic' || $item->category === 'congestion';
                if ($tab === 'weather') return $item->advisory_type === 'weather' || $item->category === 'weather';
                if ($tab === 'diversion') return $item->advisory_type === 'diversion';
                return true;
            });
        }

        // Live Ticker items (High priority across all motorways)
        $tickerItems = TabletAdvisory::active()
            ->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'warning' THEN 2 ELSE 3 END")
            ->orderBy('published_at', 'desc')
            ->take(8)
            ->get();

        return view('public.travel-advisory.index', [
            'carriageways'     => $carriageways,
            'popularJourneys'  => $popularJourneys,
            'currentRoad'      => $cleanRoad,
            'from'             => $from,
            'to'               => $to,
            'direction'        => $direction,
            'selectedType'     => $selectedType,
            'counters'         => $counters,
            'advisories'       => $advisories,
            'allAdvisories'    => $allRoadAdvisories,
            'tickerItems'      => $tickerItems,
            'updatedTime'      => Carbon::now()->timezone('Asia/Karachi')->format('H:i \P\K\T'),
        ]);
    }

    /**
     * Wireless Operator Rapid Incident Data Entry Hub.
     * Role-aware for Beat, Sector, and Zone wireless operators.
     */
    public function operatorCreate(Request $request): View
    {
        $this->authorizeSectorAdmin();

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $userOffice = $user?->office ?? ($user instanceof \App\Models\User ? $user->activeScopes()->with('office.parent.parent')->first()?->office : null);

        // Determine operator tier based on office type or role
        $tier = 'beat';
        if ($user?->role?->name === Role::SUPER_ADMIN || $user?->role?->name === 'national_admin' || !$userOffice) {
            $tier = 'national';
        } elseif ($userOffice) {
            $tier = strtolower((string)$userOffice->type); // 'zone', 'sector', 'beat'
        }

        $hierarchy = GeospatialMarkerController::getOfficeHierarchy();

        $landmarks = Cache::remember('travel_advisory:all_landmarks_by_office_v2', 3600, function () {
            return GeospatialLandmark::select(['id', 'office_id', 'location_name', 'km_numeric', 'road_name'])
                ->orderBy('km_numeric')
                ->get()
                ->groupBy('office_id');
        });

        $zones = Office::where('type', 'zone')->orderBy('name')->get();
        $carriageways = Carriageway::whereIn('type', ['Motorway', 'Highway', 'Strategic Route'])
            ->orderBy('road')
            ->get()
            ->map(fn($cw) => [
                'id'         => $cw->id,
                'road'       => $cw->road,
                'road_short' => $cw->road_short ?: $cw->road,
                'road_name'  => $cw->road_name,
                'road_from'  => $cw->road_from,
                'road_to'    => $cw->road_to,
                'total_km'   => (float)$cw->total_km,
                'type'       => $cw->type,
            ]);

        // Recent advisories entered by this operational unit
        $recentAdvisories = TabletAdvisory::orderBy('published_at', 'desc')
            ->take(10)
            ->get();

        return view('operations.travel-advisory.create', [
            'operator'         => $user,
            'operatorOffice'   => $userOffice,
            'operatorTier'     => $tier,
            'hierarchy'        => $hierarchy,
            'landmarks'        => $landmarks,
            'zones'            => $zones,
            'carriageways'     => $carriageways,
            'recentAdvisories' => $recentAdvisories,
        ]);
    }

    /**
     * Store new advisory from wireless operator rapid incident hub.
     * Auto-aggregates contiguous beat segments into unified corridors.
     */
    public function operatorStore(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeSectorAdmin();

        $validated = $request->validate([
            'advisory_type'     => 'required|in:fog,road_closure,road_works,traffic,weather,diversion',
            'severity'          => 'required|in:info,advisory,warning,critical',
            'route_name'        => 'required|string|max:50',
            'carriageway'       => 'required|in:both,northbound,southbound',
            'zone_id'           => 'nullable|exists:offices,id',
            'sector_id'         => 'nullable|exists:offices,id',
            'office_id'         => 'nullable|exists:offices,id',
            'start_km'          => 'required|numeric|min:0',
            'end_km'            => 'required|numeric|min:0',
            'start_landmark'    => 'nullable|string|max:100',
            'end_landmark'      => 'nullable|string|max:100',
            'visibility_meters' => 'nullable|integer|min:0|max:5000',
            'speed_limit_kmh'   => 'nullable|integer|min:0|max:150',
            'closure_type'      => 'nullable|in:full,lane_1,lane_2,shoulder,diversion',
            'title'             => 'required|string|max:150',
            'message'           => 'required|string|max:1000',
            'is_emergency'      => 'nullable|boolean',
            'duration_hours'    => 'nullable|numeric|min:0.5|max:72',
            'custom_expires_at' => 'nullable|date',
        ]);

        $user = Auth::user();
        $now = Carbon::now();
        
        if (!empty($validated['custom_expires_at'])) {
            $expiresAt = Carbon::parse($validated['custom_expires_at']);
        } else {
            $durationHours = (float) ($validated['duration_hours'] ?? 4.0);
            $expiresAt = $now->copy()->addMinutes((int)($durationHours * 60));
        }

        // Auto-compute coordinates from GeospatialMarker if available
        $cleanRoute = strtoupper(str_replace('-', '', $validated['route_name']));
        $startCoord = $this->resolveKmCoordinates($validated['office_id'], $validated['start_km']);
        $endCoord = $this->resolveKmCoordinates($validated['office_id'], $validated['end_km']);

        // Determine corridor key for aggregation (e.g. M2-FOG-KM210-KM245)
        $corridorKey = sprintf(
            '%s-%s-KM%03d-KM%03d-%s',
            $cleanRoute,
            strtoupper($validated['carriageway']),
            (int)$validated['start_km'],
            (int)$validated['end_km'],
            strtoupper($validated['advisory_type'])
        );

        // Map enum category
        $category = match ($validated['advisory_type']) {
            'fog'          => 'fog',
            'road_closure' => 'road_closure',
            'road_works'   => 'maintenance',
            'traffic'      => 'congestion',
            'weather'      => 'weather',
            'diversion'    => 'road_closure',
            default        => 'advisory',
        };

        // Determine carriageway model ID
        $carriagewayModel = Carriageway::where('road', $cleanRoute)
            ->orWhere('road_short', $validated['route_name'])
            ->first();

        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'title'             => $validated['title'],
            'category'          => $category,
            'advisory_type'     => $validated['advisory_type'],
            'severity'          => $validated['severity'],
            'route_name'        => $validated['route_name'],
            'carriageway'       => $validated['carriageway'],
            'carriageway_id'    => $carriagewayModel?->id,
            'zone_id'           => $validated['zone_id'] ?? $user?->office?->parent?->parent_id,
            'sector_id'         => $validated['sector_id'] ?? $user?->office?->parent_id,
            'office_id'         => $validated['office_id'] ?? $user?->office_id,
            'start_km'          => $validated['start_km'],
            'end_km'            => $validated['end_km'],
            'start_landmark'    => $validated['start_landmark'],
            'end_landmark'      => $validated['end_landmark'],
            'visibility_meters' => $validated['visibility_meters'] ?? null,
            'speed_limit_kmh'   => $validated['speed_limit_kmh'] ?? null,
            'closure_type'      => $validated['closure_type'] ?? null,
            'message'           => $validated['message'],
            'status'            => 'published',
            'is_emergency'      => $validated['is_emergency'] ?? ($validated['severity'] === 'critical'),
            'reported_by_tier'  => $user?->office?->type ?? 'beat',
            'corridor_key'      => $corridorKey,
            'start_lat'         => $startCoord['lat'] ?? null,
            'start_lng'         => $startCoord['lng'] ?? null,
            'end_lat'           => $endCoord['lat'] ?? null,
            'end_lng'           => $endCoord['lng'] ?? null,
            'published_at'      => $now,
            'expires_at'        => $expiresAt,
            'created_by'        => $user?->id ?? 1,
        ]);

        TabletAdvisory::flushAdvisoryCache();

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'  => true,
                'message'  => "Travel advisory '{$advisory->title}' broadcasted successfully across Humsafar Network.",
                'advisory' => $advisory,
            ]);
        }

        return redirect()->route('ops.travel-advisory.create')
            ->with('success', "Travel advisory '{$advisory->title}' broadcasted successfully across Humsafar Network.");
    }

    /**
     * Wireless Operator Active Advisories Management Grid.
     * Accessible by wireless control operators to view, filter, extend, or resolve live corridor alerts.
     */
    public function operatorIndex(Request $request): View|JsonResponse
    {
        $this->authorizeSectorAdmin();

        $road = $request->query('road', 'all');
        $status = $request->query('status', 'active');
        $type = $request->query('type', 'all');
        $severity = $request->query('severity', 'all');
        $search = $request->query('search');

        $query = TabletAdvisory::with(['office.parent.parent', 'carriagewayModel']);

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'resolved') {
            $query->where('status', 'resolved');
        } elseif ($status === 'expired') {
            $query->where('status', 'published')->where('expires_at', '<=', Carbon::now());
        }

        if ($road && strtolower($road) !== 'all') {
            $query->forRoute(strtoupper($road));
        }

        if ($type && strtolower($type) !== 'all') {
            $query->where('advisory_type', strtolower($type));
        }

        if ($severity && strtolower($severity) !== 'all') {
            $query->where('severity', strtolower($severity));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('start_landmark', 'like', "%{$search}%")
                  ->orWhere('end_landmark', 'like', "%{$search}%")
                  ->orWhere('corridor_key', 'like', "%{$search}%")
                  ->orWhere('route_name', 'like', "%{$search}%");
            });
        }

        $advisories = $query->orderBy('published_at', 'desc')->paginate(15);

        // Metrics for 3D filter stat cards
        $metrics = [
            'total_active'     => TabletAdvisory::active()->count(),
            'fog_alerts'       => TabletAdvisory::active()->where('advisory_type', 'fog')->count(),
            'road_closures'    => TabletAdvisory::active()->whereIn('advisory_type', ['road_closure', 'diversion'])->count(),
            'road_works'       => TabletAdvisory::active()->where('advisory_type', 'road_works')->count(),
            'critical_hazards' => TabletAdvisory::active()->where('severity', 'critical')->count(),
            'expired_resolved' => TabletAdvisory::where(function ($q) {
                $q->where('status', 'resolved')->orWhere('expires_at', '<=', Carbon::now());
            })->count(),
            'total_all'        => TabletAdvisory::count(),
        ];

        $formattedItems = collect($advisories->items())->map(function ($item) {
            $now = Carbon::now();
            $isResolved = ($item->status === 'resolved');
            $isExpired = (!$isResolved && $item->expires_at && $item->expires_at->isPast());
            $isLive = (!$isResolved && !$isExpired);

            return [
                'id'                  => $item->id,
                'uuid'                => $item->uuid,
                'title'               => $item->title,
                'message'             => $item->message,
                'category'            => $item->category,
                'advisory_type'       => $item->advisory_type,
                'severity'            => $item->severity,
                'route_name'          => $item->route_name,
                'carriageway'         => $item->carriageway,
                'start_km'            => (float) $item->start_km,
                'end_km'              => (float) ($item->end_km ?: $item->start_km),
                'start_landmark'      => $item->start_landmark,
                'end_landmark'        => $item->end_landmark,
                'visibility_meters'   => $item->visibility_meters,
                'speed_limit_kmh'     => $item->speed_limit_kmh,
                'closure_type'        => $item->closure_type,
                'status'              => $item->status,
                'is_emergency'        => (bool) $item->is_emergency,
                'reported_by_tier'    => $item->reported_by_tier ?: 'Operator',
                'office_name'         => $item->office?->name ?: 'Wireless Station',
                'sector_name'         => $item->office?->parent?->name ?: 'Sector',
                'zone_name'           => $item->office?->parent?->parent?->name ?: 'Zone',
                'is_live'             => $isLive,
                'is_expired'          => $isExpired,
                'is_resolved'         => $isResolved,
                'expires_at_human'    => $item->expires_at ? $item->expires_at->timezone('Asia/Karachi')->diffForHumans() : 'N/A',
                'published_at_human'  => $item->published_at ? $item->published_at->timezone('Asia/Karachi')->diffForHumans() : 'Just now',
            ];
        })->all();

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'    => true,
                'items'      => $formattedItems,
                'pagination' => [
                    'current_page' => $advisories->currentPage(),
                    'last_page'    => $advisories->lastPage(),
                    'per_page'     => $advisories->perPage(),
                    'total'        => $advisories->total(),
                    'from'         => $advisories->firstItem() ?? 0,
                    'to'           => $advisories->lastItem() ?? 0,
                ],
                'metrics'    => $metrics,
            ]);
        }

        $carriageways = Carriageway::whereIn('type', ['Motorway', 'Highway'])
            ->orderBy('road')
            ->get();

        return view('operations.travel-advisory.index', [
            'initialItems'  => $formattedItems,
            'advisories'    => $advisories,
            'metrics'       => $metrics,
            'carriageways'  => $carriageways,
            'currentRoad'   => $road,
            'currentStatus' => $status,
            'currentType'   => $type,
            'search'        => $search,
        ]);
    }

    /**
     * Resolve / Clear an active advisory.
     */
    public function operatorResolve(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSectorAdmin();

        $advisory = TabletAdvisory::findOrFail($id);
        $advisory->update([
            'status'     => 'resolved',
            'expires_at' => Carbon::now(),
        ]);

        TabletAdvisory::flushAdvisoryCache();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Advisory '{$advisory->title}' marked as resolved.",
            ]);
        }

        return back()->with('success', "Advisory '{$advisory->title}' has been marked as resolved.");
    }

    /**
     * Extend advisory expiration by 2 hours.
     */
    public function operatorExtend(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSectorAdmin();

        $advisory = TabletAdvisory::findOrFail($id);
        $newExpiry = ($advisory->expires_at && $advisory->expires_at->isFuture()) 
            ? $advisory->expires_at->addHours(2) 
            : Carbon::now()->addHours(2);

        $advisory->update([
            'expires_at' => $newExpiry,
            'status'     => 'published',
        ]);

        TabletAdvisory::flushAdvisoryCache();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Advisory validity extended until " . $newExpiry->timezone('Asia/Karachi')->format('H:i \P\K\T'),
            ]);
        }

        return back()->with('success', "Advisory validity extended until " . $newExpiry->timezone('Asia/Karachi')->format('H:i \P\K\T'));
    }

    /**
     * Resolve approximate coordinates for a given Beat & KM from GeospatialMarker.
     */
    private function resolveKmCoordinates(?int $officeId, float $km): array
    {
        if (! $officeId) {
            return ['lat' => null, 'lng' => null];
        }

        $marker = GeospatialMarker::where('office_id', $officeId)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderByRaw("ABS(km - ?)", [$km])
            ->first();

        if ($marker) {
            return ['lat' => (float)$marker->lat, 'lng' => (float)$marker->lng];
        }

        return ['lat' => null, 'lng' => null];
    }
}
