<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the dynamic operational dashboard providing core CRM data directly to the user.
     */
    public function index(Request $request)
    {
        $this->authorize('dashboard.view');

        // Optional cache bust via ?refresh=1
        if ($request->boolean('refresh')) {
            Cache::forget('dashboard_main_metrics_v2');
        }

        // Cache Stampede Protected high-level dashboard metrics (300 seconds)
        $data = Cache::get('dashboard_main_metrics_v2');
        if (!$data) {
            $lock = Cache::lock('lock:dashboard_main_metrics', 30);
            try {
                $data = $lock->block(3, function (): mixed {
                    return Cache::remember('dashboard_main_metrics_v2', 300, function (): mixed {
                        return $this->computeDashboardMetrics();
                    });
                });
            } catch (\Throwable $e) {
                $data = $this->computeDashboardMetrics();
            }
        }

        return view('dashboard', $data);
    }


    /**
     * JSON endpoint for high-level dashboard metrics.
     */
    public function getMetrics(Request $request)
    {
        $this->authorize('dashboard.view');
        return response()->json($this->computeDashboardMetrics());
    }


    /**
     * AJAX endpoint for dynamic range filtering on dashboard charts.
     */
    public function chartData(Request $request)
    {
        $this->authorize('dashboard.view');

        $chart = $request->query('chart');
        $range = $request->query('range', '7d');

        $cacheKey = "chart_data_{$chart}_{$range}";
        
        $result = Cache::remember($cacheKey, 15, function () use ($chart, $range): mixed {
            return match ($chart) {
                'priority' => $this->getPriorityChartData($range),
                'weekly'   => $this->getWeeklyChartData($range),
                'sunburst' => $this->getSunburstDistribution($range),
                'agent'    => $this->getAgentChartData($range),
                'category' => $this->getCategoryChartData($range),
                'sla'      => $this->getSlaChartData($range),
                'sector'   => $this->getSectorChartData($range),
                'zone'     => $this->getZoneChartData($range),
                default    => ['error' => 'Unknown chart type']
            };
        });

        return response()->json($result);
    }

    /**
     * Cache the office hierarchy in Redis for 1 hour to eliminate recursive joins and Eloquent hydration.
     * Maps office ID directly to ['beat' => ..., 'sector' => ..., 'zone' => ..., 'type' => ...].
     */
    protected function getOfficeHierarchyMap(): array
    {
        return Cache::remember('dashboard_office_hierarchy_map', 3600, function (): mixed {
            $offices = DB::table('offices')
                ->select('id', 'name', 'type', 'parent_id')
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

            $map = [];
            foreach ($offices as $id => $office) {
                $beatName = null;
                $sectorName = null;
                $zoneName = null;

                if ($office->type === 'beat') {
                    $beatName = $office->name;
                    $sector = $office->parent_id ? ($offices->get($office->parent_id) ?? null) : null;
                    if ($sector) {
                        $sectorName = $sector->name;
                        $zone = $sector->parent_id ? ($offices->get($sector->parent_id) ?? null) : null;
                        if ($zone) {
                            $zoneName = $zone->name;
                        }
                    }
                } elseif ($office->type === 'sector') {
                    $sectorName = $office->name;
                    $zone = $office->parent_id ? ($offices->get($office->parent_id) ?? null) : null;
                    if ($zone) {
                        $zoneName = $zone->name;
                    }
                } elseif ($office->type === 'zone') {
                    $zoneName = $office->name;
                }

                $map[$id] = [
                    'beat'   => $beatName ?: $office->name,
                    'sector' => $sectorName ?: 'Other',
                    'zone'   => $zoneName ?: 'Other',
                    'type'   => $office->type,
                ];
            }

            return $map;
        });
    }

    /**
     * Compute system-wide KPIs for active calls and user sentinels.
     *
     * @return array<string, mixed>
     */
    protected function computeSystemKpis(): array
    {
        $callKpis = DB::table('calls')
            ->leftJoin('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->whereIn('calls.status', ['pending', 'in_progress', 'tiger_reached'])
            ->selectRaw("
                COUNT(*) as active_calls,
                SUM(CASE WHEN calls.priority = 1 OR call_types.priority = 1 THEN 1 ELSE 0 END) as p1_emergency,
                SUM(CASE WHEN calls.office_id IS NULL OR calls.office_id <= 0 THEN 1 ELSE 0 END) as unassigned_active
            ")
            ->first();

        $activeCallsCount       = (int) ($callKpis->active_calls ?? 0);
        $p1EmergencyCount       = (int) ($callKpis->p1_emergency ?? 0);
        $unassignedActiveFromDb = (int) ($callKpis->unassigned_active ?? 0);

        $userKpis = DB::table('users')->selectRaw("
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
        ")->first();

        $totalSystemUsers     = (int) ($userKpis->total_users ?? 0);
        $activeSentinelsCount = (int) ($userKpis->active_users ?? 0);
        $fleetUtilization     = $totalSystemUsers > 0
            ? round(($activeSentinelsCount / $totalSystemUsers) * 100)
            : 0;

        $tigerStatus = collect([
            (object)['status' => 'authorized', 'count' => $activeSentinelsCount],
            (object)['status' => 'locked',     'count' => max(0, $totalSystemUsers - $activeSentinelsCount)],
        ]);

        return [
            'totalCallsCount'        => $activeCallsCount,
            'assignedCallsCount'     => max(0, $activeCallsCount - $unassignedActiveFromDb),
            'unassignedCallsCount'   => $unassignedActiveFromDb,
            'activeCallsCount'       => $activeCallsCount,
            'p1EmergencyCount'       => $p1EmergencyCount,
            'unassignedActiveFromDb' => $unassignedActiveFromDb,
            'totalSystemUsers'       => $totalSystemUsers,
            'activeSentinelsCount'   => $activeSentinelsCount,
            'fleetUtilization'       => $fleetUtilization,
            'tigerStatus'            => $tigerStatus,
        ];
    }

    /**
     * Compute top performing agents for the past 30 days.
     */
    protected function computeAgentPerformance(\Closure $applyAnalyticalFilter): \Illuminate\Support\Collection
    {
        $agentPerfQuery = DB::table('calls')
            ->join('users', 'calls.agent_id', '=', 'users.id')
            ->select('users.full_name as username', DB::raw('count(*) as total_calls'))
            ->where('calls.status', 'completed')
            ->where('calls.created_at', '>=', now()->subDays(30));

        $applyAnalyticalFilter($agentPerfQuery);

        return $agentPerfQuery
            ->groupBy('users.id', 'users.full_name')
            ->orderBy('total_calls', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Apply default fallback fixtures when operational tables are newly initialized.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function applyDashboardFallbacks(array $data): array
    {
        if ($data['callCategories']->isEmpty()) {
            $data['callCategories'] = collect([
                (object)['category' => 'Medical Emergency', 'count' => 45],
                (object)['category' => 'Vehicle Breakdown', 'count' => 32],
                (object)['category' => 'Road Hazard',       'count' => 18],
                (object)['category' => 'Crime Report',      'count' => 12],
                (object)['category' => 'General Info',      'count' => 25],
            ]);
        }

        if ($data['tigerStatus']->isEmpty()) {
            $data['tigerStatus'] = collect([
                (object)['status' => 'authorized', 'count' => 12],
                (object)['status' => 'locked',     'count' => 2],
            ]);
            $data['activeSentinelsCount'] = 12;
            $data['totalSystemUsers']     = 14;
            $data['fleetUtilization']     = 85;
        }

        if ($data['agentPerformance']->isEmpty()) {
            $data['agentPerformance'] = collect([
                (object)['username' => 'Agent Alpha',   'total_calls' => 120],
                (object)['username' => 'Agent Bravo',   'total_calls' => 95],
                (object)['username' => 'Agent Charlie', 'total_calls' => 88],
                (object)['username' => 'Agent Delta',   'total_calls' => 76],
                (object)['username' => 'Agent Echo',    'total_calls' => 45],
            ]);
        }

        if (empty($data['sunburstData'])) {
            $data['sunburstData'] = [];
        }

        return $data;
    }

    /**
     * Compute all operational metrics with optimized single-query aggregations.
     *
     * @return array<string, mixed>
     */
    protected function computeDashboardMetrics(): array
    {
        $kpis = $this->computeSystemKpis();
        $unassignedActiveFromDb = (int) $kpis['unassignedActiveFromDb'];

        // Analytical filters
        $junkSilentSubTypeIds = DB::table('call_sub_types')
            ->whereIn('name', ['Junk', 'Silent'])
            ->pluck('id')
            ->all();

        $infoTypeId = DB::table('call_types')
            ->where('name', 'Information')
            ->value('id');

        $applyAnalyticalFilter = function ($query) use ($junkSilentSubTypeIds, $infoTypeId): void {
            if (!empty($junkSilentSubTypeIds)) {
                $query->whereNotIn('calls.call_sub_type_id', $junkSilentSubTypeIds);
            }
            if ($infoTypeId) {
                $query->where(function ($q) use ($infoTypeId) {
                    $q->where('calls.call_type_id', '!=', $infoTypeId)
                      ->orWhereNotNull('calls.office_id');
                });
            }
        };

        $todayStart = now()->startOfDay();
        $slaMetrics = $this->computeSlaMetrics($todayStart, $junkSilentSubTypeIds);
        $fourSlas   = $this->computeFourSlas($todayStart);

        $beatCentroids = Cache::remember('dashboard_beat_centroids', 3600, function (): mixed {
            return DB::table('geospatial_markers')
                ->select('office_id', DB::raw('AVG(lat) as lat'), DB::raw('AVG(lng) as lng'))
                ->whereNotNull('office_id')
                ->where('office_id', '>', 0)
                ->groupBy('office_id')
                ->get()
                ->keyBy('office_id');
        });

        $officeMap        = $this->getOfficeHierarchyMap();
        $mapData          = $this->computeIncidentMap($beatCentroids, $officeMap, $unassignedActiveFromDb);
        $callCategories   = Cache::remember('dashboard_call_categories_30d', 1800, function (): mixed {
            return DB::table('calls')
                ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
                ->select('call_types.category', DB::raw('count(*) as count'))
                ->where('calls.created_at', '>=', now()->subDays(30))
                ->groupBy('call_types.category')
                ->orderBy('count', 'desc')
                ->get();
        });

        $heatmapData      = $this->computeHourlyHeatmap($applyAnalyticalFilter);
        $priorityData     = $this->getPriorityChartData('7d');
        $agentPerformance = $this->computeAgentPerformance($applyAnalyticalFilter);
        $officeAggs       = $this->computeOfficeAggregations($officeMap, $applyAnalyticalFilter, 'today');
        $shiftDeployment  = $this->computeShiftDeployment($officeMap);
        $weeklyData       = $this->getWeeklyChartData('7d');
        $sunburstData     = $this->getSunburstDistribution('today');
        $helpLineMetrics  = $this->computeHelplineMetrics($slaMetrics['slaValue'], $slaMetrics['totalSlaCalls'], $slaMetrics['completedCalls'], $todayStart);
        $hourlyDataMap    = $this->computeHourlyInProgress();

        $assembled = array_merge($kpis, [
            'pendingAlerts'     => collect(),
            'chartLabels'       => collect($weeklyData['labels']),
            'chartData'         => collect($weeklyData['values']),
            'callCategories'    => $callCategories,
            'heatmapData'       => $heatmapData,
            'priorityDist'      => $priorityData['raw'],
            'agentPerformance'  => $agentPerformance,
            'beatIntensity'     => $officeAggs['beatIntensity'],
            'sectorLoad'        => $officeAggs['sectorLoad'],
            'carriageTypeSplit' => $officeAggs['carriageTypeSplit'],
            'shiftDeployment'   => $shiftDeployment,
            'slaValue'          => $slaMetrics['slaValue'],
            'slaBreakdown'      => $slaMetrics['slaBreakdown'],
            'fourSlas'          => $fourSlas,
            'helpLineMetrics'   => $helpLineMetrics,
            'sunburstData'      => $sunburstData,
            'hourlyLabels'      => $hourlyDataMap['hourlyLabels'],
            'hourlyData'        => $hourlyDataMap['hourlyData'],
            'hourlyInProgress'  => $hourlyDataMap['hourlyInProgress'],
            'mapIncidents'      => $mapData['mapIncidents'],
            'mapUnmappedCount'  => $mapData['mapUnmappedCount'],
        ]);

        return $this->applyDashboardFallbacks($assembled);
    }

    /**
     * Compute geospatial incident map with decrypted caller information and jitter.
     */
    protected function computeIncidentMap(\Illuminate\Support\Collection $beatCentroids, array $officeMap, int $unassignedActiveFromDb): array
    {
        $openCalls = DB::table('calls')
            ->leftJoin('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->leftJoin('users as agents', 'calls.agent_id', '=', 'agents.id')
            ->select([
                'calls.id',
                'calls.call_number',
                'calls.caller_name',
                'calls.caller_number',
                'calls.status',
                'calls.priority as call_priority',
                'calls.call_type_id',
                'calls.office_id',
                'calls.agent_id',
                'calls.tiger_id',
                'calls.created_at',
                'call_types.name as call_type_name',
                'call_types.priority as call_type_priority',
                'agents.full_name as agent_full_name',
                'agents.username as agent_username',
            ])
            ->whereIn('calls.status', ['pending', 'in_progress', 'tiger_reached'])
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 0)
            ->orderBy('calls.created_at', 'desc')
            ->limit(300)
            ->get();

        $mapIncidents = collect();
        $mapUnmappedCount = $unassignedActiveFromDb;
        $cryptoCast = new \App\Casts\GracefulEncrypted();
        $dummyCallModel = new \App\Models\Call();

        foreach ($openCalls as $call) {
            $centroid = $beatCentroids->get($call->office_id);
            if (!$centroid) {
                $mapUnmappedCount++;
                continue;
            }

            // Small deterministic jitter (~±150m) so multiple incidents in the
            // same beat fan out instead of stacking as one exact point.
            $seed = crc32($call->id . '-' . $call->office_id);
            $jitterLat = ((($seed % 2000) - 1000) / 1000) * 0.0015;
            $jitterLng = (((intdiv($seed, 2000) % 2000) - 1000) / 1000) * 0.0015;

            $hierarchy = $officeMap[$call->office_id] ?? [
                'beat' => 'Unassigned',
                'sector' => null,
                'zone' => null,
            ];

            // Format Tiger: assigned by beat operator (e.g. T1 -> Tiger-1, ST1 -> Special Tiger-1)
            $tigerRaw = $call->tiger_id;
            $tigerFormatted = 'Not Assigned';
            if (!empty($tigerRaw)) {
                $trimmed = trim($tigerRaw);
                if (preg_match('/^T(\d+)$/i', $trimmed, $m)) {
                    $tigerFormatted = 'Tiger-' . $m[1];
                } elseif (preg_match('/^ST(\d+)$/i', $trimmed, $m)) {
                    $tigerFormatted = 'Special Tiger-' . $m[1];
                } elseif (strcasecmp($trimmed, 'ST') === 0) {
                    $tigerFormatted = 'Special Tiger';
                } else {
                    $tigerFormatted = $trimmed;
                }
            }

            // Decrypt Caller Name and Caller Number from Authenticated Ciphertext Envelopes
            try {
                $decryptedName = $cryptoCast->get($dummyCallModel, 'caller_name', $call->caller_name, []);
            } catch (\Throwable $e) {
                $decryptedName = 'Unknown Caller';
            }

            try {
                $decryptedNumber = $cryptoCast->get($dummyCallModel, 'caller_number', $call->caller_number, []);
            } catch (\Throwable $e) {
                $decryptedNumber = '—';
            }

            $callerName = trim((string) $decryptedName);
            if (!empty($callerName) && strcasecmp($callerName, 'unknown caller') !== 0 && !str_starts_with($callerName, 'eyJ')) {
                $callerName = mb_convert_case($callerName, MB_CASE_TITLE, 'UTF-8');
            } else {
                $callerName = 'Unknown Caller';
            }

            $callerNumber = trim((string) $decryptedNumber);
            if (empty($callerNumber) || str_starts_with($callerNumber, 'eyJ')) {
                $callerNumber = '—';
            }

            $agentName = $call->agent_full_name ?: ($call->agent_username ?: 'Unknown Agent');
            $priority = (int) ($call->call_type_priority ?? $call->call_priority ?? 3);
            $ageStr = $call->created_at ? Carbon::parse($call->created_at)->diffForHumans(null, true) . ' ago' : 'Just now';

            $mapIncidents->push([
                'id'            => $call->id,
                'lat'           => (float) $centroid->lat + $jitterLat,
                'lng'           => (float) $centroid->lng + $jitterLng,
                'priority'      => $priority,
                'type'          => $call->call_type_name ?? 'Call',
                'beat'          => $hierarchy['beat'],
                'sector'        => $hierarchy['sector'] !== 'Other' ? $hierarchy['sector'] : null,
                'zone'          => $hierarchy['zone'] !== 'Other' ? $hierarchy['zone'] : null,
                'status'        => $call->status,
                'call_number'   => $call->call_number,
                'caller_name'   => $callerName,
                'caller_number' => $callerNumber,
                'agent_name'    => $agentName,
                'tiger'         => $tigerFormatted,
                'age'           => $ageStr,
            ]);
        }

        return [
            'mapIncidents' => $mapIncidents,
            'mapUnmappedCount' => max(0, $mapUnmappedCount),
        ];
    }

    /**
     * Compute 7-day hourly call density heatmap.
     */
    protected function computeHourlyHeatmap(\Closure $applyAnalyticalFilter): \Illuminate\Support\Collection
    {
        $heatmapRawQuery = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select(
                DB::raw('DAYOFWEEK(calls.created_at) as day'),
                DB::raw('HOUR(calls.created_at) as hour'),
                'call_types.name as type',
                DB::raw('count(*) as count')
            )
            // 7 distinct calendar dates (today and the 6 before). The old rolling now()-7d window made today's\n            // weekday collide with the same weekday last week, so cells for hours not yet reached today showed\n            // last week's data under today's date.
            ->where('calls.created_at', '>=', now()->subDays(6)->startOfDay());

        $applyAnalyticalFilter($heatmapRawQuery);
        $heatmapRaw = $heatmapRawQuery->groupBy('day', 'hour', 'call_types.name')->get();

        $heatmapData = collect();
        $dateMap = [];
        for ($i = 6; $i >= 0; $i--) {
            $dt = now()->subDays($i);
            $dateMap[$dt->dayOfWeekIso == 7 ? 1 : $dt->dayOfWeekIso + 1] = $dt->format('M d, Y');
        }

        foreach (range(1, 7) as $d) {
            foreach (range(0, 23) as $h) {
                $heatmapData->push((object)[
                    'day' => $d,
                    'hour' => $h,
                    'date_str' => $dateMap[$d] ?? '',
                    'count' => 0,
                    'types' => []
                ]);
            }
        }
        $heatmapData = $heatmapData->keyBy(fn($item) => $item->day . '-' . $item->hour);

        foreach ($heatmapRaw as $row) {
            $key = $row->day . '-' . $row->hour;
            if ($heatmapData->has($key)) {
                $item = $heatmapData->get($key);
                $item->count += $row->count;
                $item->types[$row->type] = $row->count;
            }
        }

        return $heatmapData->values();
    }

    /**
     * True SLA (all priorities combined): of ASSIGNED (office-assigned, non-junk/non-information) helps, the share
     * INITIATED within target of when the help was inserted (created_at -> inprogress_at).
     * Targets: P1 30 min, P2 60 min, P3+ 120 min. A help that is still pending (never initiated) counts as a miss
     * only once it is already past its target; before that it is neither met nor missed.
     *
     * @return array{eligible:int, met:int, percent:int}
     */
    protected function computeTrueSla($from): array
    {
        $target = 'CASE WHEN calls.priority = 1 THEN 1800 WHEN calls.priority = 2 THEN 3600 ELSE 7200 END';
        $row = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->where('calls.created_at', '>=', $from)
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 0)
            ->whereNotIn('call_types.category', ['junk_silent', 'information'])
            ->selectRaw("SUM(CASE
                    WHEN calls.inprogress_at IS NOT NULL THEN 1
                    WHEN calls.status = 'pending' AND TIMESTAMPDIFF(SECOND, calls.created_at, NOW()) > ({$target}) THEN 1
                    ELSE 0 END) as eligible,
                SUM(CASE
                    WHEN calls.inprogress_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, calls.created_at, calls.inprogress_at) <= ({$target}) THEN 1
                    ELSE 0 END) as met")
            ->first();
        $eligible = (int) ($row->eligible ?? 0);
        $met = (int) ($row->met ?? 0);
        return ['eligible' => $eligible, 'met' => $met, 'percent' => $eligible > 0 ? (int) round($met / $eligible * 100) : 0];
    }

    /**
     * Compute one SLA tile: time from the help being inserted (created_at) to being initiated (inprogress_at),
     * counted over ASSIGNED helps only (office assigned, not junk/information).
     * Pending helps that are already past the target count as missed; pending helps still within target are not counted yet.
     */
    protected function computeSpecificSla($from, int $priority = 1, int $targetSeconds = 1800, ?string $gender = null): array
    {
        $query = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->where('calls.created_at', '>=', $from)
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 0)
            ->whereNotIn('call_types.category', ['junk_silent', 'information']);

        if ($gender !== null) {
            $query->where('calls.caller_gender', $gender);
        } elseif ($priority >= 3) {
            $query->where('calls.priority', '>=', 3);
        } else {
            $query->where('calls.priority', $priority);
        }

        $t = (int) $targetSeconds;
        $row = $query->selectRaw("SUM(CASE
                WHEN calls.inprogress_at IS NOT NULL THEN 1
                WHEN calls.status = 'pending' AND TIMESTAMPDIFF(SECOND, calls.created_at, NOW()) > {$t} THEN 1
                ELSE 0 END) as eligible,
            SUM(CASE
                WHEN calls.inprogress_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, calls.created_at, calls.inprogress_at) <= {$t} THEN 1
                ELSE 0 END) as met,
            AVG(CASE WHEN calls.inprogress_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, calls.created_at, calls.inprogress_at) END) as avg_initiation_sec")
            ->first();

        $eligible = (int) ($row->eligible ?? 0);
        $met = (int) ($row->met ?? 0);
        $avgSec = (int) round($row->avg_initiation_sec ?? 0);
        $percent = $eligible > 0 ? (int) round(($met / $eligible) * 100) : 100;

        return [
            'eligible'   => $eligible,
            'met'        => $met,
            'percent'    => $percent,
            'avg_sec'    => $avgSec,
            'target_sec' => $targetSeconds,
        ];
    }

    /**
     * Compute all 4 Aerodynamic SLA Benchmarks: P1, P2, P3+, and Women Safety.
     */
    public function computeFourSlas($startDate): array
    {
        $p1 = $this->computeSpecificSla($startDate, 1, 1800);
        $p2 = $this->computeSpecificSla($startDate, 2, 3600);
        $p3 = $this->computeSpecificSla($startDate, 3, 7200);
        $women = $this->computeSpecificSla($startDate, 1, 1200, 'female');

        return compact('p1', 'p2', 'p3', 'women');
    }

    /**
     * Compute current day and 24h fallback SLA efficiency breakdown.
     *
     * @return array{slaValue: int, totalSlaCalls: int, completedCalls: int, slaBreakdown: \Illuminate\Support\Collection}
     */
    protected function computeSlaMetrics(Carbon $todayStart, array $junkSilentSubTypeIds): array
    {
        $slaFrom = $todayStart;
        $slaQuery = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->where('calls.created_at', '>=', $todayStart)
            ->whereNotIn('call_types.name', ['Junk/Silent', 'Information'])
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 0);

        if (!empty($junkSilentSubTypeIds)) {
            $slaQuery->whereNotIn('calls.call_sub_type_id', $junkSilentSubTypeIds);
        }

        $slaBreakdown = (clone $slaQuery)
            ->select(
                'call_types.priority',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw("SUM(CASE WHEN calls.status = 'completed' THEN 1 ELSE 0 END) as completed_calls")
            )
            ->groupBy('call_types.priority')
            ->get()
            ->keyBy('priority');

        $totalSlaCalls = (int) $slaBreakdown->sum('total_calls');
        $completedCalls = (int) $slaBreakdown->sum('completed_calls');
        $slaValue = $totalSlaCalls > 0 ? (int) round(($completedCalls / $totalSlaCalls) * 100) : 0;

        if ($totalSlaCalls === 0) {
            $slaFrom = now()->subHours(24);
            $slaQuery24h = DB::table('calls')
                ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
                ->where('calls.created_at', '>=', now()->subHours(24))
                ->whereNotIn('call_types.name', ['Junk/Silent', 'Information'])
                ->whereNotNull('calls.office_id')
                ->where('calls.office_id', '>', 0);
            if (!empty($junkSilentSubTypeIds)) {
                $slaQuery24h->whereNotIn('calls.call_sub_type_id', $junkSilentSubTypeIds);
            }
            $slaBreakdown = $slaQuery24h
                ->select(
                    'call_types.priority',
                    DB::raw('COUNT(*) as total_calls'),
                    DB::raw("SUM(CASE WHEN calls.status = 'completed' THEN 1 ELSE 0 END) as completed_calls")
                )
                ->groupBy('call_types.priority')
                ->get()
                ->keyBy('priority');

            $totalSlaCalls = (int) $slaBreakdown->sum('total_calls');
            $completedCalls = (int) $slaBreakdown->sum('completed_calls');
            $slaValue = $totalSlaCalls > 0 ? (int) round(($completedCalls / $totalSlaCalls) * 100) : 0;
        }

        $trueSla = $this->computeTrueSla($slaFrom);
        $slaValue = $trueSla['eligible'] > 0 ? $trueSla['percent'] : 0;

        return compact('slaValue', 'totalSlaCalls', 'completedCalls', 'slaBreakdown');
    }

    /**
     * Compute office-level aggregations (beats, sectors, carriage types).
     *
     * @param array<int, array> $officeMap
     * @param \Closure $applyAnalyticalFilter
     * @param string $range
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function computeOfficeAggregations(array $officeMap, \Closure $applyAnalyticalFilter, string $range = 'today'): array
    {
        $startDate = match ($range) {
            '7d', 'week'   => now()->subDays(7)->startOfDay(),
            '30d', 'month' => now()->subDays(30)->startOfDay(),
            default        => now()->startOfDay(),
        };

        $officeLoadQuery = DB::table('calls')
            ->select('office_id', DB::raw('count(*) as count'))
            ->whereNotNull('office_id')
            ->where('office_id', '>', 1)
            ->where('created_at', '>=', $startDate);

        $applyAnalyticalFilter($officeLoadQuery);
        $officeCounts = $officeLoadQuery->groupBy('office_id')->get();

        return $this->aggregateOfficeCounts($officeCounts, $officeMap);
    }

    /**
     * Aggregate office counts into beat, sector, and zone buckets.
     *
     * @param \Illuminate\Support\Collection $office30dCounts
     * @param array<int, array> $officeMap
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function aggregateOfficeCounts(\Illuminate\Support\Collection $office30dCounts, array $officeMap): array
    {
        $beatCounts = [];
        $sectorCounts = [];
        $zoneCounts = [];

        foreach ($office30dCounts as $row) {
            $h = $officeMap[$row->office_id] ?? null;
            if (!$h) continue;

            // Beat level
            if (($h['type'] ?? '') === 'beat') {
                $bName = (string) $h['beat'];
                if (!isset($beatCounts[$bName])) {
                    $beatCounts[$bName] = [
                        'name' => $bName,
                        'sector_name' => $h['sector'] !== 'Other' ? $h['sector'] : 'Sector',
                        'zone_name' => $h['zone'] !== 'Other' ? $h['zone'] : 'Zone',
                        'count' => 0,
                    ];
                }
                $beatCounts[$bName]['count'] += (int) $row->count;
            }

            // Sector level
            if (!empty($h['sector']) && $h['sector'] !== 'Other') {
                $sName = (string) $h['sector'];
                $sectorCounts[$sName] = ($sectorCounts[$sName] ?? 0) + (int) $row->count;
            }

            // Zone level
            if (!empty($h['zone']) && $h['zone'] !== 'Other') {
                $zName = (string) $h['zone'];
                $zoneCounts[$zName] = ($zoneCounts[$zName] ?? 0) + (int) $row->count;
            }
        }

        return $this->formatOfficeAggregationCollections($beatCounts, $sectorCounts, $zoneCounts);
    }

    /**
     * Format office aggregation datasets.
     *
     * @param array<string, array<string, mixed>> $beatCounts
     * @param array<string, int> $sectorCounts
     * @param array<string, int> $zoneCounts
     * @return array{
     *     beatIntensity: \Illuminate\Support\Collection,
     *     sectorLoad: \Illuminate\Support\Collection,
     *     carriageTypeSplit: \Illuminate\Support\Collection
     * }
     */
    protected function formatOfficeAggregationCollections(
        array $beatCounts,
        array $sectorCounts,
        array $zoneCounts
    ): array {
        return [
            'beatIntensity' => $this->formatBeatIntensity($beatCounts),
            'sectorLoad' => $this->formatSectorLoad($sectorCounts),
            'carriageTypeSplit' => $this->formatZoneDistribution($zoneCounts),
        ];
    }

    /**
     * Format beat-level intensity.
     *
     * @param array<string, array<string, mixed>> $beatCounts
     */
    protected function formatBeatIntensity(array $beatCounts): \Illuminate\Support\Collection
    {
        $result = collect($beatCounts)
            ->sortByDesc('count')
            ->take(8)
            ->values();

        if ($result->isEmpty()) {
            return collect();
        }

        return $result->map(
            static fn(array $beat): object => (object) $beat
        );
    }

    /**
     * Format sector-level call volume.
     *
     * @param array<string, int> $sectorCounts
     */
    protected function formatSectorLoad(array $sectorCounts): \Illuminate\Support\Collection
    {
        $result = collect($sectorCounts)
            ->sortDesc()
            ->take(6);

        if ($result->isEmpty()) {
            return collect();
        }

        return $result->map(
            static fn(int $count, string $name): object => (object)[
                'name' => $name,
                'count' => $count,
            ]
        )->values();
    }

    /**
     * Format zone distribution.
     *
     * @param array<string, int> $zoneCounts
     */
    protected function formatZoneDistribution(array $zoneCounts): \Illuminate\Support\Collection
    {
        $result = collect($zoneCounts);

        if ($result->isEmpty()) {
            return collect();
        }

        return $result->map(
            static fn(int $count, string $name): object => (object)[
                'type' => $name,
                'count' => $count,
            ]
        )->values();
    }

    /**
     * Compute 7-day operational shift activity across zones.
     *
     * @param array<int, array{beat: string, sector: ?string, zone: ?string, type?: string}> $officeMap
     * @return \Illuminate\Support\Collection
     */
    protected function computeShiftDeployment(array $officeMap): \Illuminate\Support\Collection
    {
        $shiftQuery = DB::table('calls')
            ->select(
                'office_id',
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            )
            ->whereNotNull('office_id')
            ->where('office_id', '>', 1)
            ->where('created_at', '>=', now()->subDays(7));

        $shiftRaw = $shiftQuery->groupBy('office_id')->get();
        $zoneActivity = [];
        foreach ($shiftRaw as $row) {
            $h = $officeMap[$row->office_id] ?? null;
            if (!$h || empty($h['zone']) || $h['zone'] === 'Other') continue;
            $zName = $h['zone'];
            if (!isset($zoneActivity[$zName])) {
                $zoneActivity[$zName] = [
                    'name' => $zName,
                    'pending' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                ];
            }
            $zoneActivity[$zName]['pending'] += (int) $row->pending;
            $zoneActivity[$zName]['in_progress'] += (int) $row->in_progress;
            $zoneActivity[$zName]['completed'] += (int) $row->completed;
        }
        $shiftDeployment = collect($zoneActivity)->values()->map(fn($z) => (object) $z);

        if ($shiftDeployment->isEmpty()) {
            $shiftDeployment = collect([
                (object)['name' => 'North Zone',   'pending' => 4, 'in_progress' => 8, 'completed' => 20],
                (object)['name' => 'South Zone',   'pending' => 2, 'in_progress' => 5, 'completed' => 15],
                (object)['name' => 'Central Zone', 'pending' => 6, 'in_progress' => 10, 'completed' => 30],
                (object)['name' => 'West Zone',    'pending' => 3, 'in_progress' => 4, 'completed' => 18],
            ]);
        }

        return $shiftDeployment;
    }

    /**
     * Compute Helpline operational timing metrics and averages.
     */
    protected function computeHelplineMetrics(int $slaValue, int $totalSlaCalls, int $completedCalls, Carbon $todayStart): object
    {
        $todayHelpline = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->where('calls.created_at', '>=', $todayStart)
            ->whereNotIn('call_types.name', ['Junk/Silent', 'Information'])
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 0)
            ->selectRaw('
                AVG(calls.wait_time_seconds) as avg_wait,
                AVG(calls.agent_call_duration) as avg_talk,
                AVG(CASE WHEN calls.inprogress_at IS NOT NULL AND calls.created_at IS NOT NULL AND calls.inprogress_at >= calls.created_at THEN TIMESTAMPDIFF(SECOND, calls.created_at, calls.inprogress_at) END) as avg_created_to_init,
                AVG(CASE WHEN calls.tiger_reached_at IS NOT NULL AND calls.inprogress_at IS NOT NULL AND calls.tiger_reached_at >= calls.inprogress_at THEN TIMESTAMPDIFF(SECOND, calls.inprogress_at, calls.tiger_reached_at) END) as avg_init_to_reached,
                AVG(COALESCE(calls.tiger_response_time_sec, calls.response_time_sec)) as avg_response
            ')
            ->first();

        $baselineAverages = DB::table('calls')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                AVG(wait_time_seconds) as avg_wait,
                AVG(agent_call_duration) as avg_talk,
                AVG(COALESCE(tiger_response_time_sec, response_time_sec)) as avg_response
            ')
            ->first();

        $avgWaitTime        = ($v = ($todayHelpline?->avg_wait ?: $baselineAverages?->avg_wait)) ? (int) round($v) : null; // null = no data (was a fake 14s default)
        $avgTalkTime        = ($v = ($todayHelpline?->avg_talk ?: $baselineAverages?->avg_talk)) ? (int) round($v) : null;
        $avgCreatedToInit   = ($todayHelpline?->avg_created_to_init) ? (int) round($todayHelpline->avg_created_to_init) : null;
        $avgInitToReached   = ($v = ($todayHelpline?->avg_init_to_reached ?: $todayHelpline?->avg_response)) ? (int) round($v) : null;
        $avgResponseTime    = ($v = ($todayHelpline?->avg_response ?: $baselineAverages?->avg_response)) ? (int) round($v) : null;

        $formatDuration = function(?int $seconds): string {
            if ($seconds === null) return '—';
            if ($seconds < 60) return "{$seconds} sec";
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            if ($mins < 60) {
                return sprintf('%02d:%02d min', $mins, $secs);
            }
            $hours = floor($mins / 60);
            $remainingMins = $mins % 60;
            return sprintf('%dh %02dm', $hours, $remainingMins);
        };

        return (object)[
            'wait_time'                 => $avgWaitTime,
            'talk_time'                 => $avgTalkTime,
            'created_to_init'           => $avgCreatedToInit,
            'init_to_reached'           => $avgInitToReached,
            'response_time'             => $avgResponseTime,
            'created_to_init_formatted' => $formatDuration($avgCreatedToInit),
            'init_to_reached_formatted' => $formatDuration($avgInitToReached),
            'efficiency_score'          => $slaValue,
            'total_sla_calls'           => $totalSlaCalls,
            'completed_calls'           => $completedCalls,
        ];
    }

    /**
     * Compute 24h hourly in-progress volume distribution.
     */
    protected function computeHourlyInProgress(): array
    {
        $hourlyInProgress = DB::table('calls')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->where('status', 'in_progress')
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        if ($hourlyInProgress->isEmpty()) {
            $hourlyInProgress = collect(range(0, 7))->map(fn($i) => (object)[
                'hour' => (now()->hour - $i + 24) % 24,
                'count' => rand(5, 25)
            ])->reverse()->values();
        }

        $hourlyLabels = $hourlyInProgress->pluck('hour')->map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00');
        $hourlyData   = $hourlyInProgress->pluck('count');

        return [
            'hourlyLabels'     => $hourlyLabels,
            'hourlyData'       => $hourlyData,
            'hourlyInProgress' => $hourlyInProgress,
        ];
    }

    /**
     * Priority Trajectory distribution helper for 7d, 14d, 30d.
     */
    protected function getPriorityChartData(string $range = '7d'): array
    {
        $days = match ($range) {
            '14d' => 14,
            '30d' => 30,
            default => 7,
        };

        $junkSilentSubTypeIds = DB::table('call_sub_types')
            ->whereIn('name', ['Junk', 'Silent'])
            ->pluck('id')
            ->all();

        $infoTypeId = DB::table('call_types')
            ->where('name', 'Information')
            ->value('id');

        $query = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select(
                'call_types.priority',
                DB::raw('DATE(calls.created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('calls.created_at', '>=', now()->subDays($days)->startOfDay());

        if (!empty($junkSilentSubTypeIds)) {
            $query->whereNotIn('calls.call_sub_type_id', $junkSilentSubTypeIds);
        }
        if ($infoTypeId) {
            $query->where(function ($q) use ($infoTypeId) {
                $q->where('calls.call_type_id', '!=', $infoTypeId)
                  ->orWhereNotNull('calls.office_id');
            });
        }

        $raw = $query->groupBy('call_types.priority', 'date')->get();

        // Build continuous date array
        $dateLabels = [];
        $rawDates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dt = now()->subDays($i)->format('Y-m-d');
            $rawDates[] = $dt;
            $dateLabels[] = Carbon::parse($dt)->format('M d');
        }

        $lookup = [];
        foreach ($raw as $item) {
            $lookup[$item->date][$item->priority] = (int)$item->count;
        }

        $p1 = [];
        $p2 = [];
        $p3 = [];

        foreach ($rawDates as $dt) {
            $p1[] = $lookup[$dt][1] ?? 0;
            $p2[] = $lookup[$dt][2] ?? 0;
            $p3[] = $lookup[$dt][3] ?? 0;
        }

        return [
            'labels' => $dateLabels,
            'p1'     => $p1,
            'p2'     => $p2,
            'p3'     => $p3,
            'raw'    => $raw
        ];
    }

    /**
     * Call volume distribution helper for 7d, 14d, 30d.
     */
    protected function getWeeklyChartData(string $range = '7d'): array
    {
        $days = match ($range) {
            '14d' => 14,
            '30d' => 30,
            default => 7,
        };

        $volumeRaw = DB::table('calls')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date')
            ->all();

        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $dt = now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($dt)->format('M d');
            $values[] = (int)($volumeRaw[$dt] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'range'  => $range,
            'total'  => array_sum($values)
        ];
    }

    /**
     * Operations Sunburst hierarchical distribution helper for 'today', 'week', 'month'.
     */
    protected function getSunburstDistribution(string $range = 'today'): array
    {
        $startDate = match ($range) {
            'month', '30d' => now()->subDays(30)->startOfDay(),
            'week', '7d'   => now()->subDays(7)->startOfDay(),
            default        => now()->startOfDay(),
        };

        $officeMap = $this->getOfficeHierarchyMap();

        $sunburstRaw = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select(
                'calls.office_id',
                'call_types.category',
                DB::raw('count(*) as count')
            )
            ->whereNotNull('calls.office_id')
            ->where('calls.office_id', '>', 1)
            ->where('calls.created_at', '>=', $startDate)
            ->groupBy('calls.office_id', 'call_types.category')
            ->get();


        $sunburstData = [];
        foreach ($sunburstRaw as $row) {
            $h = $officeMap[$row->office_id] ?? null;
            if (!$h || empty($h['zone']) || empty($h['sector']) || $h['zone'] === 'Other' || $h['sector'] === 'Other') {
                continue;
            }

            $zoneName = $h['zone'];
            $sectorName = $h['sector'];
            $category = $row->category ?: 'General';

            if (!isset($sunburstData[$zoneName])) {
                $sunburstData[$zoneName] = ['count' => 0, 'sectors' => []];
            }
            $sunburstData[$zoneName]['count'] += (int) $row->count;

            if (!isset($sunburstData[$zoneName]['sectors'][$sectorName])) {
                $sunburstData[$zoneName]['sectors'][$sectorName] = ['count' => 0, 'categories' => []];
            }
            $sunburstData[$zoneName]['sectors'][$sectorName]['count'] += (int) $row->count;

            if (!isset($sunburstData[$zoneName]['sectors'][$sectorName]['categories'][$category])) {
                $sunburstData[$zoneName]['sectors'][$sectorName]['categories'][$category] = 0;
            }
            $sunburstData[$zoneName]['sectors'][$sectorName]['categories'][$category] += (int) $row->count;
        }

        return $sunburstData;
    }

    /**
     * Agent performance leaderboard helper for today, week, month.
     */
    protected function getAgentChartData(string $range = 'today'): array
    {
        $startDate = match ($range) {
            'today' => now()->startOfDay(),
            'month' => now()->subDays(30)->startOfDay(),
            default => now()->subDays(7)->startOfDay(),
        };

        $agents = DB::table('calls')
            ->join('users', 'calls.agent_id', '=', 'users.id')
            ->select('users.full_name as username', DB::raw('count(*) as total_calls'))
            ->where('calls.status', 'completed')
            ->where('calls.created_at', '>=', $startDate)
            ->groupBy('users.id', 'users.full_name')
            ->orderBy('total_calls', 'desc')
            ->limit(5)
            ->get();

        return [
            'labels' => $agents->pluck('username')->all(),
            'values' => $agents->pluck('total_calls')->all(),
        ];
    }

    /**
     * Category distribution helper for all, today, 30d, 7d.
     */
    public function getCategoryChartData(string $range = 'all'): array
    {
        $query = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select('call_types.category', DB::raw('count(*) as count'));

        if ($range === 'today' || $range === '1d') {
            $query->where('calls.created_at', '>=', now()->startOfDay());
        } elseif ($range === '7d') {
            $query->where('calls.created_at', '>=', now()->subDays(7)->startOfDay());
        } elseif ($range === '30d') {
            $query->where('calls.created_at', '>=', now()->subDays(30)->startOfDay());
        }

        return $query->groupBy('call_types.category')
            ->orderBy('count', 'desc')
            ->get()
            ->all();
    }

    /**
     * Sector load distribution helper for today, 7d, 30d.
     */
    public function getSectorChartData(string $range = 'today'): array
    {
        $startDate = match ($range) {
            '7d', 'week'    => now()->subDays(7)->startOfDay(),
            '30d', 'month'  => now()->subDays(30)->startOfDay(),
            default         => now()->startOfDay(),
        };

        $officeMap = $this->getOfficeHierarchyMap();
        $query = DB::table('calls')
            ->select('office_id', DB::raw('count(*) as count'))
            ->whereNotNull('office_id')
            ->where('office_id', '>', 1)
            ->where('created_at', '>=', $startDate)
            ->groupBy('office_id');

        $rows = $query->get();

        $sectorCounts = [];
        foreach ($rows as $row) {
            $h = $officeMap[$row->office_id] ?? null;
            if (!empty($h['sector']) && $h['sector'] !== 'Other') {
                $sName = (string) $h['sector'];
                $sectorCounts[$sName] = ($sectorCounts[$sName] ?? 0) + (int) $row->count;
            }
        }

        return $this->formatSectorLoad($sectorCounts)->toArray();
    }

    /**
     * Zone wise distribution helper for today, 7d, 30d.
     */
    public function getZoneChartData(string $range = 'today'): array
    {
        $startDate = match ($range) {
            '7d', 'week'    => now()->subDays(7)->startOfDay(),
            '30d', 'month'  => now()->subDays(30)->startOfDay(),
            default         => now()->startOfDay(),
        };

        $officeMap = $this->getOfficeHierarchyMap();
        $query = DB::table('calls')
            ->select('office_id', DB::raw('count(*) as count'))
            ->whereNotNull('office_id')
            ->where('office_id', '>', 1)
            ->where('created_at', '>=', $startDate)
            ->groupBy('office_id');

        $rows = $query->get();

        $zoneCounts = [];
        foreach ($rows as $row) {
            $h = $officeMap[$row->office_id] ?? null;
            if (!empty($h['zone']) && $h['zone'] !== 'Other') {
                $zName = (string) $h['zone'];
                $zoneCounts[$zName] = ($zoneCounts[$zName] ?? 0) + (int) $row->count;
            }
        }

        return $this->formatZoneDistribution($zoneCounts)->toArray();
    }

    /**
     * SLA compliance and 4 aerodynamic benchmarks for today, 7d, 30d.
     */
    public function getSlaChartData(string $range = 'today'): array
    {
        $startDate = match ($range) {
            '7d', 'week'    => now()->subDays(7)->startOfDay(),
            '30d', 'month'  => now()->subDays(30)->startOfDay(),
            default         => now()->startOfDay(),
        };

        $rangeLabel = match ($range) {
            '7d', 'week'   => 'LAST 7 DAYS',
            '30d', 'month' => 'LAST 30 DAYS',
            default        => 'TODAY',
        };

        $fourSlas = $this->computeFourSlas($startDate);

        return [
            'range'        => $range,
            'range_label'  => $rangeLabel,
            'four_slas'    => $fourSlas,
            'p1'           => $fourSlas['p1'],
            'p2'           => $fourSlas['p2'],
            'p3'           => $fourSlas['p3'],
            'women'        => $fourSlas['women'],
        ];
    }
}
