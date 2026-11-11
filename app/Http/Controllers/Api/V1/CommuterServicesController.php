<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallType;
use App\Models\PatrolDutySession;
use App\Models\User;
use App\Services\PatrolTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommuterServicesController extends Controller
{
    /**
     * View 08 / View 14: Road User Emergency SOS Trigger.
     */
    public function triggerSos(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'problem_type' => 'nullable|string|max:100',
            'details'      => 'nullable|string|max:1000',
            'carriageway'  => 'nullable|string|max:100',
            'km_marker'    => 'nullable|string|max:50',
            'vehicle_no'   => 'nullable|string|max:50',
        ]);

        $callNumber = 'H-' . random_int(1000, 9999);
        $emergencyType = CallType::firstOrCreate(
            ['name' => 'Emergency Assistance'],
            ['description' => 'Commuter Emergency SOS Alert']
        );

        $case = Call::create([
            'call_number'      => $callNumber,
            'agent_id'         => User::where('role_id', 1)->value('id') ?? 1,
            'commuter_user_id' => $user->id,
            'caller_name'      => $user->full_name,
            'caller_number'    => $user->mobile_no ?? '03000000000',
            'call_type_id'     => $emergencyType->id,
            'caller_lat'       => $validated['latitude'],
            'caller_lng'       => $validated['longitude'],
            'km_marker_text'   => $validated['km_marker'] ?? null,
            'vehicle_no'       => $validated['vehicle_no'] ?? null,
            'details'          => $validated['details'] ?? ($validated['problem_type'] ?? 'Emergency roadside SOS requested via Hamsafar Mobile App.'),
            'status'           => 'pending',
            'priority'         => 1,
            'call_start_time'  => now(),
            'call_pickup_time' => now(),
        ]);

        return response()->json([
            'success'        => true,
            'case_uuid'      => $case->uuid,
            'case_reference' => $case->call_number,
            'status'         => 'pending',
            'public_status'  => '130 has received your request. Control is reviewing and assigning the nearest patrol.',
            'reported_at'    => $case->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * View 07 / View 08: Retrieve Commuter's Active Assistance Case.
     */
    public function activeCase(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $case = Call::with(['callType', 'callSubType', 'carriageway', 'office', 'patrolDutySession'])
            ->where('commuter_user_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'junk'])
            ->latest('created_at')
            ->first();

        if (! $case) {
            return response()->json([
                'success'           => true,
                'has_active_case'   => false,
                'case'              => null,
                'message'           => 'No active emergency or assistance cases in progress.',
            ]);
        }

        return response()->json([
            'success'         => true,
            'has_active_case' => true,
            'case'            => [
                'uuid'             => $case->uuid,
                'case_reference'   => $case->call_number,
                'status'           => $case->status,
                'public_status'    => $this->mapPublicStatusLabel($case->status),
                'priority'         => $case->priority,
                'category'         => $case->callType?->name,
                'sub_category'     => $case->callSubType?->name,
                'reported_at'      => $case->created_at->toIso8601String(),
                'assigned_patrol'  => $case->tiger_id || $case->patrolDutySession ? [
                    'vehicle_callsign' => $case->tiger_id ?? $case->patrolDutySession?->vehicle_callsign,
                    'status'           => $case->patrolDutySession?->status,
                ] : null,
                'public_summary'   => $case->public_summary,
            ],
        ]);
    }

    /**
     * View 08 / View 09: Live Assigned Patrol Tracking (Redis buffer coordinates).
     */
    public function tracking(
        Request $request,
        string $uuid,
        PatrolTelemetryService $telemetryService
    ): JsonResponse {
        $case = Call::with('patrolDutySession')->where('uuid', $uuid)->firstOrFail();

        if (! in_array($case->status, ['in_progress', 'tiger_reached'], true) || ! $case->patrolDutySession) {
            return response()->json([
                'success'          => false,
                'tracking_active'  => false,
                'message'          => 'Live tracking is only active when patrol is confirmed en-route or on scene.',
                'case_status'      => $case->status,
            ], 200);
        }

        $livePos = $telemetryService->getLivePatrolPosition($case->patrolDutySession);

        return response()->json([
            'success'          => true,
            'tracking_active'  => true,
            'case_reference'   => $case->call_number,
            'vehicle_callsign' => $case->patrolDutySession->vehicle_callsign,
            'patrol_position'  => $livePos,
            'incident_position'=> [
                'latitude'  => (float) $case->caller_lat,
                'longitude' => (float) $case->caller_lng,
            ],
            'status'           => $case->status,
            'public_status'    => $this->mapPublicStatusLabel($case->status),
        ]);
    }

    /**
     * View 130 Assistance: Commuter Case History.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cases = Call::with(['callType', 'callSubType'])
            ->where('commuter_user_id', $user->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'uuid'           => $c->uuid,
                'case_reference' => $c->call_number,
                'status'         => $c->status,
                'category'       => $c->callType?->name,
                'sub_category'   => $c->callSubType?->name,
                'reported_at'    => $c->created_at->toIso8601String(),
                'completed_at'   => $c->completed_at?->toIso8601String(),
                'public_summary' => $c->public_summary,
            ]);

        return response()->json([
            'success' => true,
            'cases'   => $cases,
        ]);
    }

    private function mapPublicStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'        => '130 has received your request. Control is reviewing and assigning the nearest patrol.',
            'assigned'       => 'Patrol assigned, awaiting acknowledgement.',
            'acknowledged'   => 'Patrol acknowledged, preparing to respond.',
            'in_progress'    => 'Patrol is on the way. Assigned live tracking available.',
            'tiger_reached'  => 'Patrol arrived. Assistance in progress.',
            'review_pending' => 'Assistance completed. 130 Control review pending.',
            'completed'      => 'Case closed and assistance completed.',
            'cancelled'      => 'Request cancelled.',
            default          => ucfirst($status),
        };
    }
}
