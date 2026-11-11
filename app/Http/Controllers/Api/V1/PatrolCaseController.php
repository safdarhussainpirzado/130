<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CaseCannotRespondRequest;
use App\Http\Requests\Api\V1\CaseCompleteAssistanceRequest;
use App\Http\Requests\Api\V1\CaseSupportRequest;
use App\Models\Call;
use App\Models\PatrolDutySession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatrolCaseController extends Controller
{
    /**
     * Screen 04/05: Retrieve Assigned or Active Case for currently authenticated Patrol Officer.
     */
    public function assigned(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;

        if (! $profile || ! $profile->is_on_duty || ! $profile->current_duty_session_id) {
            return response()->json([
                'success' => false,
                'message' => 'Officer must be on active duty to review assigned cases.',
            ], 403);
        }

        $session = PatrolDutySession::where('uuid', $profile->current_duty_session_id)->first();
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Active duty session not found.',
            ], 404);
        }

        // Query for case assigned to this duty session or officer that is not completed or cancelled
        $case = Call::with(['callType', 'callSubType', 'carriageway', 'office'])
            ->where(function ($q) use ($session, $user) {
                $q->where('patrol_duty_session_id', $session->id)
                  ->orWhere('patrol_officer_id', (string) $user->id)
                  ->orWhere('tiger_id', $session->vehicle_callsign);
            })
            ->whereNotIn('status', ['completed', 'cancelled', 'junk'])
            ->latest('updated_at')
            ->first();

        if (! $case) {
            return response()->json([
                'success' => true,
                'has_assigned_case' => false,
                'case' => null,
                'message' => 'No active or pending assignments for this patrol.',
            ]);
        }

        return response()->json([
            'success'           => true,
            'has_assigned_case' => true,
            'case'              => $this->formatCasePayload($case),
        ]);
    }

    /**
     * Screen 05: Detailed Case Review by UUID.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $case = Call::with(['callType', 'callSubType', 'carriageway', 'office', 'messages'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'case'    => $this->formatCasePayload($case),
        ]);
    }

    /**
     * Screen 06: Acknowledge Assignment (Patrol accepts responsibility).
     */
    public function acknowledge(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;
        $session = PatrolDutySession::where('uuid', $profile?->current_duty_session_id)->first();

        $case = Call::where('uuid', $uuid)->firstOrFail();

        if (! in_array($case->status, ['assigned', 'pending'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Case {$case->call_number} cannot be acknowledged from current status ({$case->status}).",
            ], 422);
        }

        DB::transaction(function () use ($case, $user, $session) {
            $case->update([
                'status'                 => 'acknowledged',
                'acknowledged_at'        => now(),
                'acknowledged_by'        => $user->id,
                'patrol_duty_session_id' => $session?->id,
                'patrol_officer_id'      => (string) $user->id,
                'tiger_id'               => $session?->vehicle_callsign ?? $case->tiger_id,
            ]);

            if ($session) {
                $session->update(['status' => PatrolDutySession::STATUS_BUSY]);
            }
        });

        return response()->json([
            'success'        => true,
            'message'        => "Assignment {$case->call_number} acknowledged by Patrol {$session?->vehicle_callsign}.",
            'case_reference' => $case->call_number,
            'status'         => 'acknowledged',
            'acknowledged_at'=> $case->fresh()->acknowledged_at?->toIso8601String(),
        ]);
    }

    /**
     * Screen 06: Cannot Respond (Returns assignment to 130 Control with mandatory reason).
     */
    public function cannotRespond(CaseCannotRespondRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;
        $session = PatrolDutySession::where('uuid', $profile?->current_duty_session_id)->first();

        $case = Call::where('uuid', $uuid)->firstOrFail();
        $reason = $request->validated()['reason'];

        DB::transaction(function () use ($case, $user, $session, $reason) {
            $case->update([
                'status'                 => 'pending',
                'cannot_respond_reason'  => "Rejected by {$user->full_name} ({$session?->vehicle_callsign}): {$reason}",
                'patrol_duty_session_id' => null,
                'patrol_officer_id'      => null,
                'tiger_id'               => null,
            ]);

            if ($session) {
                $session->update(['status' => PatrolDutySession::STATUS_AVAILABLE]);
            }
        });

        return response()->json([
            'success'        => true,
            'message'        => "Assignment returned to 130 Control triage queue.",
            'case_reference' => $case->call_number,
            'status'         => 'pending',
        ]);
    }

    /**
     * Screen 08: Start Response (Officer is moving towards scene; triggers live tracking).
     */
    public function startResponse(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;
        $session = PatrolDutySession::where('uuid', $profile?->current_duty_session_id)->first();

        $case = Call::where('uuid', $uuid)->firstOrFail();

        DB::transaction(function () use ($case, $user, $session) {
            $case->update([
                'status'               => 'in_progress',
                'inprogress_at'        => now(),
                'inprogress_status_by' => $user->id,
            ]);

            if ($session) {
                $session->update(['status' => PatrolDutySession::STATUS_EN_ROUTE]);
            }
        });

        return response()->json([
            'success'        => true,
            'message'        => "Response initiated. Patrol is now en-route to scene.",
            'case_reference' => $case->call_number,
            'status'         => 'in_progress',
            'public_status'  => 'Patrol is on the way. Live tracking available.',
            'started_at'     => $case->fresh()->inprogress_at?->toIso8601String(),
        ]);
    }

    /**
     * Screen 11: Mark Arrived & On Scene (Officer confirms physical arrival).
     */
    public function markArrived(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;
        $session = PatrolDutySession::where('uuid', $profile?->current_duty_session_id)->first();

        $case = Call::where('uuid', $uuid)->firstOrFail();

        DB::transaction(function () use ($case, $user, $session) {
            $now = now();
            $responseTime = $case->inprogress_at ? max(0, (int) $case->inprogress_at->diffInSeconds($now)) : 0;

            $case->update([
                'status'                  => 'tiger_reached',
                'tiger_reached_at'        => $now,
                'tiger_reached_status_by' => $user->id,
                'tiger_response_time_sec' => $responseTime,
            ]);

            if ($session) {
                $session->update(['status' => PatrolDutySession::STATUS_AT_SCENE]);
            }
        });

        return response()->json([
            'success'          => true,
            'message'          => "Arrival confirmed. Assistance is now active at scene.",
            'case_reference'   => $case->call_number,
            'status'           => 'tiger_reached',
            'public_status'    => 'Patrol arrived. Assistance in progress.',
            'arrived_at'       => $case->fresh()->tiger_reached_at?->toIso8601String(),
            'response_time_sec'=> $case->fresh()->tiger_response_time_sec,
        ]);
    }

    /**
     * Screen 12: Request Support / Handover through Control Room.
     */
    public function requestSupport(CaseSupportRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = Call::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();

        // Create internal support request message under this case
        $message = \App\Models\CaseMessage::create([
            'call_id'        => $case->id,
            'sender_user_id' => $user->id,
            'sender_type'    => 'patrol_officer',
            'audience'       => 'internal_control',
            'message_type'   => 'text',
            'content'        => "[SUPPORT REQUEST: {$validated['support_type']}] {$validated['internal_note']}",
            'metadata'       => [
                'support_type'  => $validated['support_type'],
                'requested_by'  => $user->full_name,
                'requested_at'  => now()->toIso8601String(),
            ],
            'delivered_at'   => now(),
        ]);

        return response()->json([
            'success'        => true,
            'message'        => "Support request dispatched to 130 Control Room. Patrol retains active responsibility until explicit handover.",
            'case_reference' => $case->call_number,
            'support_type'   => $validated['support_type'],
            'dispatched_at'  => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Screen 13: Submit Assistance Completion Report.
     */
    public function completeAssistance(CaseCompleteAssistanceRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = Call::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();

        $now = now();
        $assistanceDuration = $case->tiger_reached_at ? max(0, (int) $case->tiger_reached_at->diffInSeconds($now)) : 0;

        $case->update([
            'status'                  => 'review_pending',
            'completion_outcome'      => $validated['outcome'],
            'public_summary'          => $validated['public_summary'],
            'internal_control_note'   => $validated['internal_control_note'] ?? null,
            'assistance_duration_sec' => $assistanceDuration,
        ]);

        return response()->json([
            'success'             => true,
            'message'             => "Completion report received. Placed in 130 Control closure review queue.",
            'case_reference'      => $case->call_number,
            'status'              => 'review_pending',
            'public_status'       => 'Assistance completed. Control review pending.',
            'assistance_duration' => $assistanceDuration,
        ]);
    }

    /**
     * Screen 15/16: Control Room Case Closure & Patrol Release.
     */
    public function closeAndRelease(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = Call::where('uuid', $uuid)->firstOrFail();

        $now = now();
        $resolutionTime = $case->created_at ? max(0, (int) $case->created_at->diffInSeconds($now)) : 0;

        DB::transaction(function () use ($case, $user, $now, $resolutionTime) {
            $case->update([
                'status'              => 'completed',
                'completed_at'        => $now,
                'completed_status_by' => $user->id,
                'resolution_time_sec' => $resolutionTime,
            ]);

            // Release patrol duty session if linked
            if ($case->patrolDutySession) {
                $case->patrolDutySession->update(['status' => PatrolDutySession::STATUS_AVAILABLE]);
            }
        });

        return response()->json([
            'success'             => true,
            'message'             => "Case {$case->call_number} formally closed by 130 Control. Patrol released to AVAILABLE status.",
            'case_reference'      => $case->call_number,
            'status'              => 'completed',
            'public_status'       => 'Case closed and assistance sharing ended.',
            'closed_at'           => $now->toIso8601String(),
            'total_resolution_sec'=> $resolutionTime,
        ]);
    }

    private function formatCasePayload(Call $case): array
    {
        return [
            'uuid'                   => $case->uuid,
            'case_reference'         => $case->call_number,
            'status'                 => $case->status,
            'priority'               => $case->priority ?? 'high',
            'category'               => $case->callType?->name ?? 'Roadside Emergency',
            'sub_category'           => $case->callSubType?->name ?? 'Vehicle Breakdown',
            'caller_name'            => $case->caller_name,
            'caller_number'          => $case->caller_number,
            'location'               => [
                'latitude'           => (float) $case->caller_lat,
                'longitude'          => (float) $case->caller_lng,
                'carriageway'        => $case->carriageway?->name,
                'km_marker'          => $case->km_marker_text,
                'location_details'   => $case->location_details,
                'beat'               => $case->office?->name,
            ],
            'vehicle'                => [
                'type'               => $case->vehicleType?->name,
                'registration_no'    => $case->vehicle_no,
            ],
            'assigned_patrol'        => [
                'vehicle_callsign'   => $case->tiger_id ?? $case->patrolDutySession?->vehicle_callsign,
                'officer_id'         => $case->patrol_officer_id,
            ],
            'timestamps'             => [
                'reported_at'        => $case->created_at?->toIso8601String(),
                'assigned_at'        => $case->assigned_at?->toIso8601String(),
                'acknowledged_at'    => $case->acknowledged_at?->toIso8601String(),
                'response_started_at'=> $case->inprogress_at?->toIso8601String(),
                'arrived_at'         => $case->tiger_reached_at?->toIso8601String(),
                'completed_at'       => $case->completed_at?->toIso8601String(),
            ],
            'public_summary'         => $case->public_summary,
            'internal_control_note'  => $case->internal_control_note,
        ];
    }
}
