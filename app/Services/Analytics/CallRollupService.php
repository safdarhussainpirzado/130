<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsOutboxEvent;
use App\Models\Call;
use App\Models\CallHourlyRollup;
use App\Models\Office;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CallRollupService
{
    /**
     * Cache the office hierarchy in Redis/Memory to map beat_id -> sector_id -> zone_id.
     */
    public function getOfficeHierarchyMap(): array
    {
        /** @var array $map */
        $map = Cache::remember('analytics_office_hierarchy_map', 3600, function (): mixed {
            $offices = DB::table('offices')
                ->select('id', 'name', 'type', 'parent_id')
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

            $map = [];
            foreach ($offices as $id => $office) {
                $zoneId = 0;
                $sectorId = 0;
                $beatId = 0;

                if ($office->type === 'beat') {
                    $beatId = (int) $office->id;
                    $sector = $office->parent_id ? ($offices->get($office->parent_id) ?? null) : null;
                    if ($sector) {
                        $sectorId = (int) $sector->id;
                        $zone = $sector->parent_id ? ($offices->get($sector->parent_id) ?? null) : null;
                        if ($zone) {
                            $zoneId = (int) $zone->id;
                        }
                    }
                } elseif ($office->type === 'sector') {
                    $sectorId = (int) $office->id;
                    $zone = $office->parent_id ? ($offices->get($office->parent_id) ?? null) : null;
                    if ($zone) {
                        $zoneId = (int) $zone->id;
                    }
                } elseif ($office->type === 'zone') {
                    $zoneId = (int) $office->id;
                }

                $map[$id] = [
                    'beat_id'   => $beatId,
                    'sector_id' => $sectorId,
                    'zone_id'   => $zoneId,
                    'type'      => $office->type,
                ];
            }

            return $map;
        });

        return is_array($map) ? $map : (array) $map;
    }

    /**
     * Resolve zone, sector, beat from an office ID.
     */
    public function resolveOfficeDimensions(?int $officeId): array
    {
        if (empty($officeId) || $officeId <= 0) {
            return ['zone_id' => 0, 'sector_id' => 0, 'beat_id' => 0];
        }

        $map = $this->getOfficeHierarchyMap();
        if (isset($map[$officeId])) {
            return [
                'zone_id'   => $map[$officeId]['zone_id'],
                'sector_id' => $map[$officeId]['sector_id'],
                'beat_id'   => $map[$officeId]['beat_id'] ?: $officeId,
            ];
        }

        return ['zone_id' => 0, 'sector_id' => 0, 'beat_id' => $officeId];
    }

    /**
     * Record an immutable Outbox Event within the operational transaction.
     */
    public function recordOutboxEvent(Call $call, string $eventType, ?array $oldAttributes = []): AnalyticsOutboxEvent
    {
        $createdAt = $call->created_at ? Carbon::parse($call->created_at) : now();
        $statDate = $createdAt->toDateString();
        $statHour = (int) $createdAt->format('H');

        $newOffice = $this->resolveOfficeDimensions($call->office_id);
        $oldOffice = isset($oldAttributes['office_id'])
            ? $this->resolveOfficeDimensions($oldAttributes['office_id'])
            : $newOffice;

        // SLA Evaluation
        $isSlaEligible = false;
        $isSlaMet = null;

        // Information / Junk calls are not SLA eligible
        $callType = $call->call_type_id ? DB::table('call_types')->where('id', $call->call_type_id)->first() : null;
        $category = $callType?->category ?? 'general';

        if (!in_array($category, ['junk_silent', 'information'], true) && !empty($call->office_id)) {
            $isSlaEligible = true;
            if ($call->status === 'completed' && !empty($call->resolution_time_sec)) {
                // Priority 1 SLA: 1800s (30m), Priority 2: 3600s (1h), Priority 3+: 7200s (2h)
                $targetSec = match ((int) $call->priority) {
                    1 => 1800,
                    2 => 3600,
                    default => 7200,
                };
                $isSlaMet = ((int) $call->resolution_time_sec) <= $targetSec;
            }
        }

        return AnalyticsOutboxEvent::create([
            'event_uuid'          => (string) Str::uuid(),
            'call_id'             => $call->id,
            'event_type'          => $eventType,
            'stat_date'           => $statDate,
            'stat_hour'           => $statHour,
            'old_zone_id'         => $oldOffice['zone_id'],
            'old_sector_id'       => $oldOffice['sector_id'],
            'old_beat_id'         => $oldOffice['beat_id'],
            'new_zone_id'         => $newOffice['zone_id'],
            'new_sector_id'       => $newOffice['sector_id'],
            'new_beat_id'         => $newOffice['beat_id'],
            'old_status'          => $oldAttributes['status'] ?? null,
            'new_status'          => $call->status,
            'call_type_id'        => (int) ($call->call_type_id ?? 0),
            'call_category'       => $category,
            'priority'            => (int) ($call->priority ?? 3),
            'response_time_sec'   => $call->response_time_sec,
            'resolution_time_sec' => $call->resolution_time_sec,
            'is_sla_met'          => $isSlaMet,
            'is_sla_eligible'     => $isSlaEligible,
            'payload'             => [
                'call_number'   => $call->call_number,
                'caller_number' => $call->caller_number,
            ],
            'status'              => 'pending',
            'attempts'            => 0,
        ]);
    }

    /**
     * Process pending outbox events in micro-batches with atomic UPSERTs.
     */
    public function processOutboxBatch(int $limit = 500): int
    {
        $events = AnalyticsOutboxEvent::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $processedCount = 0;

        foreach ($events as $event) {
            try {
                DB::transaction(function () use ($event) {
                    $this->applyEventToRollup($event);
                    $event->update([
                        'status'       => 'processed',
                        'processed_at' => now(),
                        'attempts'     => $event->attempts + 1,
                    ]);
                });
                $processedCount++;
            } catch (\Throwable $e) {
                $event->increment('attempts');
                $event->update([
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'status'        => $event->attempts >= 5 ? 'failed' : 'pending',
                ]);
                Log::error("Failed to apply outbox event {$event->id}: " . $e->getMessage());
            }
        }

        return $processedCount;
    }

    /**
     * Atomic Delta Upsert into call_hourly_rollups table.
     */
    protected function applyEventToRollup(AnalyticsOutboxEvent $event): void
    {
        $date = Carbon::parse($event->stat_date)->toDateString();
        $hour = $event->stat_hour;
        $zone = $event->new_zone_id;
        $sector = $event->new_sector_id;
        $beat = $event->new_beat_id;
        $typeId = $event->call_type_id;
        $category = $event->call_category ?: 'general';
        $priority = $event->priority;
        $status = $event->new_status;

        $isNewCall = ($event->event_type === 'call_created');
        $isCompleted = ($status === 'completed');
        $pendingStates = ['pending', 'in_progress', 'tiger_reached'];
        $isPending = in_array($status, $pendingStates, true);
        $oldStatus = $event->old_status;
        $statusChanged = $isNewCall || ($oldStatus !== $status);

        // Every call produces several outbox events (created, in_progress, tiger_reached, completed...).
        // Each counter must therefore move only on the TRANSITION it describes, never once per event
        // (per-event counting inflated pending and SLA-eligible counts ~4x from 2026-11-10).
        $enteredCompleted = $isCompleted && ($isNewCall || $oldStatus !== 'completed');

        $respSec = $enteredCompleted ? (int) ($event->response_time_sec ?? 0) : 0;
        $hasResp = $respSec > 0 ? 1 : 0;

        $resSec = (int) ($event->resolution_time_sec ?? 0);
        $hasRes = ($enteredCompleted && $resSec > 0) ? 1 : 0;
        if (!$enteredCompleted) {
            $resSec = 0;
        }

        // SLA-eligible/met follow the backfill definition: counted once, when an eligible call is completed.
        $slaEligible = ($enteredCompleted && $event->is_sla_eligible) ? 1 : 0;
        $slaMet = ($slaEligible && $event->is_sla_met) ? 1 : 0;

        $deltaTotalCalls = $isNewCall ? 1 : 0;
        $deltaCompleted = $enteredCompleted ? 1 : 0;
        $deltaPending = ($isPending && $statusChanged) ? 1 : 0;

        // Leaving a pending-type status (to completed OR to another pending-type status): decrement the old row once.
        if (!$isNewCall && $statusChanged && $oldStatus && in_array($oldStatus, $pendingStates, true)) {
            $this->decrementPendingRollup(
                $date,
                $hour,
                $event->old_zone_id,
                $event->old_sector_id,
                $event->old_beat_id,
                $typeId,
                $priority,
                $oldStatus
            );
        }

        $sql = "
            INSERT INTO call_hourly_rollups (
                stat_date, stat_hour, zone_id, sector_id, beat_id,
                call_type_id, call_category, priority, status,
                total_calls, completed_calls, pending_calls,
                sum_response_seconds, response_count,
                sum_resolution_seconds, resolution_count,
                sla_met_count, sla_eligible_count,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                total_calls = total_calls + VALUES(total_calls),
                completed_calls = completed_calls + VALUES(completed_calls),
                pending_calls = pending_calls + VALUES(pending_calls),
                sum_response_seconds = sum_response_seconds + VALUES(sum_response_seconds),
                response_count = response_count + VALUES(response_count),
                sum_resolution_seconds = sum_resolution_seconds + VALUES(sum_resolution_seconds),
                resolution_count = resolution_count + VALUES(resolution_count),
                sla_met_count = sla_met_count + VALUES(sla_met_count),
                sla_eligible_count = sla_eligible_count + VALUES(sla_eligible_count),
                updated_at = NOW();
        ";

        DB::statement($sql, [
            $date, $hour, $zone, $sector, $beat,
            $typeId, $category, $priority, $status,
            $deltaTotalCalls, $deltaCompleted, $deltaPending,
            $respSec, $hasResp,
            $resSec, $hasRes,
            $slaMet, $slaEligible,
        ]);
    }

    /**
     * Safely decrement pending calls when a call transitions out of pending.
     */
    protected function decrementPendingRollup(
        string $date,
        int $hour,
        int $zone,
        int $sector,
        int $beat,
        int $typeId,
        int $priority,
        string $status
    ): void {
        DB::statement("
            UPDATE call_hourly_rollups
            SET pending_calls = GREATEST(0, CAST(pending_calls AS SIGNED) - 1),
                updated_at = NOW()
            WHERE stat_date = ?
              AND stat_hour = ?
              AND zone_id = ?
              AND sector_id = ?
              AND beat_id = ?
              AND call_type_id = ?
              AND priority = ?
              AND status = ?;
        ", [$date, $hour, $zone, $sector, $beat, $typeId, $priority, $status]);
    }

    /**
     * Backfill call_hourly_rollups from the raw operational calls table.
     */
    public function backfillHistorical(string $startDate, string $endDate): int
    {
        $hierarchyMap = $this->getOfficeHierarchyMap();

        return DB::transaction(function () use ($startDate, $endDate, $hierarchyMap) {
        // Truncate or clean target date range to guarantee fresh, non-duplicated backfill
        // (inside one transaction: dashboards keep reading the old rows until the new ones commit)
        DB::table('call_hourly_rollups')
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->delete();

        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
        $current = $start->copy();
        $insertedCount = 0;

        while ($current <= $end) {
            $chunkStart = $current->toDateString();
            $chunkEnd = $current->copy()->addDays(6)->min($end)->toDateString();

            // Fast raw group by from MySQL for 7-day slice
            $results = DB::table('calls')
                ->leftJoin('call_types', 'calls.call_type_id', '=', 'call_types.id')
                ->selectRaw("
                    DATE(calls.created_at) as stat_date,
                    HOUR(calls.created_at) as stat_hour,
                    COALESCE(calls.office_id, 0) as office_id,
                    COALESCE(calls.call_type_id, 0) as call_type_id,
                    COALESCE(call_types.category, 'general') as call_category,
                    COALESCE(calls.priority, 3) as priority,
                    calls.status as status,
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN calls.status = 'completed' THEN 1 ELSE 0 END) as completed_calls,
                    SUM(CASE WHEN calls.status IN ('pending', 'in_progress', 'tiger_reached') THEN 1 ELSE 0 END) as pending_calls,
                    SUM(COALESCE(calls.response_time_sec, 0)) as sum_response_seconds,
                    SUM(CASE WHEN calls.response_time_sec > 0 THEN 1 ELSE 0 END) as response_count,
                    SUM(CASE WHEN calls.status = 'completed' THEN COALESCE(calls.resolution_time_sec, 0) ELSE 0 END) as sum_resolution_seconds,
                    SUM(CASE WHEN calls.status = 'completed' AND calls.resolution_time_sec > 0 THEN 1 ELSE 0 END) as resolution_count,
                    SUM(CASE
                        WHEN calls.status = 'completed' AND COALESCE(calls.office_id, 0) > 0 AND COALESCE(call_types.category, '') NOT IN ('junk_silent', 'information')
                        THEN (CASE
                            WHEN calls.priority = 1 AND calls.resolution_time_sec <= 1800 THEN 1
                            WHEN calls.priority = 2 AND calls.resolution_time_sec <= 3600 THEN 1
                            WHEN calls.priority >= 3 AND calls.resolution_time_sec <= 7200 THEN 1
                            ELSE 0
                        END)
                        ELSE 0
                    END) as sla_met_count,
                    SUM(CASE
                        WHEN calls.status = 'completed' AND COALESCE(calls.office_id, 0) > 0 AND COALESCE(call_types.category, '') NOT IN ('junk_silent', 'information')
                        THEN 1
                        ELSE 0
                    END) as sla_eligible_count
                ")
                ->whereBetween('calls.created_at', [$chunkStart . ' 00:00:00', $chunkEnd . ' 23:59:59'])
                ->groupBy([
                    DB::raw('DATE(calls.created_at)'),
                    DB::raw('HOUR(calls.created_at)'),
                    'calls.office_id',
                    'calls.call_type_id',
                    'call_types.category',
                    'calls.priority',
                    'calls.status',
                ])
                ->get();

            $rowsToInsert = [];

            foreach ($results as $r) {
                $h = $hierarchyMap[$r->office_id] ?? [
                    'zone_id'   => 0,
                    'sector_id' => 0,
                    'beat_id'   => (int) $r->office_id,
                ];

                $rowsToInsert[] = [
                    'stat_date'              => $r->stat_date,
                    'stat_hour'              => (int) $r->stat_hour,
                    'zone_id'                => (int) $h['zone_id'],
                    'sector_id'              => (int) $h['sector_id'],
                    'beat_id'                => (int) ($h['beat_id'] ?: $r->office_id),
                    'call_type_id'           => (int) $r->call_type_id,
                    'call_category'          => $r->call_category,
                    'priority'               => (int) $r->priority,
                    'status'                 => $r->status,
                    'total_calls'            => (int) $r->total_calls,
                    'completed_calls'        => (int) $r->completed_calls,
                    'pending_calls'          => (int) $r->pending_calls,
                    'sum_response_seconds'   => (int) $r->sum_response_seconds,
                    'response_count'         => (int) $r->response_count,
                    'sum_resolution_seconds' => (int) $r->sum_resolution_seconds,
                    'resolution_count'       => (int) $r->resolution_count,
                    'sla_met_count'          => (int) $r->sla_met_count,
                    'sla_eligible_count'     => (int) $r->sla_eligible_count,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ];

                if (count($rowsToInsert) >= 500) {
                    DB::table('call_hourly_rollups')->insert($rowsToInsert);
                    $insertedCount += count($rowsToInsert);
                    $rowsToInsert = [];
                }
            }

            if (!empty($rowsToInsert)) {
                DB::table('call_hourly_rollups')->insert($rowsToInsert);
                $insertedCount += count($rowsToInsert);
            }

            unset($results);
            $current->addDays(7);
        }

        return $insertedCount;
        });
    }

    /**
     * Reconcile call_hourly_rollups against calls table.
     * Returns true if counts match 100%, false if discrepancies exist.
     */
    public function reconcile(string $startDate, string $endDate): array
    {
        $rawCalls = DB::table('calls')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw("
                COUNT(*) as total_raw,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_raw,
                SUM(CASE WHEN status IN ('pending', 'in_progress', 'tiger_reached') THEN 1 ELSE 0 END) as pending_raw,
                SUM(COALESCE(response_time_sec, 0)) as sum_resp_raw,
                SUM(CASE WHEN status = 'completed' THEN COALESCE(resolution_time_sec, 0) ELSE 0 END) as sum_res_raw
            ")
            ->first();

        $rawSla = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->whereBetween('calls.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('calls.status', 'completed')
            ->whereRaw('COALESCE(calls.office_id, 0) > 0')
            ->whereNotIn('call_types.category', ['junk_silent', 'information'])
            ->selectRaw("
                COUNT(*) as eligible,
                SUM(CASE
                    WHEN calls.priority = 1 AND calls.resolution_time_sec <= 1800 THEN 1
                    WHEN calls.priority = 2 AND calls.resolution_time_sec <= 3600 THEN 1
                    WHEN calls.priority >= 3 AND calls.resolution_time_sec <= 7200 THEN 1
                    ELSE 0 END) as met
            ")
            ->first();

        $rollup = DB::table('call_hourly_rollups')
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->selectRaw("
                SUM(sla_eligible_count) as elig_rollup,
                SUM(sla_met_count) as met_rollup,
                SUM(total_calls) as total_rollup,
                SUM(completed_calls) as completed_rollup,
                SUM(pending_calls) as pending_rollup,
                SUM(sum_response_seconds) as sum_resp_rollup,
                SUM(sum_resolution_seconds) as sum_res_rollup
            ")
            ->first();

        $diffTotal = ((int) $rawCalls->total_raw) - ((int) ($rollup->total_rollup ?? 0));
        $diffCompleted = ((int) $rawCalls->completed_raw) - ((int) ($rollup->completed_rollup ?? 0));
        $diffResponseSec = ((int) $rawCalls->sum_resp_raw) - ((int) ($rollup->sum_resp_rollup ?? 0));
        $diffResolutionSec = ((int) $rawCalls->sum_res_raw) - ((int) ($rollup->sum_res_rollup ?? 0));

        $diffPending  = ((int) $rawCalls->pending_raw) - ((int) ($rollup->pending_rollup ?? 0));
        $diffEligible = ((int) $rawSla->eligible) - ((int) ($rollup->elig_rollup ?? 0));
        $diffMet      = ((int) ($rawSla->met ?? 0)) - ((int) ($rollup->met_rollup ?? 0));

        // pending and SLA counters are checked too: they drifted unnoticed (2026-11-10..12) while totals still matched
        $isMatch = ($diffTotal === 0 && $diffCompleted === 0 && $diffPending === 0 && $diffEligible === 0 && $diffMet === 0);

        return [
            'is_consistent'       => $isMatch,
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'raw_total_calls'     => (int) $rawCalls->total_raw,
            'rollup_total_calls'  => (int) ($rollup->total_rollup ?? 0),
            'diff_total_calls'    => $diffTotal,
            'raw_completed'       => (int) $rawCalls->completed_raw,
            'rollup_completed'    => (int) ($rollup->completed_rollup ?? 0),
            'diff_completed'      => $diffCompleted,
            'raw_sum_resp_sec'    => (int) $rawCalls->sum_resp_raw,
            'rollup_sum_resp_sec' => (int) ($rollup->sum_resp_rollup ?? 0),
            'diff_resp_sec'       => $diffResponseSec,
            'raw_sum_res_sec'     => (int) $rawCalls->sum_res_raw,
            'rollup_sum_res_sec'  => (int) ($rollup->sum_res_rollup ?? 0),
            'diff_res_sec'        => $diffResolutionSec,
            'raw_pending'         => (int) $rawCalls->pending_raw,
            'rollup_pending'      => (int) ($rollup->pending_rollup ?? 0),
            'diff_pending'        => $diffPending,
            'raw_sla_eligible'    => (int) $rawSla->eligible,
            'rollup_sla_eligible' => (int) ($rollup->elig_rollup ?? 0),
            'diff_sla_eligible'   => $diffEligible,
            'diff_sla_met'        => $diffMet,
        ];
    }
}
