<?php

namespace App\Http\Controllers;

use App\Services\TravelAdvisoryEventFeed;
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
     * Expiry for an advisory that is going live now: the validity the operator chose when inserting it
     * (expires_at - created_at) starts counting from approval time, instead of a fixed 4 hours.
     */
    private function expiryFromApproval(TabletAdvisory $advisory): Carbon
    {
        $seconds = ($advisory->expires_at && $advisory->created_at)
            ? $advisory->expires_at->getTimestamp() - $advisory->created_at->getTimestamp()
            : 0;
        if ($seconds < 1800) {
            $seconds = $seconds > 0 ? 1800 : 4 * 3600; // never shorter than 30 min; 4h only if no duration was ever recorded
        }

        return Carbon::now()->addSeconds($seconds);
    }

    private function pushEvent(string $type, TabletAdvisory $advisory, array $extra = []): void
    {
        $actor = Auth::user();
        TravelAdvisoryEventFeed::push($type, $advisory, $actor, $this->isSupervisorOrAdmin($actor), $extra);

        \App\Services\Notifications\PushDispatcher::dispatchAdvisory($advisory, $type, $extra);
    }

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
        $authorizedRoles = [
            Role::SECTOR_ADMIN,
            Role::SUPER_ADMIN,
            Role::AGENT_SUPERVISOR,
            Role::IT_ADMIN,
        ];

        if (! in_array($roleName, $authorizedRoles, true) && ! ($user instanceof \App\Models\User && $user->hasPermission('travel_advisory.view'))) {
            abort(403, 'Unauthorized. Access to Travel Advisory operations is restricted to authorized supervisors, operators, and administrators.');
        }
    }

    /**
     * Check if user is a Call Center Agent Supervisor or System Admin.
     */
    private function isSupervisorOrAdmin(?\App\Models\User $user = null): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $user ?: Auth::user();
        if (! $user) {
            return false;
        }

        $roleName = $user->role?->name;
        return in_array($roleName, [Role::SUPER_ADMIN, Role::IT_ADMIN, Role::AGENT_SUPERVISOR], true)
            || $user->hasPermission('travel_advisory.approve');
    }

    /**
     * Authorize that the current user is an Agent Supervisor or Super Admin.
     * Field sector wireless operators are strictly unauthorized to approve advisories.
     */
    private function authorizeSupervisor(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $this->isSupervisorOrAdmin($user)) {
            abort(403, 'Unauthorized. Advisory review, editing, and approval is restricted to Call Center Agent Supervisors and Super Admins.');
        }
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

        $isSupervisor = $this->isSupervisorOrAdmin($user);

        // Determine operator tier based on office type or role
        $tier = 'beat';
        if ($user?->role?->name === Role::SUPER_ADMIN || $user?->role?->name === 'national_admin' || $user?->role?->name === Role::AGENT_SUPERVISOR || $user?->role?->name === Role::IT_ADMIN) {
            $tier = 'national';
        } elseif ($user?->role?->name === Role::SECTOR_ADMIN || ($userOffice && $userOffice->type === 'sector')) {
            $tier = 'sector';
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
            'isSupervisor'     => $isSupervisor,
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
            'category'          => 'sometimes|nullable|in:fog,road_closure,road_works,traffic,weather,diversion',
            'advisory_type'     => 'sometimes|nullable|in:fog,road_closure,road_works,traffic,weather,diversion',
            'severity'          => 'required|in:info,advisory,warning,critical',
            'route_name'        => 'required|string|max:50',
            'carriageway'       => 'required|in:both,northbound,southbound',
            'zone_id'           => 'nullable|exists:offices,id',
            'sector_id'         => 'nullable|exists:offices,id',
            'office_id'         => 'nullable|exists:offices,id',
            'beat_ids'          => 'nullable|array',
            'beat_ids.*'        => 'integer|exists:offices,id',
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

        if (empty($validated['office_id']) && !empty($validated['beat_ids'])) {
            $validated['office_id'] = $validated['beat_ids'][0];
        }
        
        if (!empty($validated['custom_expires_at'])) {
            $expiresAt = Carbon::parse($validated['custom_expires_at']);
        } else {
            $durationHours = (float) ($validated['duration_hours'] ?? 4.0);
            $expiresAt = $now->copy()->addMinutes((int)($durationHours * 60));
        }

        // Auto-compute coordinates from GeospatialMarker / Carriageway
        $cleanRoute = strtoupper(str_replace(['-', ' '], '', $validated['route_name']));
        $targetOfficeId = $validated['office_id'] ?? $validated['sector_id'] ?? $user?->office_id ?? null;
        $startCoord = $this->resolveKmCoordinates($targetOfficeId, $validated['route_name'], (float)$validated['start_km']);
        $endCoord   = $this->resolveKmCoordinates($targetOfficeId, $validated['route_name'], (float)($validated['end_km'] ?? $validated['start_km']));

        $category = $request->input('category') ?: $request->input('advisory_type') ?: 'fog';

        // Determine corridor key for aggregation (e.g. M2-FOG-KM210-KM245)
        $corridorKey = sprintf(
            '%s-%s-KM%03d-KM%03d-%s',
            $cleanRoute,
            strtoupper($validated['carriageway']),
            (int)$validated['start_km'],
            (int)$validated['end_km'],
            strtoupper($category)
        );

        // Determine carriageway model ID
        $carriagewayModel = Carriageway::where('road', $cleanRoute)
            ->orWhere('road_short', $validated['route_name'])
            ->first();

        $isSupervisor = $this->isSupervisorOrAdmin($user);
        // Field sector operators insert as pending_approval; Call Center Supervisors can publish directly if requested.
        $status = ($isSupervisor && $request->boolean('auto_approve', false)) ? 'published' : 'pending_approval';
        $publishedAt = ($status === 'published') ? $now : null;
        $approvedBy = ($status === 'published') ? $user?->id : null;
        $approvedAt = ($status === 'published') ? $now : null;

        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'title'             => $validated['title'],
            'category'          => $category,
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
            'status'            => $status,
            'is_emergency'      => (bool) ($validated['is_emergency'] ?? false) || $validated['severity'] === 'critical',
            'reported_by_tier'  => $user?->office?->type ?? 'beat',
            'corridor_key'      => $corridorKey,
            'start_lat'         => $startCoord['lat'] ?? null,
            'start_lng'         => $startCoord['lng'] ?? null,
            'end_lat'           => $endCoord['lat'] ?? null,
            'end_lng'           => $endCoord['lng'] ?? null,
            'published_at'      => $publishedAt,
            'approved_at'       => $approvedAt,
            'expires_at'        => $expiresAt,
            'created_by'        => $user?->id ?? 1,
            'approved_by'       => $approvedBy,
        ]);

        $this->pushEvent($status === 'published' ? 'approved' : 'created', $advisory);

        if ($status === 'published') {
            TabletAdvisory::flushAdvisoryCache();
            $msg = "Travel advisory '{$advisory->title}' approved and broadcasted successfully across Humsafar Network.";
        } else {
            $msg = "Travel advisory '{$advisory->title}' submitted successfully. Forwarded to Call Center Agent Supervisors for review & approval.";
        }

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'  => true,
                'message'  => $msg,
                'status'   => $status,
                'advisory' => $advisory,
            ]);
        }

        return redirect()->route('ops.travel-advisory.create')->with('success', $msg);
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
        } elseif ($status === 'pending' || $status === 'pending_approval') {
            $query->where('status', 'pending_approval');
        } elseif ($status === 'resolved') {
            $query->where('status', 'resolved');
        } elseif ($status === 'expired') {
            $query->where('status', 'published')->where('expires_at', '<=', Carbon::now());
        } elseif ($status === 'rejected') {
            $query->where('status', 'rejected');
        }

        if ($road && strtolower($road) !== 'all') {
            $query->forRoute(strtoupper($road));
        }

        if ($type && strtolower($type) !== 'all') {
            $cleanType = strtolower($type);
            if ($cleanType === 'road_closure') {
                $query->where('category', 'road_closure')
                      ->where('category', '!=', 'diversion');
            } elseif ($cleanType === 'diversion') {
                $query->where('category', 'diversion');
            } elseif ($cleanType === 'road_works') {
                $query->whereIn('category', ['road_works', 'maintenance']);
            } elseif ($cleanType === 'traffic') {
                $query->whereIn('category', ['traffic', 'congestion']);
            } else {
                $query->where('category', $cleanType);
            }
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

        $advisories = $query->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE(published_at, created_at) DESC")
            ->paginate(15);

        // Metrics for 3D filter stat cards
        $metrics = [
            'total_active'     => TabletAdvisory::active()->count(),
            'pending_approval' => TabletAdvisory::where('status', 'pending_approval')->count(),
            'rejected'         => TabletAdvisory::where('status', 'rejected')->count(),
            'fog_alerts'       => TabletAdvisory::active()->where('category', 'fog')->count(),
            'road_closures'    => TabletAdvisory::active()->where('category', 'road_closure')->where('category', '!=', 'diversion')->count(),
            'road_works'       => TabletAdvisory::active()->whereIn('category', ['road_works', 'maintenance'])->count(),
            'diversions'       => TabletAdvisory::active()->where('category', 'diversion')->count(),
            'traffic_alerts'   => TabletAdvisory::active()->whereIn('category', ['traffic', 'congestion'])->count(),
            'critical_hazards' => TabletAdvisory::active()->where('severity', 'critical')->count(),
            'expired_resolved' => TabletAdvisory::where('status', 'resolved')->count(),
            'total_all'        => TabletAdvisory::count(),
        ];

        $isSupervisor = $this->isSupervisorOrAdmin();
        $formattedItems = collect($advisories->items())->map(function ($item) use ($isSupervisor) {
            $now = Carbon::now();
            $isPending = ($item->status === 'pending_approval');
            $isPublished = ($item->status === 'published');
            $isResolved = ($item->status === 'resolved');
            $isRejected = ($item->status === 'rejected');
            $isExpired = ($isPublished && $item->expires_at && $item->expires_at->isPast());
            $isLive = ($isPublished && !$isExpired);

            return [
                'id'                  => $item->id,
                'uuid'                => $item->uuid,
                'title'               => $item->title,
                'message'             => $item->message,
                'category'            => $item->category,
                'advisory_type'       => $item->category,
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
                'is_pending'          => $isPending,
                'is_live'             => $isLive,
                'is_expired'          => $isExpired,
                'is_resolved'         => $isResolved,
                'is_rejected'         => $isRejected,
                'creator_name'        => $item->creator?->username ?: ($item->creator?->full_name ?: ($item->creator?->name ?: 'Field Operator')),
                'approver_name'       => $item->approver?->username ?: ($item->approver?->full_name ?: ($item->approver?->name ?: null)),
                'rejection_reason'    => $item->rejection_reason,
                'can_approve'         => $isSupervisor,
                'can_edit'            => $isSupervisor,
                'can_reject'          => $isSupervisor,
                'expires_at_human'    => $item->expires_at ? $item->expires_at->timezone('Asia/Karachi')->diffForHumans() : 'N/A',
                'published_at_human'  => $item->published_at ? $item->published_at->timezone('Asia/Karachi')->diffForHumans() : ($item->created_at ? $item->created_at->timezone('Asia/Karachi')->diffForHumans() : 'Pending'),
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
            'canApprove'    => $isSupervisor,
        ]);
    }

    /**
     * Fetch single advisory JSON for Supervisor Edit & Review modal.
     */
    public function operatorShow(int $id): JsonResponse
    {
        $this->authorizeSectorAdmin();
        $item = TabletAdvisory::with(['creator', 'approver', 'office', 'sector', 'zone', 'carriagewayModel'])
            ->findOrFail($id);

        return response()->json([
            'success'  => true,
            'advisory' => [
                'id'                => $item->id,
                'uuid'              => $item->uuid,
                'title'             => $item->title,
                'message'           => $item->message,
                'category'          => $item->category,
                'advisory_type'     => $item->category,
                'severity'          => $item->severity,
                'route_name'        => $item->route_name,
                'carriageway'       => $item->carriageway,
                'start_km'          => (float) $item->start_km,
                'end_km'            => (float) ($item->end_km ?: $item->start_km),
                'start_landmark'    => $item->start_landmark,
                'end_landmark'      => $item->end_landmark,
                'visibility_meters' => $item->visibility_meters,
                'speed_limit_kmh'   => $item->speed_limit_kmh,
                'closure_type'      => $item->closure_type,
                'status'            => $item->status,
                'is_emergency'      => (bool) $item->is_emergency,
                'rejection_reason'  => $item->rejection_reason,
                'creator_name'      => $item->creator?->username ?: ($item->creator?->name ?: 'Field Operator'),
                'approver_name'     => $item->approver?->username ?: ($item->approver?->name ?: null),
                'published_at'      => $item->published_at?->toIso8601String(),
                'expires_at'        => $item->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Operator / Supervisor Update / Edit advisory in place.
     * Accessible by both Sector operators and Supervisors.
     * Immediate live approval is strictly restricted to Supervisors.
     */
    public function operatorUpdate(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSectorAdmin();
        $user = Auth::user();
        $advisory = TabletAdvisory::findOrFail($id);

        $validated = $request->validate([
            'title'             => 'required|string|max:150',
            'message'           => 'required|string|max:1000',
            'severity'          => 'sometimes|required|in:info,advisory,warning,critical',
            'category'          => 'sometimes|nullable|in:fog,road_closure,road_works,traffic,weather,diversion',
            'advisory_type'     => 'sometimes|nullable|in:fog,road_closure,road_works,traffic,weather,diversion',
            'carriageway'       => 'sometimes|required|in:both,northbound,southbound',
            'start_km'          => 'nullable|numeric|min:0',
            'end_km'            => 'nullable|numeric|min:0',
            'start_landmark'    => 'nullable|string|max:100',
            'end_landmark'      => 'nullable|string|max:100',
            'visibility_meters' => 'nullable|integer|min:0|max:5000',
            'speed_limit_kmh'   => 'nullable|integer|min:0|max:150',
            'closure_type'      => 'nullable|in:full,lane_1,lane_2,shoulder,diversion',
            'is_emergency'      => 'nullable|boolean',
            'duration_hours'    => 'nullable|numeric|min:0.5|max:72',
            'approve_now'       => 'nullable|boolean',
        ]);

        $category = $request->input('category') ?: $request->input('advisory_type') ?: $advisory->category;

        $advisory->title             = $validated['title'];
        $advisory->message           = $validated['message'];
        $advisory->severity          = $validated['severity'] ?? $advisory->severity;
        $advisory->category          = $category;
        $advisory->carriageway       = $validated['carriageway'] ?? $advisory->carriageway ?? 'both';
        $advisory->start_km          = isset($validated['start_km']) ? $validated['start_km'] : $advisory->start_km;
        $advisory->end_km            = isset($validated['end_km']) ? $validated['end_km'] : ($advisory->end_km ?: $advisory->start_km);
        $advisory->start_landmark    = $validated['start_landmark'] ?? null;
        $advisory->end_landmark      = $validated['end_landmark'] ?? null;
        $advisory->visibility_meters = $validated['visibility_meters'] ?? null;
        $advisory->speed_limit_kmh   = $validated['speed_limit_kmh'] ?? null;
        $advisory->closure_type      = $validated['closure_type'] ?? null;
        $advisory->is_emergency      = (bool) ($validated['is_emergency'] ?? $advisory->is_emergency) || (($validated['severity'] ?? $advisory->severity) === 'critical');

        $explicitDuration = !empty($validated['duration_hours']);
        if ($explicitDuration) {
            // "Valid for N hours" counts from now. Leaving it blank keeps the current expiry untouched.
            $advisory->expires_at = Carbon::now()->addMinutes((int) ($validated['duration_hours'] * 60));
        }

        $isSupervisor = $this->isSupervisorOrAdmin($user);
        if ($request->boolean('approve_now') && $isSupervisor) {
            $now = Carbon::now();
            if (!$explicitDuration && $advisory->status !== 'published') {
                $advisory->expires_at = $this->expiryFromApproval($advisory);
            }
            $advisory->status = 'published';
            $advisory->approved_by = Auth::id();
            $advisory->approved_at = $now;
            if (empty($advisory->published_at)) {
                $advisory->published_at = $now;
            }
        }

        $wasApproveNow = $request->boolean('approve_now') && $isSupervisor;
        $advisory->save();
        TabletAdvisory::flushAdvisoryCache();
        $this->pushEvent($wasApproveNow ? 'approved' : 'updated', $advisory);

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'  => true,
                'message'  => "Advisory '{$advisory->title}' updated successfully" . ($advisory->status === 'published' ? ' and published live.' : '.'),
                'advisory' => $advisory,
            ]);
        }

        return redirect()->route('ops.travel-advisory.index')
            ->with('success', "Advisory updated successfully.");
    }

    /**
     * Real-time alert feed. Supervisors receive what sector operators do (insert/update/clear/extend);
     * sector operators receive what supervisors do (approve/reject/update/clear/extend) on their advisories.
     */
    public function liveEvents(Request $request): JsonResponse
    {
        $this->authorizeSectorAdmin();
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $latest = TravelAdvisoryEventFeed::latestSeq();

        if (! $request->query->has('after')) {
            return response()->json(['last_seq' => $latest, 'events' => []]);
        }

        $isSupervisor = $this->isSupervisorOrAdmin($user);
        $office = $user->office;
        $chain = array_values(array_filter([$office?->id, $office?->parent_id, $office?->parent?->parent_id]));

        $events = array_values(array_filter(
            TravelAdvisoryEventFeed::since((int) $request->query('after')),
            function (array $e) use ($user, $isSupervisor, $chain): bool {
                if (($e['actor_id'] ?? null) === $user->id) {
                    return false;
                }
                if ($isSupervisor) {
                    return empty($e['actor_is_supervisor']);
                }

                return ! empty($e['actor_is_supervisor'])
                    && (($e['created_by'] ?? null) === $user->id
                        || in_array($e['sector_id'] ?? null, $chain, true)
                        || in_array($e['zone_id'] ?? null, $chain, true));
            }
        ));

        return response()->json(['last_seq' => $latest, 'events' => $events]);
    }

    /**
     * Agent Supervisor Approve advisory and broadcast live.
     */
    public function operatorApprove(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSupervisor();
        $advisory = TabletAdvisory::findOrFail($id);

        $durationHours = (float) ($request->input('duration_hours', 0));
        if ($durationHours > 0) {
            $advisory->expires_at = Carbon::now()->addMinutes((int)($durationHours * 60));
        } else {
            // Keep the validity the operator chose when inserting; the clock starts at approval.
            $advisory->expires_at = $this->expiryFromApproval($advisory);
        }

        $advisory->approve(Auth::id());
        $this->pushEvent('approved', $advisory);

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'  => true,
                'message'  => "Travel advisory #{$advisory->id} ('{$advisory->title}') approved and broadcasted live to Humsafar and public APIs.",
                'status'   => 'published',
                'advisory' => $advisory,
            ]);
        }

        return redirect()->route('ops.travel-advisory.index')
            ->with('success', "Advisory #{$advisory->id} approved and published live.");
    }

    /**
     * Agent Supervisor Reject advisory.
     */
    public function operatorReject(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSupervisor();
        $advisory = TabletAdvisory::findOrFail($id);

        $reason = $request->input('reason', 'Rejected by Call Center Agent Supervisor.');
        $advisory->reject(Auth::id(), $reason);
        $this->pushEvent('rejected', $advisory, ['reason' => Str::limit((string) $reason, 120)]);

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'  => true,
                'message'  => "Travel advisory #{$advisory->id} rejected.",
                'status'   => 'rejected',
                'advisory' => $advisory,
            ]);
        }

        return redirect()->route('ops.travel-advisory.index')
            ->with('success', "Advisory #{$advisory->id} rejected.");
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
        $this->pushEvent('resolved', $advisory);

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success' => true,
                'message' => "Advisory '{$advisory->title}' marked as resolved and removed from live corridor feeds.",
            ]);
        }

        return back()->with('success', "Advisory '{$advisory->title}' has been marked as resolved.");
    }

    /**
     * Extend advisory expiration by specified hours (defaults to +2 hours).
     */
    public function operatorExtend(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeSectorAdmin();

        $advisory = TabletAdvisory::findOrFail($id);
        $hours = (float) ($request->input('hours', 2));
        if ($hours <= 0) {
            $hours = 2.0;
        }

        $baseTime = ($advisory->expires_at && $advisory->expires_at->isFuture()) 
            ? $advisory->expires_at 
            : Carbon::now();
        $newExpiry = $baseTime->copy()->addMinutes((int)($hours * 60));

        $advisory->update([
            'expires_at' => $newExpiry,
            'status'     => 'published',
        ]);

        TabletAdvisory::flushAdvisoryCache();
        $this->pushEvent('extended', $advisory, ['hours' => $hours]);

        if ($request->wantsJson() || $request->ajax() || $request->boolean('ajax')) {
            return response()->json([
                'success'          => true,
                'message'          => "Advisory validity extended by {$hours}h until " . $newExpiry->timezone('Asia/Karachi')->format('H:i \P\K\T'),
                'expires_at'       => $newExpiry->toIso8601String(),
                'expires_at_human' => $newExpiry->timezone('Asia/Karachi')->diffForHumans(),
            ]);
        }

        return back()->with('success', "Advisory validity extended until " . $newExpiry->timezone('Asia/Karachi')->format('H:i \P\K\T'));
    }

    /**
     * Resolve approximate coordinates for a given Route, Beat & KM from GeospatialMarker or Carriageway.
     */
    private function resolveKmCoordinates(?int $officeId, ?string $routeName, float $km): array
    {
        // 1. First attempt: Carriageway markers for route (over 377,000 physical road markers)
        if ($routeName) {
            $clean = strtoupper(str_replace(['-', ' '], '', $routeName));
            $cw = Carriageway::where('road', $clean)->orWhere('road_short', $routeName)->first();
            if ($cw) {
                $marker = $cw->markers()
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderByRaw("ABS(km - ?)", [$km])
                    ->first();
                if ($marker) {
                    return ['lat' => (float)$marker->lat, 'lng' => (float)$marker->lng];
                }
            }
        }

        // 2. Second attempt: Specific office (beat or sector child beats)
        if ($officeId) {
            $office = Office::find($officeId);
            $beatIds = [];
            if ($office) {
                if ($office->type === 'beat') {
                    $beatIds = [$office->id];
                } elseif ($office->type === 'sector') {
                    $beatIds = Office::where('parent_id', $office->id)->pluck('id')->all();
                }
            }

            if (!empty($beatIds)) {
                $marker = GeospatialMarker::whereIn('office_id', $beatIds)
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderByRaw("ABS(km - ?)", [$km])
                    ->first();
                if ($marker) {
                    return ['lat' => (float)$marker->lat, 'lng' => (float)$marker->lng];
                }
            }
        }

        // 3. Fallback: Route default coordinates
        if ($routeName) {
            $defaults = TabletAdvisory::getRouteDefaultCoordinates($routeName);
            if ($defaults && isset($defaults[0])) {
                return ['lat' => (float)$defaults[0][0], 'lng' => (float)$defaults[0][1]];
            }
        }

        return ['lat' => null, 'lng' => null];
    }
}
