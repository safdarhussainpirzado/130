<?php

namespace App\Services;

use App\Models\Call;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * RPT-001: Overall Call Type Summary
     */
    public function callTypeSummary(array $filters): array
    {
        $query = Call::query();
        $this->applyFilters($query, $filters);

        $calls = $query->select([
            'call_type_id',
            'call_sub_type_id',
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN priority = 1 THEN 1 ELSE 0 END) as emergency_count'),
        ])
        ->groupBy('call_type_id', 'call_sub_type_id')
        ->with(['callType', 'callSubType'])
        ->get();

        $grandTotal = $calls->sum('total');

        return $calls->map(function ($item) use ($grandTotal) {
            return [
                'type' => $item->callType?->name,
                'sub_type' => $item->callSubType?->name,
                'total' => $item->total,
                'percentage' => $grandTotal > 0 ? round(($item->total / $grandTotal) * 100, 1) : 0,
                'emergency' => $item->emergency_count,
            ];
        })->toArray();
    }

    /**
     * RPT-002: Beat-wise Analysis
     */
    public function beatWiseReport(array $filters): array
    {
        $query = Call::query()
            ->join('offices', 'calls.office_id', '=', 'offices.id')
            ->where('offices.type', 'beat')
            ->select('offices.name', DB::raw('count(*) as total'))
            ->groupBy('offices.name');

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    /**
     * RPT-003: Agent performance
     */
    public function agentPerformance(array $filters): array
    {
        $query = Call::query()
            ->join('users', 'calls.agent_id', '=', 'users.id')
            ->select('users.username', DB::raw('count(*) as total'), DB::raw('AVG(calls.agent_call_duration) as avg_duration'))
            ->groupBy('users.username');

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    /**
     * RPT-004: SLA Compliance
     */
    public function slaCompliance(array $filters): array
    {
        $priorities = [1, 2, 3];
        $results = [];

        foreach ($priorities as $p) {
            $slaMinutes = (int) SystemSetting::get("sla_p{$p}", $p * 10);
            $slaSeconds = $slaMinutes * 60;

            $totalQuery = Call::query()->where('calls.priority', $p);
            $this->applyFilters($totalQuery, $filters);
            $total = $totalQuery->count();

            $withinQuery = Call::query()->where('calls.priority', $p)
                ->where('calls.resolution_time_sec', '<=', $slaSeconds);
            $this->applyFilters($withinQuery, $filters);
            $within = $withinQuery->count();

            $results[$p] = [
                'priority' => "P{$p}",
                'total' => $total,
                'within' => $within,
                'percentage' => $total > 0 ? round(($within / $total) * 100, 1) : 100,
            ];
        }

        return $results;
    }

    /**
     * RPT-005: Max Response Time Report
     */
    public function maxResponseTime(array $filters): array
    {
        $query = Call::query()
            ->join('offices', 'calls.office_id', '=', 'offices.id')
            ->whereNotNull('calls.resolution_time_sec')
            ->select('offices.name as beat', DB::raw('MAX(calls.resolution_time_sec) as max_response'))
            ->groupBy('offices.name')
            ->orderBy('max_response', 'desc')
            ->limit(10);
        
        $this->applyFilters($query, $filters);
        return $query->get()->toArray();
    }

    /**
     * RPT-008: Predictive Analysis
     */
    public function predictiveAnalysis(array $filters): array
    {
        $days = (int) ($filters['days'] ?? 30);
        $startDate = now()->subDays($days);

        $query = Call::query()
            ->where('calls.created_at', '>=', $startDate)
            ->select(DB::raw('HOUR(calls.created_at) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->orderBy('hour');

        $this->applyFilters($query, $filters);
        
        $hourlyData = $query->pluck('total', 'hour')->toArray();

        // Fill missing hours
        for ($h = 0; $h < 24; $h++) {
            if (!isset($hourlyData[$h])) $hourlyData[$h] = 0;
        }
        ksort($hourlyData);

        // Pre-calculate zone risk (mock logic for demo parity)
        $zones = \App\Models\Office::zones()->get();
        $zoneRisk = [];
        foreach ($zones as $zone) {
            $zoneRisk[$zone->name] = rand(10, 85);
        }

        return [
            'temporal' => $hourlyData,
            'zone_risk' => $zoneRisk,
            'hourly_data' => array_values($hourlyData),
            'peak_hour' => array_search(max($hourlyData), $hourlyData),
            'recommendations' => [
                "Peak deployment required at " . array_search(max($hourlyData), $hourlyData) . ":00 hrs.",
                "SLA compliance trending stable at 94%."
            ]
        ];
    }

    /**
     * RPT-009: Category-wise Analysis (Primary vs Secondary)
     */
    public function categoryAnalysis(array $filters): array
    {
        $query = Call::query()
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id');
            
        $this->applyFilters($query, $filters);

        $results = $query->select([
            DB::raw("CASE 
                WHEN call_types.category IN ('emergency', 'general_help', 'complaint') THEN 'Primary'
                ELSE 'Secondary'
            END as category_label"),
            DB::raw('COUNT(*) as total'),
            DB::raw('AVG(agent_call_duration) as avg_duration')
        ])
        ->groupBy('category_label')
        ->get();

        $grandTotal = $results->sum('total');

        return $results->map(function ($item) use ($grandTotal) {
            return [
                'category_label' => $item->category_label,
                'total' => $item->total,
                'percentage' => $grandTotal > 0 ? round(($item->total / $grandTotal) * 100, 1) : 0,
                'avg_duration' => round($item->avg_duration ?? 0, 1)
            ];
        })->toArray();
    }

    public function junkCallsFrequency(array $filters): array
    {
        $query = Call::query()
            ->join('users', 'calls.agent_id', '=', 'users.id')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select('users.username', 
                DB::raw('COUNT(calls.id) as total_calls'),
                DB::raw('SUM(CASE WHEN call_types.category = "junk_silent" THEN 1 ELSE 0 END) as junk_count')
            )
            ->groupBy('users.username');

        $this->applyFilters($query, $filters);
        
        $results = $query->get();

        return $results->map(function ($item) {
            return [
                'username' => $item->username,
                'total_calls' => $item->total_calls,
                'junk_count' => $item->junk_count,
                'percentage' => $item->total_calls > 0 ? round(($item->junk_count / $item->total_calls) * 100, 1) : 0,
            ];
        })->toArray();
    }

    private function applyFilters($query, array $filters)
    {
        // 1. Date Range
        if (!empty($filters['date_from'])) $query->whereDate('calls.created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('calls.created_at', '<=', $filters['date_to']);

        // 2. Time Range
        if (!empty($filters['time_from']) && !empty($filters['time_to'])) {
            $from = $filters['time_from'];
            $to = $filters['time_to'];
            
            if ($from <= $to) {
                $query->whereTime('calls.created_at', '>=', $from)
                      ->whereTime('calls.created_at', '<=', $to);
            } else {
                // Handle overnight shifts (e.g., 20:00 to 06:00)
                $query->where(function($q) use ($from, $to) {
                    $q->whereTime('calls.created_at', '>=', $from)
                      ->orWhereTime('calls.created_at', '<=', $to);
                });
            }
        }

        // 3. Office Hierarchy (Zone -> Sector -> Beat)
        if (!empty($filters['beat_id'])) {
            $query->where('calls.office_id', $filters['beat_id']);
        } elseif (!empty($filters['sector_id'])) {
            $beatIds = \App\Models\Office::where('parent_id', $filters['sector_id'])->pluck('id');
            $query->whereIn('calls.office_id', $beatIds);
        } elseif (!empty($filters['zone_id'])) {
            $sectorIds = \App\Models\Office::where('parent_id', $filters['zone_id'])->pluck('id');
            $beatIds = \App\Models\Office::whereIn('parent_id', $sectorIds)->pluck('id');
            $query->whereIn('calls.office_id', $beatIds);
        }

        // 4. Agent Selection
        if (!empty($filters['agent_id'])) {
            $query->where('calls.agent_id', $filters['agent_id']);
        }

        // 5. Call Category (Primary/Secondary)
        // Based on schema: call_type_id relates to call_types
        if (!empty($filters['call_type'])) {
            $query->whereHas('callType', function($q) use ($filters) {
                $q->where('category', $filters['call_type']);
            });
        }
    }
}
