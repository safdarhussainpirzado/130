<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartDutyRequest;
use App\Models\Office;
use App\Models\PatrolDutySession;
use App\Models\PatrolOfficerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatrolDutyController extends Controller
{
    /**
     * Screen 02: Retrieve Duty Setup Context (Assigned Beat, Available Assisting Officers, Recent Callsigns).
     */
    public function getRosterContext(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['designation', 'patrolOfficerProfile.assignedBeat']);
        $profile = $user->patrolOfficerProfile;

        // Assigned Beat
        $beat = $profile?->assignedBeat;
        if (! $beat) {
            $scope = $user->activeScopes()->with('office')->first();
            $beat = $scope?->office;
        }

        // Available assisting officers in the same beat or parent sector
        $assistingOfficers = [];
        if ($beat) {
            $sectorId = $beat->parent_id;

            $candidates = User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->whereIn('name', ['patrol_officer', 'beat_operator']))
                ->where(function ($q) use ($beat, $sectorId) {
                    $q->whereHas('scopes', function ($sq) use ($beat, $sectorId) {
                        $sq->whereIn('office_id', array_filter([$beat->id, $sectorId]));
                    })->orWhereHas('patrolOfficerProfile', function ($pq) use ($beat) {
                        $pq->where('assigned_beat_id', $beat->id);
                    });
                })
                ->with('designation')
                ->limit(20)
                ->get();

            $assistingOfficers = $candidates->map(fn ($u) => [
                'id'          => $u->id,
                'uuid'        => $u->uuid,
                'full_name'   => $u->full_name,
                'username'    => $u->username,
                'designation' => $u->designation?->name ?? 'Patrol Officer',
                'rank_code'   => $u->designation?->short_code ?? 'PO',
                'bps'         => (int) ($u->designation?->bps ?? 14),
            ]);
        }

        // Recent vehicle callsigns used in this beat for fast autocomplete (e.g. P-12, MT-04)
        $recentCallsigns = [];
        if ($beat) {
            $recentCallsigns = PatrolDutySession::where('beat_office_id', $beat->id)
                ->latest('started_at')
                ->limit(10)
                ->pluck('vehicle_callsign')
                ->unique()
                ->values()
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'officer' => [
                'full_name'   => $user->full_name,
                'username'    => $user->username,
                'designation' => $user->designation?->name ?? 'Patrol Officer',
                'rank_code'   => $user->designation?->short_code ?? 'PO',
                'bps'         => (int) ($user->designation?->bps ?? 14),
                'role'        => 'patrol_officer', // Read-only: cannot be altered by officer
            ],
            'assigned_beat' => $beat ? [
                'id'            => $beat->id,
                'name'          => $beat->name,
                'parent_sector' => $beat->parent?->name,
                'zone'          => $beat->parent?->parent?->name,
            ] : null,
            'recent_callsigns'    => $recentCallsigns,
            'assisting_officers'  => $assistingOfficers,
        ]);
    }

    /**
     * Screen 02: Start Duty & Patrol Binding.
     */
    public function startDuty(StartDutyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $callsign = strtoupper(trim($validated['vehicle_callsign']));
        $beatOfficeId = (int) $validated['beat_office_id'];
        $assistingOfficerId = ! empty($validated['assisting_officer_id']) ? (int) $validated['assisting_officer_id'] : null;

        // Verify beat exists
        $beat = Office::find($beatOfficeId);
        if (! $beat) {
            return response()->json([
                'success' => false,
                'message' => 'The selected beat office does not exist.',
            ], 422);
        }

        // Check if officer already has an active duty session
        $profile = PatrolOfficerProfile::firstOrNew(['user_id' => $user->id]);
        if ($profile->is_on_duty && $profile->current_duty_session_id) {
            $existingSession = PatrolDutySession::where('uuid', $profile->current_duty_session_id)
                ->whereNull('ended_at')
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success'         => true,
                    'message'         => 'Officer is already on duty with active session.',
                    'duty_session_id' => $existingSession->uuid,
                    'status'          => $existingSession->status,
                    'vehicle_callsign'=> $existingSession->vehicle_callsign,
                    'started_at'      => $existingSession->started_at?->toIso8601String(),
                ]);
            }
        }

        $session = DB::transaction(function () use ($user, $profile, $callsign, $validated, $beatOfficeId, $assistingOfficerId) {
            $newSession = PatrolDutySession::create([
                'officer_user_id'           => $user->id,
                'assisting_officer_user_id' => $assistingOfficerId,
                'beat_office_id'            => $beatOfficeId,
                'vehicle_callsign'          => $callsign,
                'vehicle_reg_no'            => $validated['vehicle_reg_no'] ?? null,
                'status'                    => PatrolDutySession::STATUS_AVAILABLE,
                'started_at'                => now(),
                'initial_latitude'          => $validated['initial_latitude'] ?? null,
                'initial_longitude'         => $validated['initial_longitude'] ?? null,
                'last_latitude'             => $validated['initial_latitude'] ?? null,
                'last_longitude'            => $validated['initial_longitude'] ?? null,
                'last_telemetry_at'         => now(),
            ]);

            // Update primary officer profile
            $profile->is_on_duty = true;
            $profile->assigned_beat_id = $beatOfficeId;
            $profile->current_duty_session_id = $newSession->uuid;
            $profile->save();

            // If partner is assigned, link partner profile
            if ($assistingOfficerId) {
                PatrolOfficerProfile::updateOrCreate(
                    ['user_id' => $assistingOfficerId],
                    [
                        'is_on_duty'                => true,
                        'assigned_beat_id'          => $beatOfficeId,
                        'current_duty_session_id'   => $newSession->uuid,
                    ]
                );
            }

            return $newSession;
        });

        return response()->json([
            'success'          => true,
            'message'          => "Patrol {$callsign} successfully checked in on duty. Connected to 130 Control.",
            'duty_session_id'  => $session->uuid,
            'status'           => PatrolDutySession::STATUS_AVAILABLE,
            'vehicle_callsign' => $session->vehicle_callsign,
            'beat'             => [
                'id'            => $beat->id,
                'name'          => $beat->name,
                'parent_sector' => $beat->parent?->name,
            ],
            'started_at'       => $session->started_at->toIso8601String(),
        ], 201);
    }

    /**
     * End Officer Duty Session.
     */
    public function endDuty(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;

        if (! $profile || ! $profile->is_on_duty || ! $profile->current_duty_session_id) {
            return response()->json([
                'success' => false,
                'message' => 'No active duty session found for this officer.',
            ], 400);
        }

        $sessionUuid = $profile->current_duty_session_id;

        DB::transaction(function () use ($user, $profile, $sessionUuid) {
            $session = PatrolDutySession::where('uuid', $sessionUuid)->first();
            if ($session) {
                $session->update([
                    'status'   => PatrolDutySession::STATUS_OFF_DUTY,
                    'ended_at' => now(),
                ]);

                // Clear assisting officer if linked
                if ($session->assisting_officer_user_id) {
                    PatrolOfficerProfile::where('user_id', $session->assisting_officer_user_id)
                        ->where('current_duty_session_id', $sessionUuid)
                        ->update([
                            'is_on_duty'              => false,
                            'current_duty_session_id' => null,
                        ]);
                }
            }

            $profile->update([
                'is_on_duty'              => false,
                'current_duty_session_id' => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Duty session successfully concluded. You are now marked OFF DUTY.',
        ]);
    }

    /**
     * Screen 03: High-frequency Patrol Telemetry Stream (Buffered in Redis).
     */
    public function telemetry(
        \App\Http\Requests\Api\V1\PatrolTelemetryRequest $request,
        \App\Services\PatrolTelemetryService $telemetryService
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;

        if (! $profile || ! $profile->is_on_duty || ! $profile->current_duty_session_id) {
            return response()->json([
                'success' => false,
                'message' => 'No active duty session found. Officer must start duty first.',
            ], 403);
        }

        $session = PatrolDutySession::where('uuid', $profile->current_duty_session_id)->first();
        if (! $session || ! $session->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Active duty session not found or already closed.',
            ], 404);
        }

        $result = $telemetryService->recordTelemetry($session, $request->validated());

        return response()->json($result, 202);
    }

    /**
     * Screen 03: Retrieve Current Patrol Readiness & Active Duty Status.
     */
    public function status(
        Request $request,
        \App\Services\PatrolTelemetryService $telemetryService
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;

        if (! $profile || ! $profile->is_on_duty || ! $profile->current_duty_session_id) {
            return response()->json([
                'success'       => true,
                'is_on_duty'    => false,
                'duty_session'  => null,
                'active_case'   => null,
            ]);
        }

        $session = PatrolDutySession::with(['beatOffice', 'assistingOfficer.designation'])
            ->where('uuid', $profile->current_duty_session_id)
            ->first();

        if (! $session) {
            return response()->json([
                'success'       => true,
                'is_on_duty'    => false,
                'duty_session'  => null,
                'active_case'   => null,
            ]);
        }

        $activeCase = $session->activeCase()->with(['callType', 'callSubType', 'carriageway'])->first();
        $livePos = $telemetryService->getLivePatrolPosition($session);

        return response()->json([
            'success'      => true,
            'is_on_duty'   => true,
            'duty_session' => [
                'uuid'              => $session->uuid,
                'vehicle_callsign'  => $session->vehicle_callsign,
                'vehicle_reg_no'    => $session->vehicle_reg_no,
                'status'            => $session->status,
                'started_at'        => $session->started_at?->toIso8601String(),
                'beat'              => [
                    'id'            => $session->beatOffice?->id,
                    'name'          => $session->beatOffice?->name,
                ],
                'assisting_officer' => $session->assistingOfficer ? [
                    'id'          => $session->assistingOfficer->id,
                    'full_name'   => $session->assistingOfficer->full_name,
                    'designation' => $session->assistingOfficer->designation?->name,
                ] : null,
                'live_position'     => $livePos,
            ],
            'active_case'  => $activeCase ? [
                'uuid'           => $activeCase->uuid,
                'case_reference' => $activeCase->call_number,
                'status'         => $activeCase->status,
                'category'       => $activeCase->callType?->name,
                'sub_category'   => $activeCase->callSubType?->name,
                'carriageway'    => $activeCase->carriageway?->name,
                'km_marker'      => $activeCase->km_marker_text,
                'caller_name'    => $activeCase->caller_name,
                'caller_number'  => $activeCase->caller_number,
                'location_details' => $activeCase->location_details,
            ] : null,
        ]);
    }

    /**
     * Screen 03: Toggle Readiness Status (AVAILABLE / BUSY).
     */
    public function updateStatus(
        \App\Http\Requests\Api\V1\PatrolStatusUpdateRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patrolOfficerProfile;

        if (! $profile || ! $profile->is_on_duty || ! $profile->current_duty_session_id) {
            return response()->json([
                'success' => false,
                'message' => 'No active duty session found.',
            ], 403);
        }

        $session = PatrolDutySession::where('uuid', $profile->current_duty_session_id)->first();
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Duty session not found.',
            ], 404);
        }

        $newStatus = $request->validated()['status'];
        $session->update(['status' => $newStatus]);

        return response()->json([
            'success'          => true,
            'message'          => "Patrol readiness updated to {$newStatus}.",
            'status'           => $session->status,
            'vehicle_callsign' => $session->vehicle_callsign,
        ]);
    }
}
