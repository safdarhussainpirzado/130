<?php

namespace App\Services;

use App\Events\CallLogged; 
use App\Models\Call;
use App\Models\CallStatusHistory;
use App\Models\User;
use App\Models\CallType;
use App\Models\CallSubType;
use Illuminate\Support\Facades\DB;

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
                $data['completed_remarks'] = 'Automated completion for ' . $type->category . ' call';
                $data['resolution_time_sec'] = 0;
            }

            // Resolve priority from call type/sub type
            if (!isset($data['priority'])) {
                $data['priority'] = $this->resolvePriority($data);
            }

            // Atomic sequential call number allocation via call_sequences table
            // Uses MySQL LAST_INSERT_ID() atomic row-level increment to guarantee
            // uniqueness and eliminate expensive table scans and 1062 duplicate crashes.
            if (!isset($data['call_number'])) {
                $data['call_number'] = $this->generateCallNumber();
            }

            // Safe call creation with bounded duplicate-key retry safeguard
            $maxAttempts = 3;
            $call = null;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $call = Call::create($data);
                    break;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    if ($attempt === $maxAttempts) {
                        throw $e;
                    }
                    // In the rare event of an external insert race, advance sequence and retry
                    $data['call_number'] = $this->generateCallNumber();
                }
            }

            // Record initial status
            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by' => $agent->id,
                'remarks' => $isAutomated ? 'Automated completion for ' . $type->category . ' call' : 'Help inserted',
            ]);

            // Fire events
            event(new CallLogged($call->call_number, $call->caller_number, $call->call_type_id, $call->priority));

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

        if (!in_array($newStatus, $allowed)) {
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
                    $updateData['response_time_sec'] = (int) ($call->call_start_time ?? $call->created_at)->diffInSeconds(now());
                }

                if (isset($data['tiger_id']) && $data['tiger_id'] !== '') {
                    $assetCode = $data['tiger_id'];
                    $updateData['tiger_id'] = $assetCode;
                    $updateData['inprogress_remarks'] = ($updateData['inprogress_remarks'] ? $updateData['inprogress_remarks'] . " " : "") . "[Allocated Asset: {$assetCode}]";
                    $data['remarks'] = ($data['remarks'] ?? '') . " [Asset: {$assetCode}]";
                }
                if (isset($data['patrol_officer_id']) && $data['patrol_officer_id'] !== '') {
                    $officerInfo = $data['patrol_officer_id'];
                    $updateData['patrol_officer_id'] = $officerInfo;
                    $updateData['inprogress_remarks'] = ($updateData['inprogress_remarks'] ? $updateData['inprogress_remarks'] . " " : "") . "[Patrol Officer: {$officerInfo}]";
                    $data['remarks'] = ($data['remarks'] ?? '') . " [Officer: {$officerInfo}]";
                }
            }

            if ($newStatus === 'tiger_reached') {
                $updateData['tiger_reached_at'] = now();
                $updateData['tiger_reached_status_by'] = $changedBy->id;
                $updateData['tiger_reached_remarks'] = $data['remarks'] ?? null;

                // Tiger Response Time = Tiger Reached Time - Help Initiated Time
                if ($call->inprogress_at) {
                    $updateData['tiger_response_time_sec'] = (int) $call->inprogress_at->diffInSeconds(now());
                } elseif ($call->call_start_time ?? $call->created_at) {
                    $updateData['tiger_response_time_sec'] = (int) ($call->call_start_time ?? $call->created_at)->diffInSeconds(now());
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
                if (!$call->tiger_reached_at && $call->inprogress_at) {
                    $updateData['tiger_reached_at'] = now();
                    $updateData['tiger_response_time_sec'] = (int) $call->inprogress_at->diffInSeconds(now());
                    $updateData['response_time_sec'] = $updateData['tiger_response_time_sec'];
                } elseif ($call->tiger_response_time_sec && !$call->response_time_sec) {
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
                'call_id'    => $call->id,
                'old_status' => $call->status,
                'new_status' => $call->status, // Status remains the same
                'changed_by' => $user->id,
                'remarks'    => "Call reminder #{$call->call_reminder_count} sent to field units.",
            ]);

            // Re-fire event to trigger notifications via polling/broadcast
            event(new CallLogged($call->call_number, $call->caller_number, $call->call_type_id, $call->priority));

            return $call->fresh();
        });
    }

    /**
     * Atomically generates a unique, sequential call number (CRM-YYYY-NNNNN)
     * using the dedicated call_sequences table with MySQL LAST_INSERT_ID()
     * row-level atomic locking. Eliminates table-scanning and race conditions.
     */
    public function generateCallNumber(): string
    {
        $year = (int) date('Y');

        // Ensure row exists for the current year
        DB::statement("
            INSERT INTO call_sequences (year, current_seq)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE current_seq = current_seq
        ", [$year]);

        // Atomic increment using LAST_INSERT_ID(current_seq + 1)
        DB::statement("
            UPDATE call_sequences 
            SET current_seq = LAST_INSERT_ID(current_seq + 1) 
            WHERE year = ?
        ", [$year]);

        $res = DB::selectOne("SELECT LAST_INSERT_ID() AS seq");
        $nextSeq = (int) ($res->seq ?? 1);

        $callNumber = sprintf('CRM-%d-%05d', $year, $nextSeq);

        // Fallback safeguard in case of manual insert or sequence disparity
        while (DB::table('calls')->where('call_number', $callNumber)->exists()) {
            DB::statement("
                UPDATE call_sequences 
                SET current_seq = LAST_INSERT_ID(current_seq + 1) 
                WHERE year = ?
            ", [$year]);
            $res = DB::selectOne("SELECT LAST_INSERT_ID() AS seq");
            $nextSeq = (int) ($res->seq ?? 1);
            $callNumber = sprintf('CRM-%d-%05d', $year, $nextSeq);
        }

        return $callNumber;
    }

    private function resolvePriority(array $data): int
    {
        if (isset($data['call_sub_type_id'])) {
            $subType = CallSubType::find($data['call_sub_type_id']);
            if ($subType) return $subType->priority;
        }

        if (isset($data['call_type_id'])) {
            $type = CallType::find($data['call_type_id']);
            if ($type) return $type->priority;
        }

        return 3;
    }
}
