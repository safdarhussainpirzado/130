<?php

namespace App\Services;

use App\Events\CallLogged;
use App\Models\Call;
use App\Models\CallStatusHistory;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\User;
use App\Services\Analytics\CallRollupService;
use App\Services\Notifications\PushDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallService
{
    public const VALID_TRANSITIONS = [
        'pending' => ['in_progress', 'cancelled', 'junk', 'forwarded'],
        'in_progress' => ['tiger_reached', 'completed', 'pending', 'forwarded'],
        'tiger_reached' => ['completed', 'in_progress', 'forwarded', 'cancelled'],
        'forwarded' => ['in_progress', 'tiger_reached', 'completed'],
    ];

    /**
     * Create a new call with advanced priority resolution.
     */
    public function createCall(array $data, User $agent): Call
    {
        return DB::transaction(function () use ($data, $agent) {
            $data['agent_id'] = $agent->id;
            $data['call_start_time'] = now();

            // Determine if this is a Junk/Silent or Information call for automated completion
            $type = null;
            if (isset($data['call_type_id'])) {
                $type = CallType::find($data['call_type_id']);
            }
            $isAutomated = $type && in_array($type->category, ['junk_silent', 'information']);

            $initialStatus = $isAutomated ? 'completed' : 'pending';
            $data['status'] = $initialStatus;

            if ($isAutomated) {
                $data['completed_at'] = now();
                $data['completed_status_by'] = $agent->id;
                $data['completed_remarks'] = 'Automated completion for '.$type->category.' call';
                $data['resolution_time_sec'] = 0;
            }

            // Resolve priority from call type/sub type
            if (! isset($data['priority'])) {
                $data['priority'] = $this->resolvePriority($data);
            }

            // Safe call creation with bounded duplicate-key retry safeguard (max 3 attempts)
            // Eliminates any possibility of infinite loops while handling race conditions at
            // the database constraint boundary.
            $maxAttempts = 3;
            $call = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                if (! isset($data['call_number']) || $attempt > 1) {
                    $data['call_number'] = $this->generateCallNumber();
                }

                try {
                    $call = Call::create($data);
                    break;
                } catch (UniqueConstraintViolationException|QueryException $e) {
                    $isCollision = ($e instanceof UniqueConstraintViolationException) ||
                        (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062);

                    if (! $isCollision) {
                        throw $e;
                    }

                    // Self-healing: Real-time alignment with MAX(call_number) in table
                    $year = (int) date('Y');
                    $maxSeq = (int) DB::table('calls')
                        ->where('call_number', 'like', "CRM-{$year}-%")
                        ->selectRaw("MAX(CAST(SUBSTRING_INDEX(call_number, '-', -1) AS UNSIGNED)) as max_seq")
                        ->value('max_seq');
                    DB::table('call_sequences')->updateOrInsert(['year' => $year], ['current_seq' => $maxSeq]);

                    Log::warning("Call number collision on attempt {$attempt}/{$maxAttempts} for {$data['call_number']}. Resynchronized call_sequences to {$maxSeq}.");
                    if ($attempt === $maxAttempts) {
                        throw $e;
                    }
                }
            }

            // Record initial status
            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by' => $agent->id,
                'remarks' => $isAutomated ? 'Automated completion for '.$type->category.' call' : 'Help inserted',
            ]);

            // Transactional Outbox Event for High-Speed Analytics Rollups
            try {
                app(CallRollupService::class)->recordOutboxEvent($call, 'call_created');
            } catch (\Throwable $e) {
                Log::warning("Outbox recording failed on createCall for {$call->id}: ".$e->getMessage());
            }

            // Fire events
            event(new CallLogged($call->id, $call->call_number, $call->call_type_id, $call->priority));

            // Desk Unified Push Notification for Pending Help (short-circuits if disabled)
            if ($call->status === 'pending') {
                PushDispatcher::dispatchHelpPending($call);
            }

            return $call;
        });
    }

    /**
     * Update call status with metrics calculation.
     */
    public function updateStatus(Call $call, string $newStatus, User $changedBy, array $data = []): Call
    {
        $validTransitions = self::VALID_TRANSITIONS;

        $allowed = $validTransitions[$call->status] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw new \InvalidArgumentException("Invalid state transition: {$call->status} -> {$newStatus}");
        }

        return DB::transaction(function () use ($call, $newStatus, $changedBy, $data) {
            $oldStatus = $call->status;
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'in_progress') {
                $updateData['inprogress_at'] = now();
                $updateData['inprogress_status_by'] = $changedBy->id;
                $updateData['inprogress_remarks'] = $data['remarks'] ?? null;

                if ($call->call_start_time ?? $call->created_at) {
                    $updateData['response_time_sec'] = max(0, (int) ($call->call_start_time ?? $call->created_at)->diffInSeconds(now()));
                }

                if (isset($data['tiger_id']) && $data['tiger_id'] !== '') {
                    $assetCode = $data['tiger_id'];
                    $updateData['tiger_id'] = $assetCode;
                    $updateData['inprogress_remarks'] = ($updateData['inprogress_remarks'] ? $updateData['inprogress_remarks'].' ' : '')."[Allocated Asset: {$assetCode}]";
                    $data['remarks'] = ($data['remarks'] ?? '')." [Asset: {$assetCode}]";
                }
                if (isset($data['patrol_officer_id']) && $data['patrol_officer_id'] !== '') {
                    $officerInfo = $data['patrol_officer_id'];
                    $updateData['patrol_officer_id'] = $officerInfo;
                    $updateData['inprogress_remarks'] = ($updateData['inprogress_remarks'] ? $updateData['inprogress_remarks'].' ' : '')."[Patrol Officer: {$officerInfo}]";
                    $data['remarks'] = ($data['remarks'] ?? '')." [Officer: {$officerInfo}]";
                }
            }

            if ($newStatus === 'tiger_reached') {
                $updateData['tiger_reached_at'] = now();
                $updateData['tiger_reached_status_by'] = $changedBy->id;
                $updateData['tiger_reached_remarks'] = $data['remarks'] ?? null;

                // Tiger Response Time = Tiger Reached Time - Help Initiated Time
                if ($call->inprogress_at) {
                    $updateData['tiger_response_time_sec'] = max(0, (int) $call->inprogress_at->diffInSeconds(now()));
                } elseif ($call->call_start_time ?? $call->created_at) {
                    $updateData['tiger_response_time_sec'] = max(0, (int) ($call->call_start_time ?? $call->created_at)->diffInSeconds(now()));
                }
                $updateData['response_time_sec'] = $updateData['tiger_response_time_sec'] ?? null;

                if (isset($data['tiger_id']) && $data['tiger_id'] !== '') {
                    $updateData['tiger_id'] = $data['tiger_id'];
                }
                if (isset($data['patrol_officer_id']) && $data['patrol_officer_id'] !== '') {
                    $updateData['patrol_officer_id'] = $data['patrol_officer_id'];
                }
            }

            if ($newStatus === 'completed') {
                $updateData['completed_at'] = now();
                $updateData['completed_status_by'] = $changedBy->id;
                $updateData['completed_remarks'] = $data['remarks'] ?? null;
                $updateData['followup_needed'] = $data['followup_needed'] ?? false;

                // If tiger_reached_at was never marked, but directly completed from in_progress
                if (! $call->tiger_reached_at && $call->inprogress_at) {
                    $updateData['tiger_reached_at'] = now();
                    $updateData['tiger_response_time_sec'] = max(0, (int) $call->inprogress_at->diffInSeconds(now()));
                    $updateData['response_time_sec'] = $updateData['tiger_response_time_sec'];
                } elseif ($call->tiger_response_time_sec && ! $call->response_time_sec) {
                    $updateData['response_time_sec'] = $call->tiger_response_time_sec;
                }

                // Assistance Duration = Help Completed Time - Tiger Reached Time
                $reachedTime = $updateData['tiger_reached_at'] ?? $call->tiger_reached_at;
                if ($reachedTime) {
                    $updateData['assistance_duration_sec'] = max(0, (int) $reachedTime->diffInSeconds(now()));
                } elseif ($call->inprogress_at) {
                    $updateData['assistance_duration_sec'] = max(0, (int) $call->inprogress_at->diffInSeconds(now()));
                }

                // Total Help Resolution Time = Help Completed Time - Help Created Time
                $startTime = $call->created_at ?? $call->call_start_time;
                if ($startTime) {
                    $updateData['resolution_time_sec'] = max(0, (int) $startTime->diffInSeconds(now()));
                }
            }

            $call->update($updateData);

            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            // Transactional Outbox Event for Analytics Rollup Delta
            try {
                $freshCall = $call->fresh();
                app(CallRollupService::class)->recordOutboxEvent(
                    $freshCall,
                    'status_changed',
                    ['status' => $oldStatus, 'office_id' => $call->office_id]
                );
            } catch (\Throwable $e) {
                Log::warning("Outbox recording failed on updateStatus for {$call->id}: ".$e->getMessage());
            }

            return $call->fresh();
        });
    }

    public function sendReminder(Call $call, User $user): Call
    {
        return DB::transaction(function () use ($call, $user) {
            $call->increment('call_reminder_count');
            $call->update([
                'last_reminder_at' => now(),
                'is_reminder_call' => true,
            ]);

            // Record this trigger in status history for audit
            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => $call->status,
                'new_status' => $call->status, // Status remains the same
                'changed_by' => $user->id,
                'remarks' => "Call reminder #{$call->call_reminder_count} sent to field units.",
            ]);

            // Re-fire event to trigger notifications via polling/broadcast
            event(new CallLogged($call->id, $call->call_number, $call->call_type_id, $call->priority));

            return $call->fresh();
        });
    }

    /**
     * Atomically generates a unique, sequential call number (CRM-YYYY-NNNNN)
     * using the dedicated call_sequences table with MySQL LAST_INSERT_ID()
     * row-level atomic locking. Eliminates table-scanning and race conditions.
     *
     * In MySQL 8.4:
     * - Fast path: Atomic UPDATE on existing sequence row via primary key in <0.1ms.
     *   Retrieves LAST_INSERT_ID() directly from the write PDO connection to guarantee
     *   connection affinity under read/write splitting topologies.
     * - Initialization path: When the sequence row is absent (year rollover or initial setup),
     *   a pessimistic transaction lock guarantees that exactly one worker initializes the row
     *   from MAX(call_number) or 1, and subsequent workers wait and increment cleanly.
     * - Bounded safety: Bounded retry handled at createCall boundary; no unbounded loops.
     */
    public function generateCallNumber(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $connection = DB::connection();

        // Fast path: Atomic increment on existing sequence row.
        // Executes in <0.1ms using primary key index without scanning calls table.
        $affected = $connection->affectingStatement('
            UPDATE call_sequences 
            SET current_seq = LAST_INSERT_ID(current_seq + 1) 
            WHERE year = ?
        ', [$year]);

        if ($affected > 0) {
            // Guarantee write PDO connection affinity for LAST_INSERT_ID()
            $pdo = $connection->getPdo();
            $nextSeq = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

            if ($nextSeq > 0) {
                return sprintf('CRM-%d-%05d', $year, $nextSeq);
            }
        }

        // Slow path: Lazy initialization executed ONLY on year rollover or when sequence row is absent.
        // Enclosed in a serialized transaction with row locking to ensure deterministic initialization
        // even under concurrent first-time requests from independent workers.
        return $connection->transaction(function () use ($connection, $year) {
            // Pessimistic check: verify if another concurrent worker initialized it while waiting for lock
            $row = $connection->table('call_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $nextSeq = (int) $row->current_seq + 1;
                $connection->table('call_sequences')
                    ->where('year', $year)
                    ->update([
                        'current_seq' => $nextSeq,
                    ]);

                return sprintf('CRM-%d-%05d', $year, $nextSeq);
            }

            // Sequence row does not exist: query max legacy sequence from calls table
            $maxSeq = (int) $connection->table('calls')
                ->where('call_number', 'like', "CRM-{$year}-%")
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(call_number, '-', -1) AS UNSIGNED)), 0) as max_seq")
                ->value('max_seq');

            $nextSeq = $maxSeq + 1;

            $connection->table('call_sequences')->insert([
                'year' => $year,
                'current_seq' => $nextSeq,
            ]);

            return sprintf('CRM-%d-%05d', $year, $nextSeq);
        });
    }

    private function resolvePriority(array $data): int
    {
        if (isset($data['call_sub_type_id'])) {
            $subType = CallSubType::find($data['call_sub_type_id']);
            if ($subType) {
                return $subType->priority;
            }
        }

        if (isset($data['call_type_id'])) {
            $type = CallType::find($data['call_type_id']);
            if ($type) {
                return $type->priority;
            }
        }

        return 3;
    }
}
