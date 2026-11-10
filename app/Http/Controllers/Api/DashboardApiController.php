<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallHourlyRollup;
use App\Services\Analytics\CallRollupService;
use App\Services\Analytics\DashboardCacheService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    public function __construct(
        protected DashboardCacheService $cacheService,
        protected CallRollupService $rollupService
    ) {}

    /**
     * 1. Live Operations API (Target: < 15ms)
     * Scoped strictly to active calls working set. MySQL authoritative with Redis SWR.
     */
    public function live(Request $request): JsonResponse
    {
        $this->authorize('dashboard.view');

        $user = $request->user();
        $context = [
            'user_id' => $user?->id,
            'role_id' => $user?->role_id,
        ];

        $data = $this->cacheService->rememberSwr('live_operations', 5, 15, function () {
            // Live KPIs: Indexed lookup over active calls only
            $callKpis = DB::table('calls')
                ->leftJoin('call_types', 'calls.call_type_id', '=', 'call_types.id')
                ->whereIn('calls.status', ['pending', 'in_progress', 'tiger_reached'])
                ->selectRaw("
                    COUNT(*) as active_calls,
                    SUM(CASE WHEN calls.priority = 1 OR call_types.priority = 1 THEN 1 ELSE 0 END) as p1_emergency,
                    SUM(CASE WHEN calls.office_id IS NULL OR calls.office_id <= 0 THEN 1 ELSE 0 END) as unassigned_active
                ")
                ->first();

            $activeCallsCount = (int) ($callKpis->active_calls ?? 0);
            $p1EmergencyCount = (int) ($callKpis->p1_emergency ?? 0);
            $unassignedCount  = (int) ($callKpis->unassigned_active ?? 0);
            $assignedCount    = max(0, $activeCallsCount - $unassignedCount);

            // Active Sentinels / Fleets
            $userKpis = DB::table('users')->selectRaw("
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
            ")->first();

            $totalUsers   = (int) ($userKpis->total_users ?? 0);
            $activeUsers  = (int) ($userKpis->active_users ?? 0);
            $fleetPercent = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;

            // Live Incidents for Map (Up to 200 open incidents)
            $beatCentroids = DB::table('geospatial_markers')
                ->select('office_id', DB::raw('AVG(lat) as lat'), DB::raw('AVG(lng) as lng'))
                ->whereNotNull('office_id')
                ->where('office_id', '>', 0)
                ->groupBy('office_id')
                ->get()
                ->keyBy('office_id');

            $openCalls = DB::table('calls')
                ->leftJoin('call_types', 'calls.call_type_id', '=', 'call_types.id')
                ->select([
                    'calls.id',
                    'calls.call_number',
                    'calls.status',
                    'calls.priority',
                    'calls.office_id',
                    'calls.created_at',
                    'call_types.name as call_type_name',
                ])
                ->whereIn('calls.status', ['pending', 'in_progress', 'tiger_reached'])
                ->orderBy('calls.created_at', 'desc')
                ->limit(200)
                ->get();

            $incidents = [];
            foreach ($openCalls as $call) {
                $centroid = $beatCentroids->get($call->office_id);
                if (!$centroid) continue;

                $seed = crc32($call->id . '-' . $call->office_id);
                $jitterLat = ((($seed % 2000) - 1000) / 1000) * 0.0015;
                $jitterLng = (((intdiv($seed, 2000) % 2000) - 1000) / 1000) * 0.0015;

                $incidents[] = [
                    'id'          => $call->id,
                    'call_number' => $call->call_number,
                    'lat'         => (float) $centroid->lat + $jitterLat,
                    'lng'         => (float) $centroid->lng + $jitterLng,
                    'status'      => $call->status,
                    'priority'    => (int) $call->priority,
                    'type'        => $call->call_type_name ?? 'Emergency',
                    'age'         => $call->created_at ? Carbon::parse($call->created_at)->diffForHumans(null, true) : 'Just now',
                ];
            }

            return [
                'kpis' => [
                    'active_calls'      => $activeCallsCount,
                    'p1_emergency'      => $p1EmergencyCount,
                    'assigned_calls'    => $assignedCount,
                    'unassigned_calls'  => $unassignedCount,
                    'fleet_utilization' => $fleetPercent,
                    'active_sentinels'  => $activeUsers,
                ],
                'map_incidents' => $incidents,
                'timestamp'     => now()->toIso8601String(),
            ];
        }, $context);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * 2. Historical Analytics API (Target: < 10ms)
     * Sourced from call_hourly_rollups with Redis SWR.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('dashboard.view');

        $range = strtolower($request->query('range', '1d'));
        $days = match ($range) {
            '1d', '24h', 'today' => 1,
            '30d', 'month'       => 30,
            '90d'                => 90,
            default              => 7, // 7d
        };

        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate   = now()->toDateString();

        $zoneId   = (int) $request->query('zone_id', 0);
        $sectorId = (int) $request->query('sector_id', 0);
        $beatId   = (int) $request->query('beat_id', 0);

        $context = [
            'range'     => $range,
            'zone_id'   => $zoneId,
            'sector_id' => $sectorId,
            'beat_id'   => $beatId,
        ];

        $data = $this->cacheService->rememberSwr("analytics_v2_{$range}_{$zoneId}_{$sectorId}_{$beatId}", 60, 300, function () use ($startDate, $endDate, $range, $days, $zoneId, $sectorId, $beatId) {
            $baseQuery = DB::table('call_hourly_rollups')
                ->whereBetween('stat_date', [$startDate, $endDate]);

            if ($zoneId > 0)   $baseQuery->where('zone_id', $zoneId);
            if ($sectorId > 0) $baseQuery->where('sector_id', $sectorId);
            if ($beatId > 0)   $baseQuery->where('beat_id', $beatId);

            // 1. Overall Aggregations
            $totals = (clone $baseQuery)->selectRaw("
                COALESCE(SUM(total_calls), 0) as total_calls,
                COALESCE(SUM(completed_calls), 0) as completed_calls,
                COALESCE(SUM(sum_response_seconds), 0) as sum_resp,
                COALESCE(SUM(response_count), 0) as count_resp,
                COALESCE(SUM(sum_resolution_seconds), 0) as sum_res,
                COALESCE(SUM(resolution_count), 0) as count_res,
                COALESCE(SUM(sla_met_count), 0) as sla_met,
                COALESCE(SUM(sla_eligible_count), 0) as sla_eligible
            ")->first();

            // Auto-heal: If rollups are empty for this window, backfill on-the-fly from calls table
            if ((int) ($totals->total_calls ?? 0) === 0) {
                $hasRawCalls = DB::table('calls')
                    ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->exists();

                if ($hasRawCalls) {
                    $this->rollupService->backfillHistorical($startDate, $endDate);

                    $totals = (clone $baseQuery)->selectRaw("
                        COALESCE(SUM(total_calls), 0) as total_calls,
                        COALESCE(SUM(completed_calls), 0) as completed_calls,
                        COALESCE(SUM(sum_response_seconds), 0) as sum_resp,
                        COALESCE(SUM(response_count), 0) as count_resp,
                        COALESCE(SUM(sum_resolution_seconds), 0) as sum_res,
                        COALESCE(SUM(resolution_count), 0) as count_res,
                        COALESCE(SUM(sla_met_count), 0) as sla_met,
                        COALESCE(SUM(sla_eligible_count), 0) as sla_eligible
                    ")->first();
                }
            }

            $avgResponseSec   = $totals->count_resp > 0 ? round($totals->sum_resp / $totals->count_resp, 1) : 0.0;
            $avgResolutionSec = $totals->count_res > 0 ? round($totals->sum_res / $totals->count_res, 1) : 0.0;
            $slaPercent       = $totals->sla_eligible > 0 ? round(($totals->sla_met / $totals->sla_eligible) * 100, 1) : 0.0;

            // 2. Category Distribution
            $categories = (clone $baseQuery)
                ->select('call_category', DB::raw('SUM(total_calls) as count'))
                ->groupBy('call_category')
                ->orderByDesc('count')
                ->get();

            // 3. Dot Matrix & Time Distribution (1D = 24 Hourly Columns; 7D/30D = Daily Columns)
            $matrixColumns = [];
            $maxDots = 10; // Number of vertical quantized dots per column

            if ($range === '1d' || $range === '24h' || $range === 'today') {
                $hourlyRaw = (clone $baseQuery)
                    ->selectRaw("
                        stat_hour as hour,
                        SUM(total_calls) as total_calls,
                        SUM(completed_calls) as completed_calls,
                        SUM(pending_calls) as pending_calls,
                        SUM(sla_met_count) as sla_met,
                        SUM(sla_eligible_count) as sla_eligible
                    ")
                    ->groupBy('stat_hour')
                    ->orderBy('stat_hour')
                    ->get()
                    ->keyBy('hour');

                $hourlyCategories = (clone $baseQuery)
                    ->selectRaw("stat_hour as hour, call_category, SUM(total_calls) as count")
                    ->groupBy('stat_hour', 'call_category')
                    ->orderByDesc('count')
                    ->get()
                    ->groupBy('hour');

                $maxVal = max(1, (int) $hourlyRaw->max('total_calls'));

                for ($h = 0; $h < 24; $h++) {
                    $item = $hourlyRaw->get($h);
                    $val = (int) ($item->total_calls ?? 0);
                    $comp = (int) ($item->completed_calls ?? 0);
                    $pend = (int) ($item->pending_calls ?? 0);
                    $slaMet = (int) ($item->sla_met ?? 0);
                    $slaEligible = (int) ($item->sla_eligible ?? 0);
                    $slaPct = $slaEligible > 0 ? round(($slaMet / $slaEligible) * 100, 1) : 100.0;
                    $ratio = $val / $maxVal;
                    $activeDots = $val > 0 ? max(1, (int) round($ratio * $maxDots)) : 0;

                    $hourFmt = Carbon::createFromTime($h, 0)->format('g A');
                    $shortFmt = Carbon::createFromTime($h, 0)->format('ga');
                    $fullLabel = Carbon::createFromTime($h, 0)->format('h:i A') . ' - ' . Carbon::createFromTime($h, 59)->format('h:i A');

                    $catItems = $hourlyCategories->get($h);
                    $types = [];
                    if ($catItems) {
                        foreach ($catItems as $ci) {
                            $catName = !empty($ci->call_category) ? ucwords(str_replace('_', ' ', $ci->call_category)) : 'General';
                            $types[$catName] = (int) $ci->count;
                        }
                    }

                    $matrixColumns[] = [
                        'key'            => (string) $h,
                        'label'          => $hourFmt,
                        'short_label'    => $shortFmt,
                        'full_label'     => $fullLabel,
                        'count'          => $val,
                        'completed'      => $comp,
                        'pending'        => $pend,
                        'sla_percentage' => $slaPct,
                        'ratio'          => round($ratio, 3),
                        'active_dots'    => $activeDots,
                        'max_dots'       => $maxDots,
                        'is_peak'        => ($val === $maxVal && $val > 0),
                        'types'          => $types,
                    ];
                }
            } else {
                $dailyRaw = (clone $baseQuery)
                    ->selectRaw("
                        stat_date,
                        SUM(total_calls) as total_calls,
                        SUM(completed_calls) as completed_calls,
                        SUM(pending_calls) as pending_calls,
                        SUM(sla_met_count) as sla_met,
                        SUM(sla_eligible_count) as sla_eligible
                    ")
                    ->groupBy('stat_date')
                    ->orderBy('stat_date', 'asc')
                    ->get()
                    ->keyBy('stat_date');

                $dailyCategories = (clone $baseQuery)
                    ->selectRaw("stat_date, call_category, SUM(total_calls) as count")
                    ->groupBy('stat_date', 'call_category')
                    ->orderByDesc('count')
                    ->get()
                    ->groupBy('stat_date');

                $maxVal = max(1, (int) $dailyRaw->max('total_calls'));

                for ($i = $days - 1; $i >= 0; $i--) {
                    $dStr = now()->subDays($i)->toDateString();
                    $carbonDate = Carbon::parse($dStr);
                    $item = $dailyRaw->get($dStr);
                    $val = (int) ($item->total_calls ?? 0);
                    $comp = (int) ($item->completed_calls ?? 0);
                    $pend = (int) ($item->pending_calls ?? 0);
                    $slaMet = (int) ($item->sla_met ?? 0);
                    $slaEligible = (int) ($item->sla_eligible ?? 0);
                    $slaPct = $slaEligible > 0 ? round(($slaMet / $slaEligible) * 100, 1) : 100.0;
                    $ratio = $val / $maxVal;
                    $activeDots = $val > 0 ? max(1, (int) round($ratio * $maxDots)) : 0;

                    $catItems = $dailyCategories->get($dStr);
                    $types = [];
                    if ($catItems) {
                        foreach ($catItems as $ci) {
                            $catName = !empty($ci->call_category) ? ucwords(str_replace('_', ' ', $ci->call_category)) : 'General';
                            $types[$catName] = (int) $ci->count;
                        }
                    }

                    $matrixColumns[] = [
                        'key'            => $dStr,
                        'label'          => $carbonDate->format('D, M d'),
                        'short_label'    => $carbonDate->format('M d'),
                        'full_label'     => $carbonDate->format('l, F d, Y'),
                        'count'          => $val,
                        'completed'      => $comp,
                        'pending'        => $pend,
                        'sla_percentage' => $slaPct,
                        'ratio'          => round($ratio, 3),
                        'active_dots'    => $activeDots,
                        'max_dots'       => $maxDots,
                        'is_peak'        => ($val === $maxVal && $val > 0),
                        'types'          => $types,
                    ];
                }
            }

            return [
                'range'   => $range,
                'summary' => [
                    'total_calls'            => (int) $totals->total_calls,
                    'completed_calls'        => (int) $totals->completed_calls,
                    'avg_response_seconds'   => $avgResponseSec,
                    'avg_resolution_seconds' => $avgResolutionSec,
                    'sla_percentage'         => $slaPercent,
                    'max_column_val'         => $maxVal ?? 0,
                ],
                'categories'     => $categories,
                'matrix_columns' => $matrixColumns,
            ];
        }, $context);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * 3. Background Reporting API
     * Long-range and granular breakdown queries that don't block main dashboard.
     */
    public function reporting(Request $request): JsonResponse
    {
        $this->authorize('dashboard.view');

        $range = $request->query('range', '30d');
        $days = match ($range) {
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days)->toDateString();
        $endDate   = now()->toDateString();

        $zonePerformance = DB::table('call_hourly_rollups')
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->where('zone_id', '>', 0)
            ->selectRaw("
                zone_id,
                SUM(total_calls) as total_calls,
                SUM(completed_calls) as completed_calls,
                SUM(sla_met_count) as sla_met,
                SUM(sla_eligible_count) as sla_eligible
            ")
            ->groupBy('zone_id')
            ->orderByDesc('total_calls')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'zone_performance' => $zonePerformance,
                'period' => ['start' => $startDate, 'end' => $endDate],
            ],
        ]);
    }
}
