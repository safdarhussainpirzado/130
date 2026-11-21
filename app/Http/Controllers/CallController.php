<?php

namespace App\Http\Controllers;

use App\Http\Resources\CallListResource;
use App\Http\Resources\CallShowResource;
use Illuminate\Support\Str;
use App\Models\Call;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\VehicleType;
use App\Models\Office;
use App\Models\Carriageway;
use App\Models\GeospatialLandmark;
use App\Models\User;
use App\Services\CallService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CallController extends Controller
{
    public function __construct(private readonly CallService $callService) {}

    /**
     * @param Builder|\Illuminate\Database\Query\Builder|Call|User|mixed $query
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    private function applyScopeVisibility($query)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        // Global National Administrators (super_admin, operation_admin with national scope_level)
        $isGlobalAdmin = in_array($user->role?->name, ['super_admin', 'operation_admin'], true)
            && ($user->role?->scope_level === 'national' || $user->role?->name === 'super_admin');

        if ($isGlobalAdmin) {
            return $query;
        }

        // Call Center Supervisor (national read oversight of helpline calls)
        if ($user->role?->name === 'agent_supervisor') {
            return $query;
        }

        // Call Center Agent: Strictly restricted to their own assigned calls
        if ($user->role?->name === 'agent') {
            return $query->where('calls.agent_id', $user->id);
        }

        // Operational Scoped Users (zone_admin, sector_admin, beat_operator, etc.)
        $scopes = $user->activeScopes;
        
        if ($scopes && $scopes->isNotEmpty()) {
            $accessibleOfficeIds = [];
            foreach ($scopes as $scope) {
                // Missing or null office_id must FAIL CLOSED (never add 1=1 or open access)
                if (empty($scope->office_id) || (int) $scope->office_id <= 0) {
                    continue;
                }
                
                $officeIds = $this->getOfficeDescendantIds((int) $scope->office_id);
                if (!empty($officeIds)) {
                    $accessibleOfficeIds = array_merge($accessibleOfficeIds, $officeIds);
                }
            }

            $accessibleOfficeIds = array_values(array_unique(array_filter($accessibleOfficeIds)));

            if (!empty($accessibleOfficeIds)) {
                return $query->whereIn('calls.office_id', $accessibleOfficeIds);
            }
        }

        // Fail closed for any user with missing or invalid scopes
        return $query->whereRaw('1 = 0');
    }

    /**
     * In-memory breadth-first office hierarchy resolution via Office model.
     */
    private function getOfficeDescendantIds(int $officeId): array
    {
        return Office::getDescendantIdsFor($officeId, true);
    }

    /**
     * Apply search, temporal, organizational, agent, and category filters.
     * Note: Does NOT apply status or assignment directory filters so that index stats compute accurately across tabs.
     *
     * @param Builder|\Illuminate\Database\Query\Builder|Call|mixed $query
     * @param Request $request
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    /**
     * Apply context-level filters (search, date range, agents, office hierarchy)
     * which define the active operational scope.
     *
     * @param Builder|\Illuminate\Database\Query\Builder|Call|mixed $query
     * @param Request $request
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    private function applyContextFilters($query, Request $request)
    {
        if ($request->search) {
            $s = trim($request->search);

            // Fast path for indexed lookups: Call Number ("CRM-...") or Phone Number
            if (str_starts_with(strtolower($s), 'crm-') || str_starts_with(strtolower($s), 'crm_')) {
                $query->where('calls.call_number', $s);
            } elseif (preg_match('/^(\+?92|0)?(3[0-9]{2,})$/', $s, $m)) {
                $phonePrefix = '0' . $m[2];
                $bidx = (strlen($phonePrefix) >= 10 && function_exists('phone_blind_index')) ? phone_blind_index($phonePrefix) : null;
                $query->where(function ($q) use ($phonePrefix, $bidx) {
                    if ($bidx) {
                        $q->where('calls.caller_number_bidx', $bidx)
                          ->orWhere('calls.caller_number', 'like', "{$phonePrefix}%");
                    } else {
                        $q->where('calls.caller_number', 'like', "{$phonePrefix}%");
                    }
                });
            } else {
                // Pre-fetch matching IDs from small auxiliary tables in-memory
                // to eliminate correlated subqueries on the large calls table
                $vehicleTypeIds = VehicleType::where('name', 'like', "%$s%")->pluck('id');
                $callTypeIds = CallType::where('name', 'like', "%$s%")->pluck('id');
                $callSubTypeIds = CallSubType::where('name', 'like', "%$s%")->pluck('id');
                $officeIds = Office::where('name', 'like', "%$s%")->pluck('id');
                $generalBidx = (function_exists('phone_blind_index') && strlen($s) >= 7) ? phone_blind_index($s) : null;

                // Index-friendly search (was LIKE '%term%' on four columns = full scan of ~780k rows, 3-5 s, x4 queries
                // per search). caller_number / caller_name are stored encrypted, so a substring match on them could
                // never hit; phones are found through the blind index above, legacy plaintext phones by prefix.
                $callNumberCandidates = [];
                if (preg_match('/^\d{3,10}$/', $s)) {
                    // "770720" -> CRM-<year>-770720 for the years in use (exact, indexed lookup)
                    foreach (range((int) now()->year, (int) now()->year - 3) as $yr) {
                        $callNumberCandidates[] = "CRM-{$yr}-{$s}";
                    }
                }

                $query->where(function($q) use ($s, $generalBidx, $vehicleTypeIds, $callTypeIds, $callSubTypeIds, $officeIds, $callNumberCandidates) {
                    $q->where('calls.call_number', 'like', "{$s}%")
                      ->orWhere('calls.caller_number', 'like', "{$s}%")
                      ->orWhere('calls.vehicle_no', 'like', "{$s}%");

                    if (!empty($callNumberCandidates)) {
                        $q->orWhereIn('calls.call_number', $callNumberCandidates);
                    }

                    if ($generalBidx) {
                        $q->orWhere('calls.caller_number_bidx', $generalBidx);
                    }

                    if ($vehicleTypeIds->isNotEmpty()) {
                        $q->orWhereIn('calls.vehicle_type_id', $vehicleTypeIds);
                    }
                    if ($callTypeIds->isNotEmpty()) {
                        $q->orWhereIn('calls.call_type_id', $callTypeIds);
                    }
                    if ($callSubTypeIds->isNotEmpty()) {
                        $q->orWhereIn('calls.call_sub_type_id', $callSubTypeIds);
                    }
                    if ($officeIds->isNotEmpty()) {
                        $q->orWhereIn('calls.office_id', $officeIds);
                    }
                });
            }
        }

        // Agent and My Records Filtering
        if ($request->boolean('my_records') || $request->input('scope') === 'my_records' || $request->input('view') === 'my_records') {
            $user = Auth::user();
            if ($user) {
                $query->where('calls.agent_id', $user->id);
            }
        } elseif ($request->agent_id) {
            $query->where('calls.agent_id', $request->agent_id);
        }

        if ($request->date_from) {
            try {
                $dateTime = $request->date_from;
                if ($request->time_from) {
                    $dateTime .= ' ' . $request->time_from;
                    $query->where('calls.created_at', '>=', Carbon::parse($dateTime));
                } else {
                    $query->where('calls.created_at', '>=', Carbon::parse($dateTime)->startOfDay());
                }
            } catch (Exception $e) {}
        }

        if ($request->date_to) {
            try {
                $dateTime = $request->date_to;
                if ($request->time_to) {
                    $dateTime .= ' ' . $request->time_to;
                    $query->where('calls.created_at', '<=', Carbon::parse($dateTime));
                } else {
                    $query->where('calls.created_at', '<=', Carbon::parse($dateTime)->endOfDay());
                }
            } catch (Exception $e) {}
        }

        if ($request->office_id) {
            $query->where('calls.office_id', $request->office_id);
        } elseif ($request->beat_id) {
            $query->where('calls.office_id', $request->beat_id);
        } elseif ($request->sector_id) {
            $descendantIds = $this->getOfficeDescendantIds((int) $request->sector_id);
            if (!empty($descendantIds)) {
                $query->whereIn('calls.office_id', $descendantIds);
            }
        } elseif ($request->zone_id) {
            $descendantIds = $this->getOfficeDescendantIds((int) $request->zone_id);
            if (!empty($descendantIds)) {
                $query->whereIn('calls.office_id', $descendantIds);
            }
        }

        return $query;
    }

    /**
     * Apply category and subcategory filters.
     *
     * @param Builder|\Illuminate\Database\Query\Builder|Call|mixed $query
     * @param Request $request
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    private function applyCategoryFilters($query, Request $request)
    {
        // Category Filter: Supports numeric ID or slug (e.g. 'emergency', 'information')
        $catFilter = $request->input('call_type_id', $request->input('category', $request->input('type')));
        if ($catFilter) {
            if (is_numeric($catFilter)) {
                $query->where('calls.call_type_id', (int) $catFilter);
            } else {
                $slugMap = [
                    'emergency'    => 'Emergency',
                    'general_help' => 'General Help',
                    'information'  => 'Information',
                    'complaint'    => 'Complaint',
                    'crime'        => 'Crime',
                    'junk_silent'  => 'Junk/Silent',
                    'junk'         => 'Junk/Silent',
                ];
                $typeName = $slugMap[strtolower($catFilter)] ?? null;
                if ($typeName) {
                    $matchedId = CallType::where('name', $typeName)->value('id');
                    if ($matchedId) {
                        $query->where('calls.call_type_id', $matchedId);
                    }
                }
            }
        }

        if ($request->call_sub_type_id) {
            $query->where('calls.call_sub_type_id', $request->call_sub_type_id);
        }

        return $query;
    }

    /**
     * Legacy composite filter method for full backward compatibility.
     *
     * @param Builder|\Illuminate\Database\Query\Builder|Call|mixed $query
     * @param Request $request
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    private function applyFiltersAndSearch($query, Request $request)
    {
        $query = $this->applyContextFilters($query, $request);
        return $this->applyCategoryFilters($query, $request);
    }

    /**
     * Apply directory tab (assigned vs unassigned) and status filters.
     *
     * @param Builder|\Illuminate\Database\Query\Builder|Call|mixed $query
     * @param Request $request
     * @return Builder|\Illuminate\Database\Query\Builder|mixed
     */
    private function applyDirectoryAndStatus($query, Request $request)
    {
        // 1. Assignment Directory Filter: 'assigned' vs 'unassigned'
        $assignment = $request->input('assignment');
        if (!$assignment && in_array($request->status, ['assigned', 'unassigned'], true)) {
            $assignment = $request->status;
        }

        if ($assignment === 'assigned') {
            $query->whereNotNull('calls.office_id')
                  ->where('calls.office_id', '>', 0);
        } elseif ($assignment === 'unassigned') {
            $query->whereNull('calls.office_id');
        }

        // 2. Gender / Women Safety Filter
        $gender = $request->input('gender', $request->input('caller_gender'));
        if ($gender) {
            if ($gender === 'female' || $gender === 'women') {
                $query->where(function ($q) {
                    $q->where('calls.caller_gender', 'female')
                      ->orWhere('calls.call_type_id', 10);
                });
            } else {
                $query->where('calls.caller_gender', $gender);
            }
        }

        // 3. Status Filter (excluding assignment pseudo-statuses)
        if ($request->status && !in_array($request->status, ['all', 'assigned', 'unassigned'], true)) {
            $query->where('calls.status', $request->status);
        }

        return $query;
    }

    /**
     * @param LengthAwarePaginator $calls
     * @param array $stats
     * @return JsonResponse
     */
    private function getAjaxResponse(LengthAwarePaginator $calls, array $stats): JsonResponse
    {
        return response()->json([
            'items' => CallListResource::collection($calls->items())->resolve(),
            'stats' => $stats,
            'current_page' => $calls->currentPage(),
            'last_page' => $calls->lastPage(),
            'per_page' => $calls->perPage(),
            'total' => $calls->total(),
        ]);
    }

    /**
     * Authorized, on-demand filter metadata endpoint.
     * Replaces 26KB+ inlined HTML payload with an asynchronous, role/scope-isolated cacheable response.
     */
    public function getFilterOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $isPrivileged = in_array($user->role?->name, ['super_admin', 'operation_admin', 'agent_supervisor'], true);
        $scopeKey = $isPrivileged ? 'privileged' : 'scope_' . $user->id;

        $data = Cache::remember("crm:calls:filter_options:{$scopeKey}:v2", 3600, function () use ($user, $isPrivileged): mixed {
            $zonesQuery = Office::zones()->where('is_active', true)->select('id', 'name')->orderBy('name');
            $sectorsQuery = Office::sectors()->where('is_active', true)->select('id', 'name', 'parent_id')->orderBy('name');
            $beatsQuery = Office::beats()->where('is_active', true)->select('id', 'name', 'parent_id')->orderBy('name');

            if (!$isPrivileged && $user->activeScopes && $user->activeScopes->isNotEmpty()) {
                $allowedOfficeIds = collect();
                foreach ($user->activeScopes as $scope) {
                    if ($scope->office) {
                        $allowedOfficeIds = $allowedOfficeIds->merge($scope->office->getDescendantIds(true));
                    }
                }
                if ($allowedOfficeIds->isNotEmpty()) {
                    $beatsQuery->whereIn('id', $allowedOfficeIds);
                }
            }

            return [
                'zones'        => $zonesQuery->get(),
                'sectors'      => $sectorsQuery->get(),
                'beats'        => $beatsQuery->get(),
                'callTypes'    => CallType::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
                'callSubTypes' => CallSubType::where('is_active', true)->select('id', 'name', 'call_type_id')->orderBy('name')->get(),
                'agents'       => $isPrivileged
                    ? User::whereHas('role', fn($q) => $q->where('name', 'agent'))
                          ->select('id', 'full_name', 'username')
                          ->orderBy('username')
                          ->get()
                          ->map(fn($u) => ['id' => $u->id, 'username' => $u->username, 'name' => $u->full_name ?? $u->username])
                    : [],
            ];
        });

        return response()->json($data);
    }

    private function getFilterData()
    {
        return [
            'zones'        => [],
            'sectors'      => [],
            'beats'        => [],
            'callTypes'    => [],
            'callSubTypes' => [],
            'agents'       => [],
        ];
    }

    /**
     * Compute optimized index statistics (status & categories) in 2 queries.
     *
     * @param Builder $query
     * @param Request $request
     * @return array
     */
    /**
     * Compute optimized index statistics (status & categories) across the active context.
     * Category, Status, and Assignment directory filters are intentionally NOT applied to these
     * top overview metrics to ensure cards remain stable, correct, and non-clobbering.
     *
     * @param Builder $contextQuery
     * @param Request $request
     * @return array
     */
    private function computeIndexStats($contextQuery, Request $request): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Dynamic cache per unique filter scope with atomic version invalidation
        $statsVersion = Cache::get('crm:calls:stats_version', 1);
        $cacheKey = 'crm:calls:stats_v12:' . $statsVersion . ':' . ($user?->id ?? 'anon') . ':' . md5(serialize($request->only([
            'search', 'date_from', 'date_to', 'time_from', 'time_to',
            'office_id', 'beat_id', 'sector_id', 'zone_id',
            'agent_id', 'my_records', 'scope', 'view',
            'call_type_id', 'category', 'type'
        ])));

        return Cache::remember($cacheKey, 60, function () use ($contextQuery, $request): mixed {
            // Base context query (respects zone, sector, beat, search, dates, agent)
            $baseContext = (clone $contextQuery);

            // 1. Status Stats: If a category is selected, calculate status metrics for that category subset.
            // If no category is selected, calculate across the entire active operational scope.
            $statusBase = (clone $baseContext);
            if ($request->filled('call_type_id') || $request->filled('category') || $request->filled('type')) {
                $statusBase = $this->applyCategoryFilters($statusBase, $request);
            }

            $statsQuery = (clone $statusBase)->getQuery();
            $statsQuery->columns = null;
            $statsQuery->orders = null;

            $statsRow = $statsQuery->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN calls.office_id IS NOT NULL THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN calls.office_id IS NULL THEN 1 ELSE 0 END) as unassigned,
                SUM(CASE WHEN calls.caller_gender = 'female' OR calls.call_type_id = 10 THEN 1 ELSE 0 END) as women_calls,
                SUM(CASE WHEN (calls.caller_gender = 'female' OR calls.call_type_id = 10) AND calls.status = 'pending' THEN 1 ELSE 0 END) as pending_women,
                SUM(CASE WHEN calls.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN calls.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN calls.status = 'tiger_reached' THEN 1 ELSE 0 END) as tiger_reached,
                SUM(CASE WHEN calls.status = 'completed' THEN 1 ELSE 0 END) as completed
            ")->first();

            $totalCount      = (int) ($statsRow->total ?? 0);
            $assignedCount   = (int) ($statsRow->assigned ?? 0);
            $unassignedCount = (int) ($statsRow->unassigned ?? 0);
            $womenCalls      = (int) ($statsRow->women_calls ?? 0);
            $pendingWomen    = (int) ($statsRow->pending_women ?? 0);

            $statusStats = [
                'total'               => $totalCount,
                'assigned'            => $assignedCount,
                'unassigned'          => $unassignedCount,
                'assigned_percent'    => $totalCount > 0 ? round(($assignedCount / $totalCount) * 100, 1) : 0,
                'unassigned_percent'  => $totalCount > 0 ? round(($unassignedCount / $totalCount) * 100, 1) : 0,
                'women_calls'         => $womenCalls,
                'women_calls_percent' => $totalCount > 0 ? round(($womenCalls / $totalCount) * 100, 1) : 0,
                'pending_women'       => $pendingWomen,
                'pending'             => (int) ($statsRow->pending ?? 0),
                'in_progress'         => (int) ($statsRow->in_progress ?? 0),
                'tiger_reached'       => (int) ($statsRow->tiger_reached ?? 0),
                'completed'           => (int) ($statsRow->completed ?? 0),
            ];

            $activeTotal = $statusStats['pending'] + $statusStats['in_progress'] + $statusStats['tiger_reached'];
            $statusStats['pending_percent']       = $activeTotal > 0 ? round(($statusStats['pending'] / $activeTotal) * 100, 1) : 0;
            $statusStats['in_progress_percent']   = $activeTotal > 0 ? round(($statusStats['in_progress'] / $activeTotal) * 100, 1) : 0;
            $statusStats['tiger_reached_percent'] = $activeTotal > 0 ? round(($statusStats['tiger_reached'] / $activeTotal) * 100, 1) : 0;
            $statusStats['completed_percent']     = $totalCount > 0 ? round(($statusStats['completed'] / $totalCount) * 100, 1) : 0;
            $statusStats['total_percent']         = 100;

            // 2. Category Cards: Always retain their overall context counts across all categories
            // so selecting a category doesn't wipe out the category card counters.
            $typeQuery = (clone $baseContext)->getQuery();
            $typeQuery->columns = null;
            $typeQuery->orders = null;
            $typeCounts = $typeQuery
                ->select('calls.call_type_id', DB::raw('COUNT(*) as count'))
                ->groupBy('calls.call_type_id')
                ->pluck('count', 'calls.call_type_id')
                ->all();

            $globalTotal = array_sum($typeCounts);

            $callTypes = CallType::where('is_active', true)->select('id', 'name', 'icon')->orderBy('id')->get();
            $categoryStats = [];

            // Primary Categories (ID 1-6 strictly):
            // Exclude ID 7 (Emergency Assistance) and ID 10 (Women's Safety Emergency)
            // So that there are exactly 6 category cards aligned on a single row
            foreach ($callTypes as $type) {
                if ($type->id == 7 || $type->id == 10) {
                    continue;
                }
                $c = (int) ($typeCounts[$type->id] ?? 0);
                $categoryStats[] = [
                    'id'      => $type->id,
                    'name'    => $type->name,
                    'count'   => $c,
                    'percent' => $globalTotal > 0 ? round(($c / $globalTotal) * 100, 1) : 0,
                    'icon'    => $type->icon ?? 'fa-tag',
                ];
            }

            return array_merge($statusStats, ['categories' => $categoryStats]);
        });
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Call::class);

        $perPage = (int) $request->input('per_page', $request->input('perPage', 10));
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Exact minimal columns selected from calls (zero unneeded database fields)
        $listColumns = [
            'id', 'uuid', 'call_number', 'caller_name', 'caller_number', 'caller_gender',
            'status', 'priority', 'call_type_id', 'call_sub_type_id',
            'agent_id', 'office_id', 'vehicle_no', 'vehicle_type_id',
            'created_at', 'call_reminder_count',
            'last_reminder_at', 'tiger_id',
            DB::raw('SUBSTRING(details, 1, 600) as details'),
            // shown in the call view modal (was missing from the list payload, so the modal always printed '---')
            DB::raw('SUBSTRING(location_details, 1, 255) as location_details')
        ];

        // Constrained eager loading: only essential primary keys and display fields
        $baseQuery = Call::select($listColumns)
            ->with([
                'callType:id,name',
                'callSubType:id,name',
                'agent:id,full_name',
            ]);

        $baseQuery = $this->applyScopeVisibility($baseQuery);
        $contextQuery = $this->applyContextFilters($baseQuery, $request);

        // Pre-compute & cache office location hierarchy map in Redis/memory (zero DB queries per call row)
        // Breadcrumb shows only Zone / Sector / Beat (the HQ and Region levels above them are noise on every row).
        $locationMap = Cache::remember('office_location_hierarchy_map_v2', 3600, function (): mixed {
            $offices = Office::select('id', 'name', 'type', 'parent_id')->get()->keyBy('id');
            $map = [];
            foreach ($offices as $id => $office) {
                $parts = [];
                $curr = $office;
                while ($curr) {
                    if (in_array($curr->type, ['zone', 'sector', 'beat'], true)) {
                        array_unshift($parts, $curr->name);
                    }
                    $curr = $curr->parent_id && isset($offices[$curr->parent_id]) ? $offices[$curr->parent_id] : null;
                }
                $map[$id] = $parts ? implode(' / ', $parts) : $office->name;
            }
            return $map;
        });
        CallListResource::setLocationMap($locationMap);

        $vehicleTypesMap = Cache::remember('crm:vehicle_types_map:v1', 86400, function (): mixed {
            return VehicleType::pluck('name', 'id')->toArray();
        });
        CallListResource::setVehicleTypesMap($vehicleTypesMap);

        // High-Performance Optimization: Calculate all status counts and category stats across the active scope
        $stats = $this->computeIndexStats($contextQuery, $request);

        // Apply active category filter and active directory tab (assigned/unassigned) and status filter to records
        $query = (clone $contextQuery);
        $query = $this->applyCategoryFilters($query, $request);
        $query = $this->applyDirectoryAndStatus($query, $request);

        $filterData = $this->getFilterData();

        $sort = $request->input('sort', 'created_at');
        $dir = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'id', 'call_number', 'priority', 'status', 'caller_name', 'caller_number', 'vehicle_no', 'call_type_id', 'office_id'];
        
        if ($sort === 'beat_id') $sort = 'office_id';
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        // Critical requirement: Any pending Women Safety emergency or call where gender is female
        // MUST immediately populate at the very top of the table until initialized.
        $pendingWomenSortRaw = "CASE WHEN (calls.caller_gender = 'female' OR calls.call_type_id = 10) AND calls.status = 'pending' THEN 0 ELSE 1 END ASC";

        // Deterministic ordering: always apply secondary tiebreaker (calls.id)
        // to prevent unstable pagination across pages with duplicate timestamps
        if ($sort === 'created_at') {
            $calls = $query->orderByRaw($pendingWomenSortRaw)->orderBy('calls.created_at', $dir)->orderBy('calls.id', $dir)->paginate($perPage);
        } else {
            $calls = $query->orderByRaw($pendingWomenSortRaw)->orderBy('calls.' . $sort, $dir)->orderBy('calls.id', 'desc')->paginate($perPage);
        }

        if ($request->wantsJson()) {
            return $this->getAjaxResponse($calls, $stats);
        }

        $title = 'Help Management';
        $assignment = $request->input('assignment', in_array($request->status, ['assigned', 'unassigned']) ? $request->status : null);
        if ($assignment === 'assigned') $title = 'Assigned Operational Helps';
        if ($assignment === 'unassigned') $title = 'Unassigned Helps Directory';
        if ($request->status === 'pending') $title = 'Pending Helps';
        if ($request->status === 'in_progress') $title = 'In-Process Helps';
        if ($request->status === 'tiger_reached') $title = 'Tiger Reached Helps';
        if ($request->status === 'completed') $title = 'Resolved Helps';

        return view('calls.index', array_merge(compact('calls', 'stats'), $filterData))->with('title', $title);
    }

    public function assigned(Request $request)
    {
        $request->merge(['assignment' => 'assigned']);
        return $this->index($request);
    }

    public function unassigned(Request $request)
    {
        $request->merge(['assignment' => 'unassigned']);
        return $this->index($request);
    }

    public function pending(Request $request)
    {
        $request->merge(['status' => 'pending']);
        return $this->index($request);
    }

    public function inprogress(Request $request)
    {
        $request->merge(['status' => 'in_progress']);
        return $this->index($request);
    }

    public function tigerReached(Request $request)
    {
        $request->merge(['status' => 'tiger_reached']);
        return $this->index($request);
    }

    public function completed(Request $request)
    {
        $request->merge(['status' => 'completed']);
        return $this->index($request);
    }

    public function show(Call $call)
    {
        $this->authorize('view', $call);

        // Canonical UUID Redirect: If accessed via legacy integer ID in a standard browser navigation, permanently redirect (301) to canonical UUID URL
        $rawParam = request()->route()?->originalParameters()['call'] ?? request()->segment(2);
        if (!request()->wantsJson() && !request()->ajax() && is_numeric($rawParam) && !empty($call->uuid)) {
            return redirect()->to(route('calls.show', $call), 301);
        }

        $call->load([
            'callType:id,name,category,icon',
            'callSubType:id,name,icon,priority',
            'office.parent.parent',
            'agent:id,full_name,username,designation_id',
            'agent.designation:id,name',
            'carriageway:id,road,road_name,road_short',
            'vehicleType:id,name,color_hex',
            'statusHistory' => fn($q) => $q->with(['changedBy:id,full_name,username', 'changedBy.role:id,name,display_name', 'user:id,full_name,username'])->latest(),
        ]);

        if (request()->wantsJson()) {
            return (new CallShowResource($call))->response();
        }

        return view('calls.show', compact('call'));
    }

    public function create()
    {
        $this->authorize('create', Call::class);
        $callTypes = cache()->rememberForever('master:call_types', fn() => 
            CallType::where('is_active', true)->with(['subTypes' => fn($q) => $q->where('is_active', true)])->orderBy('sort_order', 'asc')->get()
        );
        
        $vehicleTypes = cache()->rememberForever('master:vehicle_types', fn() => 
            VehicleType::where('is_active', true)->get()
        );
        
        $carriageways = Carriageway::where('status', 'active')
            ->select('id', 'type', 'road', 'road_short', 'road_name', 'road_from', 'road_to')
            ->orderBy('road_name')
            ->get();
        
        $zones = Office::zones()->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $sectors = Office::sectors()->where('is_active', true)
            ->select('id', 'parent_id as zone_id', 'name')
            ->orderBy('name')
            ->get();
        
        $beats = Office::beats()->where('is_active', true)
            ->select('id', 'parent_id as sector_id', 'name')
            ->orderBy('name')
            ->get();
        
        return view('calls.create', compact(
            'callTypes', 'vehicleTypes', 'carriageways', 'zones', 'sectors', 'beats'
        ));
    }

    public function edit(Call $call)
    {
        $this->authorize('update', $call);
        $callTypes = cache()->rememberForever('master:call_types', fn() => CallType::where('is_active', true)->with(['subTypes' => fn($q) => $q->where('is_active', true)])->get());
        $vehicleTypes = cache()->rememberForever('master:vehicle_types', fn() => VehicleType::where('is_active', true)->get());
        $carriageways = cache()->rememberForever('master:carriageways', fn() => Carriageway::where('status', 'active')->get());
        $beats = cache()->rememberForever('master:beats', fn() => Office::beats()->where('is_active', true)->get());
        
        return view('calls.edit', compact('call', 'callTypes', 'vehicleTypes', 'carriageways', 'beats'));
    }

    public function update(Request $request, Call $call)
    {
        $this->authorize('update', $call);
        $callType = null;
        if ($request->call_type_id) {
            $callType = CallType::find($request->call_type_id);
        }
        $isJunkSilent = $callType && $callType->category === 'junk_silent';

        $validated = $request->validate([
            'caller_number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $clean = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($clean) !== 11) {
                        $fail('The ' . $attribute . ' must be exactly 11 digits.');
                    }
                },
            ],
            'caller_name' => 'nullable|string|max:150',
            'call_type_id' => 'required',
            'call_sub_type_id' => 'required|exists:call_sub_types,id',
            'details' => 'nullable|string',
            'vehicle_type_id' => 'nullable',
            'vehicle_no' => 'nullable|string|max:30',
            'carriageway_id' => 'nullable',
            'km_marker_text' => ['nullable', 'string', 'max:20', 'regex:/^\s*\d{1,4}(\.\d{1,2})?\s*[- ]?\s*(NB|SB|N|S)?\s*$/i'],
            'beat_id' => ($isJunkSilent ? 'nullable' : 'required') . '|exists:offices,id',
            'priority' => 'nullable',
            'caller_lat' => 'nullable',
            'caller_lng' => 'nullable',
            'status' => 'nullable|in:pending,in_progress,tiger_reached,completed,cancelled,junk,forwarded',
            'location_details' => 'nullable|string',
        ]);

        try {
            // Sanitize: Convert empty strings to null for database compatibility
            $data = array_map(fn($v) => $v === '' ? null : $v, $validated);

            // Map priority string to integer if provided as text (e.g., 'medium')
            if (isset($data['priority']) && !is_numeric($data['priority'])) {
                $priorities = ['high' => 1, 'medium' => 3, 'low' => 5, 'emergency' => 1];
                $data['priority'] = $priorities[strtolower($data['priority'])] ?? 3;
            }

            // Map beat_id to office_id for unified structure
            if (isset($data['beat_id'])) {
                $data['office_id'] = $data['beat_id'];
            }
            unset($data['beat_id'], $data['zone_id'], $data['sector_id']);

            $call->update($data);
            return redirect()->route('calls.show', $call)->with('success', 'Operational record updated successfully.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $this->authorize('create', Call::class);

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        $payloadHash = null;
        $idempotencyLock = null;

        if ($idempotencyKey) {
            // Compute canonical payload hash across key operational inputs
            $payloadHash = md5(json_encode([
                'caller_number' => preg_replace('/[^0-9]/', '', (string)$request->input('caller_number')),
                'caller_name' => (string)$request->input('caller_name'),
                'caller_gender' => (string)$request->input('caller_gender'),
                'call_type_id' => (string)$request->input('call_type_id'),
                'call_sub_type_id' => (string)$request->input('call_sub_type_id'),
                'details' => (string)$request->input('details'),
                'beat_id' => (string)($request->input('beat_id') ?? $request->input('office_id')),
                'vehicle_no' => (string)$request->input('vehicle_no'),
            ]));

            // Fast path: Check existing cache before lock acquisition
            $cachedRecord = Cache::get("call:idempotency:{$idempotencyKey}");
            if ($cachedRecord) {
                $cachedCallId = is_array($cachedRecord) ? ($cachedRecord['call_id'] ?? null) : $cachedRecord;
                $cachedHash = is_array($cachedRecord) ? ($cachedRecord['hash'] ?? null) : null;

                // Reusing same key with different payload: reject safely
                if ($cachedHash && $cachedHash !== $payloadHash) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Idempotency key reused with mismatched request payload.',
                        'error_code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH'
                    ], 409);
                }

                if ($cachedCallId && ($existingCall = Call::find($cachedCallId))) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Operational record synchronized (idempotent duplicate): ' . $existingCall->call_number,
                            'call' => $existingCall,
                            'idempotent_duplicate' => true
                        ]);
                    }
                    return redirect()
                        ->route('calls.create')
                        ->with('success', 'Operational record synchronized: ' . $existingCall->call_number);
                }
            }

            // Acquire atomic Redis lock to serialize concurrent duplicate submissions
            try {
                $idempotencyLock = Cache::lock("call:idempotency:lock:{$idempotencyKey}", 10);
                $idempotencyLock->block(5);
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                // If lock times out, recheck if concurrent worker finished creation
                $cachedRecord = Cache::get("call:idempotency:{$idempotencyKey}");
                $cachedCallId = is_array($cachedRecord) ? ($cachedRecord['call_id'] ?? null) : $cachedRecord;
                if ($cachedCallId && ($existingCall = Call::find($cachedCallId))) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Operational record synchronized (idempotent duplicate): ' . $existingCall->call_number,
                        'call' => $existingCall,
                        'idempotent_duplicate' => true
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Concurrent request in progress for this idempotency key. Please retry.',
                    'error_code' => 'CONCURRENT_IDEMPOTENCY_CONFLICT'
                ], 429);
            }

            // Inside lock, re-check cache to prevent race condition
            $cachedRecord = Cache::get("call:idempotency:{$idempotencyKey}");
            if ($cachedRecord) {
                $cachedCallId = is_array($cachedRecord) ? ($cachedRecord['call_id'] ?? null) : $cachedRecord;
                $cachedHash = is_array($cachedRecord) ? ($cachedRecord['hash'] ?? null) : null;
                $idempotencyLock->release();
                $idempotencyLock = null;

                if ($cachedHash && $cachedHash !== $payloadHash) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Idempotency key reused with mismatched request payload.',
                        'error_code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH'
                    ], 409);
                }

                if ($cachedCallId && ($existingCall = Call::find($cachedCallId))) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Operational record synchronized (idempotent duplicate): ' . $existingCall->call_number,
                            'call' => $existingCall,
                            'idempotent_duplicate' => true
                        ]);
                    }
                    return redirect()
                        ->route('calls.create')
                        ->with('success', 'Operational record synchronized: ' . $existingCall->call_number);
                }
            }
        }

        // Decode base64-encoded free-text fields (WAF bypass encoding applied in JS)
        foreach (['details', 'location_details'] as $field) {
            $val = $request->input($field);
            if ($val && preg_match('/^[A-Za-z0-9+\/]+=*$/', $val)) {
                $decoded = base64_decode($val, true);
                if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                    // Prevent double-decoding clean text (e.g. 'RINGING') by checking for control chars
                    if (!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', $decoded)) {
                        $request->merge([$field => $decoded]);
                    }
                }
            }
        }

        $callType = CallType::find($request->call_type_id);
        $isExempt = $callType && in_array($callType->category, ['junk_silent', 'information']);

        $validated = $request->validate([
            'caller_number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $clean = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($clean) !== 11) {
                        $fail('The ' . $attribute . ' must be exactly 11 digits.');
                    }
                },
            ],
            'caller_name' => 'nullable|string|max:150',
            'caller_gender' => 'nullable|string|in:male,female,other',
            'call_type_id' => 'required|exists:call_types,id',
            'call_sub_type_id' => 'required|exists:call_sub_types,id',
            'details' => 'nullable|string',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'vehicle_no' => 'nullable|string|max:30',
            'carriageway_id' => 'nullable|exists:carriageways,id',
            'km_marker_text' => [$isExempt ? 'nullable' : 'required', 'string', 'max:20', 'regex:/^\s*\d{1,4}(\.\d{1,2})?\s*[- ]?\s*(NB|SB|N|S)?\s*$/i'],
            'beat_id' => ($isExempt ? 'nullable' : 'required') . '|exists:offices,id',
            'priority' => 'nullable|integer|min:1|max:5',
            'caller_lat' => 'nullable|numeric',
            'caller_lng' => 'nullable|numeric',
            'location_details' => 'nullable|string',
        ], [
            'km_marker_text.required' => 'KM marker is required so the help can be placed on the road (e.g. 504 or 24 NB).',
            'km_marker_text.regex'    => 'KM marker must be a number with an optional side, e.g. 504, 24-N or 327 SB.',
        ]);

        try {
            // Sanitize: Convert empty strings to null
            $data = array_map(fn($v) => $v === '' ? null : $v, $validated);

            // Map beat_id to office_id
            if (isset($data['beat_id'])) {
                $data['office_id'] = $data['beat_id'];
            }
            unset($data['beat_id'], $data['zone_id'], $data['sector_id']);
            
            // Map priority string to integer if provided as text
            if (isset($data['priority']) && !is_numeric($data['priority'])) {
                $priorities = ['high' => 1, 'medium' => 3, 'low' => 5, 'emergency' => 1];
                $data['priority'] = $priorities[strtolower($data['priority'])] ?? 3;
            }

            /** @var User $currentUser */
            $currentUser = Auth::user();
            $call = $this->callService->createCall($data, $currentUser);

            // Invalidate overview statistics cache across all nodes atomically
            try {
                Cache::increment('crm:calls:stats_version');
            } catch (Throwable $e) {
                Cache::put('crm:calls:stats_version', 2, 86400);
            }

            if ($idempotencyKey && isset($call->id)) {
                Cache::put("call:idempotency:{$idempotencyKey}", [
                    'call_id' => $call->id,
                    'hash' => $payloadHash,
                    'created_at' => now()->toIso8601String(),
                ], 300);
            }
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Operational record synchronized: ' . $call->call_number,
                    'call' => $call
                ]);
            }
            
            // For regular form submission, redirect back to create page with success message
            return redirect()
                ->route('calls.create')
                ->with('success', 'Operational record synchronized: ' . $call->call_number);
                
        } catch (Exception $e) {
            Log::error('Call creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add Help: ' . $e->getMessage()
                ], 422);
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to sync record: ' . $e->getMessage());
        } finally {
            if ($idempotencyLock) {
                try {
                    $idempotencyLock->release();
                } catch (Throwable $t) {
                    // Lock release safety
                }
            }
        }
    }

    public function updateStatus(Request $request, Call $call)
    {
        $this->authorize('manageStatus', $call);
        try {
            /** @var User $currentUser */
            $currentUser = Auth::user();
            $this->callService->updateStatus($call, $request->status, $currentUser, $request->all());

            // Invalidate overview statistics cache across all nodes atomically
            try {
                Cache::increment('crm:calls:stats_version');
            } catch (Throwable $e) {
                Cache::put('crm:calls:stats_version', 2, 86400);
            }
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status updated successfully.',
                    'status' => $call->fresh()->status
                ]);
            }

            return back()->with('success', 'Status updated successfully.');
        } catch (Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Update failed: ' . $e->getMessage()
                ], 422);
            }
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }


    // Advanced AJAX: Search caller history
    public function searchCallerHistory(Request $request)
    {
        $this->authorize('calls.api_lookup');
        $this->authorize('viewAny', Call::class);

        $num = $request->input('number', $request->input('caller_number'));
        if (!$num || strlen($num) < 7) return response()->json(['calls' => []]);

        $bidx = phone_blind_index($num);

        $query = Call::with(['office.parent.parent', 'agent'])
            ->where(function ($q) use ($num, $bidx) {
                if ($bidx) {
                    $q->where('caller_number_bidx', $bidx)
                      ->orWhere('caller_number', $num);
                } else {
                    $q->where('caller_number', $num);
                }
            });

        // Call-centre agents handle callbacks for ANY caller, so caller history is shared across agents
        // (read-only, limited to 5 rows, requires calls.api_lookup). All other roles stay scope-limited.
        if (Auth::user()?->role?->name !== 'agent') {
            $query = $this->applyScopeVisibility($query);
        }

        $calls = $query->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        /** @var User|null $user */
        $user = Auth::user();
        $showAgent = !in_array($user->role?->name, ['zone_admin', 'sector_admin', 'beat_operator']);

        $formattedCalls = $calls->map(function ($c) use ($showAgent) {
            $beat = $c->office;
            $sector = ($beat && $beat->type === 'beat') ? $beat->parent : ($beat && $beat->type === 'sector' ? $beat : null);
            $zone = $sector ? $sector->parent : ($beat && $beat->type === 'zone' ? $beat : null);

            return [
                'id' => $c->id,
                'uuid' => $c->uuid ?? null,
                'call_number' => $c->call_number,
                'caller_name' => $c->caller_name,
                'caller_gender' => $c->caller_gender ?? ($c->call_type_id == 10 ? 'female' : null),
                'caller_number' => $c->caller_number,
                'status' => $c->status,
                'created_at' => $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : '',
                'details' => $c->details,
                'priority' => $c->priority,
                'call_type_id' => $c->call_type_id,
                'call_sub_type_id' => $c->call_sub_type_id,
                'vehicle_type_id' => $c->vehicle_type_id,
                'office_id' => $c->office_id,
                'zone_name' => $zone?->name,
                'sector_name' => $sector?->name,
                'beat_name' => ($beat && $beat->type === 'beat') ? $beat->name : ($beat?->name),
                'agent_name' => $showAgent ? ($c->agent?->full_name ?? 'N/A') : null,
                'location_details' => $c->location_details,
                'call_reminder_count' => $c->call_reminder_count,
                'last_reminder_at' => $c->last_reminder_at,
            ];
        });

        return response()->json([
            'name' => $calls->first() ? $calls->first()->caller_name : null,
            'vehicle_no' => $calls->first() ? $calls->first()->vehicle_no : null,
            'calls' => $formattedCalls
        ]);
    }

    /**
     * Send Call Reminder to operational units.
     */
    public function sendReminder(Request $request, Call $call = null)
    {
        $this->authorize('calls.api_lookup');
        if (!$call) {
            $ref = $request->input('ref') ?? $request->input('id') ?? $request->input('call_id');
            if (Str::isUuid($ref)) {
                $call = Call::where('uuid', $ref)->firstOrFail();
            } else {
                $call = Call::findOrFail($ref);
            }
        }

        // Must be authorized to dispatch reminder on targeted call
        $this->authorize('reminder', $call);

        try {
            /** @var User $currentUser */
            $currentUser = Auth::user();
            $call = $this->callService->sendReminder($call, $currentUser);
            return response()->json([
                'success' => true,
                'message' => "Reminder sent successfully for call {$call->call_number}.",
                'call' => [
                    'id' => $call->id,
                    'uuid' => $call->uuid,
                    'call_reminder_count' => $call->call_reminder_count,
                    'last_reminder_at' => $call->last_reminder_at ? $call->last_reminder_at->toDateTimeString() : null,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to send reminder: " . $e->getMessage()
            ], 422);
        }
    }

    // Advanced AJAX: Get Available Beat Resources
    public function getBeatResources(Request $request)
    {
        $this->authorize('calls.api_lookup');
        $beatId = $request->input('beat_id');
        if (!$beatId) return response()->json(['officers' => []]);

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->is_active) {
            return response()->json(['officers' => []], 403);
        }

        $isPrivileged = in_array($user->role?->name, ['super_admin', 'operation_admin', 'agent_supervisor'], true);
        if (!$isPrivileged) {
            $allowedOfficeIds = [];
            foreach ($user->activeScopes ?? [] as $scope) {
                if (!empty($scope->office_id)) {
                    $allowedOfficeIds = array_merge($allowedOfficeIds, $this->getOfficeDescendantIds((int) $scope->office_id));
                }
            }
            if (!in_array((int) $beatId, $allowedOfficeIds, true)) {
                return response()->json(['officers' => []], 403);
            }
        }

        $query = User::query()->where('is_active', true)->whereHas('activeScopes', function($q) use ($beatId) {
            $q->where('office_id', $beatId);
        });

        $officers = $query->get(['id', 'username', 'full_name']);

        return response()->json(['officers' => $officers]);
    }

    public function destroy(Call $call)
    {
        $this->authorize('delete', $call);
        try {
            $call->delete();
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Heartbeat Polling: Simplified real-time discovery for pending help acquisitions.
     */
    public function getNotificationsPoll(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $this->authorize('calls.api_lookup');

        try {
            $allowedRoles = ['beat_operator', 'sector_admin', 'zone_admin'];
            $userRole = $user->role ? $user->role->name : null;

            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'new_calls' => [],
                    'timestamp' => now()->toDateTimeString()
                ]);
            }

            $since = $request->query('since');
            
            $query = Call::query()->where('status', 'pending');
            
            if ($since) {
                try {
                    $dtSince = Carbon::parse($since);
                    $query->where(function($q) use ($dtSince) {
                        $q->where('created_at', '>', $dtSince)
                          ->orWhere('last_reminder_at', '>', $dtSince);
                    });
                } catch (Throwable $e) {}
            }

            $query = $this->applyScopeVisibility($query);
            $newCalls = $query->select([
                    'id', 'call_number', 'caller_name', 'created_at',
                    'last_reminder_at', 'call_type_id', 'call_sub_type_id', 'office_id'
                ])
                ->with([
                    'callType:id,name',
                    'callSubType:id,name'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate current total pending helps count for navigation badge
            $pendingQuery = Call::query()->where('status', 'pending');
            $pendingCount = $this->applyScopeVisibility($pendingQuery)->count();

            $results = $newCalls->map(function($call) {
                return [
                    'id' => $call->id,
                    'call_number' => $call->call_number,
                    'caller_name' => $call->caller_name,
                    'created_at' => $call->created_at->toIso8601String(),
                    'last_reminder_at' => $call->last_reminder_at ? $call->last_reminder_at->toIso8601String() : null,
                    'call_type' => $call->callType ? ['name' => $call->callType->name] : null,
                    'call_sub_type' => $call->callSubType ? ['name' => $call->callSubType->name] : null,
                ];
            });

            return response()->json([
                'new_calls' => $results,
                'pending_count' => $pendingCount,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $e) {
            Log::error('Notifications poll failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Unable to retrieve notification updates at this time.',
                'new_calls' => [],
                'timestamp' => now()->toIso8601String()
            ], 500);
        }
    }


    public function exportCsv(Request $request)
    {
        $this->authorize('export', Call::class);

        try {
            /** @var User|null $user */
            $user = Auth::user();
            $showAgent = !in_array($user->role?->name, ['zone_admin', 'sector_admin', 'beat_operator']);

            $baseQuery = $this->applyScopeVisibility(Call::query());
            $query = $this->applyFiltersAndSearch($baseQuery, $request);

            // Sorting logic
            $sort = $request->input('sort', 'created_at');
            $direction = $request->input('direction', 'desc');
            $allowedSorts = ['call_number', 'created_at', 'priority', 'status', 'caller_name', 'caller_number', 'vehicle_no'];
            
            if ($sort === 'beat_id') $sort = 'office_id';

            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('status', 'asc')->orderBy('created_at', 'desc');
            }

            // Optimize query for export: minimal columns, no large remarks/details
            $query->select([
                'id', 'call_number', 'created_at', 'priority', 'status',
                'call_type_id', 'call_sub_type_id', 'office_id',
                'caller_name', 'caller_number', 'vehicle_no',
                'vehicle_type_id', 'agent_id'
            ])->with([
                'callType:id,name', 
                'callSubType:id,name', 
                'office:id,name,type,parent_id',
                'office.parent:id,name,type,parent_id',
                'office.parent.parent:id,name,type',
                'agent:id,full_name,username', 
                'vehicleType:id,name'
            ]);

            // Set execution limits for large exports
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');

            $filename = "calls_export_" . date('Y-m-d_H-i-s') . ".csv";
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function() use ($query, $showAgent) {
                $file = fopen('php://output', 'w');
                
                // Write Header
                $columns = [
                    'ID', 'Date', 'Time', 'Priority', 'Status', 'Category', 'Sub-Category',
                    'Zone', 'Sector', 'Beat', 'Caller Name', 'Caller Number', 'Vehicle No', 'Vehicle Type'
                ];
                if ($showAgent) {
                    array_splice($columns, 5, 0, ['Agent']);
                }
                fputcsv($file, $columns);

                // Stream records via cursor to prevent offset degradation and memory exhaustion
                $processedCount = 0;
                foreach ($query->cursor() as $call) {
                    $processedCount++;
                    // Efficiently find zone and sector from eager-loaded hierarchy
                    $beat = $call->office;
                    $sector = null;
                    $zone = null;

                    if ($beat) {
                        if ($beat->type === 'beat') {
                            $sector = $beat->parent;
                            $zone = $sector?->parent;
                        } elseif ($beat->type === 'sector') {
                            $sector = $beat;
                            $zone = $sector->parent;
                        } elseif ($beat->type === 'zone') {
                            $zone = $beat;
                        }
                    }

                    $row = [
                        $call->call_number,
                        $call->created_at ? $call->created_at->format('Y-m-d') : '',
                        $call->created_at ? $call->created_at->format('H:i:s') : '',
                        $call->priority,
                        $call->status,
                        $call->callType ? $call->callType->name : '',
                        $call->callSubType ? $call->callSubType->name : '',
                        $zone ? $zone->name : '',
                        $sector ? $sector->name : '',
                        $beat && $beat->type === 'beat' ? $beat->name : '',
                        $call->caller_name,
                        $call->caller_number,
                        $call->vehicle_no,
                        $call->vehicleType ? $call->vehicleType->name : ''
                    ];

                    if ($showAgent) {
                        array_splice($row, 5, 0, [$call->agent?->full_name ?? 'N/A']);
                    }
                    fputcsv($file, $row);

                    if ($processedCount % 500 === 0) {
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }

                fclose($file);
            }, 200, $headers);

        } catch (Throwable $e) {
            Log::error('Export failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If headers are already sent, we can't return a JSON/Redirect response
            if (headers_sent()) {
                exit("\n--- ERROR DURING EXPORT: " . $e->getMessage() . " ---");
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * KM / landmark lookup used by calls/create to auto-select Zone / Sector / Beat.
     * Returns ONLY what the page uses (landmark text + office id/name chain beat->sector->zone + contacts); no Eloquent
     * relations are loaded (office chain comes from the cached lite map) and the answer is cached for 2 minutes.
     */
    public function geospatialLookup(Request $request)
    {
        $this->authorize('calls.api_lookup');
        $road = $request->input('road');
        $kmMarker = $request->input('km_marker');
        $direction = $request->input('direction');

        $key = 'geo_lookup:v1:' . md5(json_encode([$road, $kmMarker, $direction]));
        $landmarks = Cache::remember($key, 120, fn (): mixed => $this->buildGeospatialLookup($road, $kmMarker, $direction));

        return response()->json(['success' => true, 'landmarks' => $landmarks]);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildGeospatialLookup(?string $road, ?string $kmMarker, ?string $direction): array
    {
        $offices = Office::liteMap();

        // beat -> sector -> zone chain, only id + name (exactly what updateSpatialContext() reads)
        $chain = function (?int $officeId) use ($offices): ?array {
            $beat = $officeId ? ($offices[$officeId] ?? null) : null;
            if (! $beat) {
                return null;
            }
            $node = ['id' => $beat['id'], 'name' => $beat['name'], 'parent' => null];
            $sector = $beat['parent_id'] ? ($offices[$beat['parent_id']] ?? null) : null;
            if ($sector) {
                $node['parent'] = ['id' => $sector['id'], 'name' => $sector['name'], 'parent' => null];
                $zone = $sector['parent_id'] ? ($offices[$sector['parent_id']] ?? null) : null;
                if ($zone) {
                    $node['parent']['parent'] = ['id' => $zone['id'], 'name' => $zone['name']];
                }
            }
            return $node;
        };

        // First non-empty phone going beat -> sector -> zone
        $officePhone = function (int $officeId) use ($offices): ?string {
            for ($i = 0, $cur = $offices[$officeId] ?? null; $cur && $i < 4; $i++) {
                if ($cur['phone']) {
                    return $cur['phone'];
                }
                $cur = $cur['parent_id'] ? ($offices[$cur['parent_id']] ?? null) : null;
            }
            return null;
        };

        $q = GeospatialLandmark::query()->select([
            'id', 'office_id', 'road_name', 'km_marker', 'location_name', 'nearby_cities', 'contact_numbers', 'agent_prompt',
        ]);
        if ($road) {
            $q->where('road_name', 'like', "%{$road}%");
        }
        if ($kmMarker) {
            $q->where('km_marker', 'like', "%{$kmMarker}%");
        }
        if ($direction) {
            $q->where('bound_direction', 'like', "%{$direction}%");
        }

        $out = [];
        foreach ($q->limit(5)->get() as $lm) {
            $out[] = [
                'id'              => $lm->id,
                'road_name'       => $lm->road_name,
                'km_marker'       => $lm->km_marker,
                'location_name'   => $lm->location_name,
                'nearby_cities'   => $lm->nearby_cities,
                'contact_numbers' => $lm->contact_numbers,
                'agent_prompt'    => $lm->agent_prompt,
                'office'          => $chain($lm->office_id ? (int) $lm->office_id : null),
            ];
        }

        // Beats whose KM range contains the marker ("Jurisdiction" matches)
        if ($kmMarker && is_numeric($kmMarker)) {
            $km = (float) $kmMarker;
            foreach ($offices as $o) {
                if ($o['type'] !== 'beat' || ! $o['is_active'] || $o['km_start'] === null || $o['km_end'] === null) {
                    continue;
                }
                if ($o['km_start'] > $km || $o['km_end'] < $km) {
                    continue;
                }
                // road label: nearest sector, else zone, else NHMP (same fallback as before)
                $roadName = null;
                for ($i = 0, $cur = $o; $cur && $i < 5 && $roadName === null; $i++) {
                    if ($cur['type'] === 'sector') {
                        $roadName = $cur['name'];
                    }
                    $cur = $cur['parent_id'] ? ($offices[$cur['parent_id']] ?? null) : null;
                }
                for ($i = 0, $cur = $o; $cur && $i < 5 && $roadName === null; $i++) {
                    if ($cur['type'] === 'zone') {
                        $roadName = $cur['name'];
                    }
                    $cur = $cur['parent_id'] ? ($offices[$cur['parent_id']] ?? null) : null;
                }
                $out[] = [
                    'id'              => 'office_' . $o['id'],
                    'location_name'   => 'KM Range: ' . (int) $o['km_start'] . ' - ' . (int) $o['km_end'],
                    'road_name'       => $roadName ?? 'NHMP',
                    'km_marker'       => $kmMarker,
                    'office'          => $chain($o['id']),
                    'agent_prompt'    => 'This KM falls within the operational boundary of ' . $o['name'],
                    'contact_numbers' => $officePhone($o['id']),
                    'is_range_match'  => true,
                ];
            }
        }

        return $out;
    }

    /**
     * Contact numbers shown for the selected Zone / Sector / Beat on calls/create.
     * Prefers the office's own phone; falls back to the landmark contact numbers under it (previous behaviour).
     * Cached 5 minutes per selection (office/landmark edits are rare) instead of 3-6 queries per click.
     */
    public function spatialContacts(Request $request)
    {
        $this->authorize('calls.api_lookup');
        $zoneId = (int) $request->input('zone_id');
        $sectorId = (int) $request->input('sector_id');
        $beatId = (int) $request->input('beat_id');

        $contacts = Cache::remember("spatial_contacts:v2:{$zoneId}:{$sectorId}:{$beatId}", 300, function () use ($zoneId, $sectorId, $beatId): mixed {
            $offices = Office::liteMap();
            $landmarkContact = function (array $officeIds): ?string {
                return $officeIds
                    ? (GeospatialLandmark::whereIn('office_id', $officeIds)->whereNotNull('contact_numbers')
                        ->where('contact_numbers', '!=', '')->value('contact_numbers') ?: null)
                    : null;
            };
            $resolve = function (int $id, bool $self) use ($offices, $landmarkContact): ?string {
                if (! $id || ! isset($offices[$id])) {
                    return null;
                }
                return $offices[$id]['phone'] ?? $landmarkContact($self ? [$id] : Office::getDescendantIdsFor($id, true));
            };

            return [
                'zone'   => $resolve($zoneId, false),
                'sector' => $resolve($sectorId, false),
                'beat'   => $resolve($beatId, true),
            ];
        });

        return response()->json($contacts);
    }
}
