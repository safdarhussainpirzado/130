<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WomensJourneyRegisterRequest;
use App\Http\Requests\Api\V1\WomensJourneyUpdateRequest;
use App\Models\Call;
use App\Models\CallType;
use App\Models\User;
use App\Models\WomensJourney;
use App\Models\WomensJourneyUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WomensJourneyController extends Controller
{
    /**
     * Screen W01-W05: Register PSV Journey for Specialized Women's Safety Monitoring (WJ-204).
     */
    public function register(WomensJourneyRegisterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        // Check if there is already an active journey for this user
        $existing = WomensJourney::where('user_id', $user->id)
            ->whereIn('status', ['active', 'alert_triggered'])
            ->first();

        if ($existing) {
            return response()->json([
                'success'      => true,
                'message'      => "Active Women's Journey already exists ({$existing->journey_code}).",
                'journey_code' => $existing->journey_code,
                'journey_uuid' => $existing->uuid,
                'status'       => $existing->status,
            ]);
        }

        // Pull emergency contacts from commuter profile if not supplied in request
        $emergencyContacts = $validated['emergency_contacts'] ?? $user->commuterProfile?->emergency_contacts ?? [];

        $journey = WomensJourney::create([
            'user_id'            => $user->id,
            'origin_city'        => $validated['origin_city'],
            'origin_stop'        => $validated['origin_stop'],
            'destination_city'   => $validated['destination_city'],
            'destination_stop'   => $validated['destination_stop'],
            'psv_company_name'   => $validated['psv_company_name'],
            'psv_reg_no'         => strtoupper(trim($validated['psv_reg_no'])),
            'psv_seat_no'        => $validated['psv_seat_no'] ?? null,
            'emergency_contacts' => $emergencyContacts,
            'status'             => 'active',
            'last_check_in_at'   => now(),
            'notes'              => $validated['notes'] ?? null,
        ]);

        // Initial check-in event
        WomensJourneyUpdate::create([
            'womens_journey_id' => $journey->id,
            'update_type'       => 'check_in',
            'content'           => "Journey started at {$journey->origin_stop} ({$journey->origin_city}) aboard {$journey->psv_company_name} [{$journey->psv_reg_no}].",
            'location_name'     => $journey->origin_stop,
        ]);

        return response()->json([
            'success'        => true,
            'message'        => "Safe Transit Journey registered. Connected to 130 Control Women's Safety Desk.",
            'journey_code'   => $journey->journey_code,
            'journey_uuid'   => $journey->uuid,
            'psv_carrier'    => $journey->psv_company_name,
            'psv_reg_no'     => $journey->psv_reg_no,
            'seat_no'        => $journey->psv_seat_no,
            'status'         => 'active',
            'started_at'     => $journey->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Screen W07: Retrieve Current Active Transit Journey.
     */
    public function active(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $journey = WomensJourney::with(['updates' => fn ($q) => $q->latest()->limit(10)])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'alert_triggered'])
            ->first();

        if (! $journey) {
            return response()->json([
                'success'            => true,
                'has_active_journey' => false,
                'journey'            => null,
                'message'            => 'No active PSV journey being monitored.',
            ]);
        }

        return response()->json([
            'success'            => true,
            'has_active_journey' => true,
            'journey'            => [
                'uuid'               => $journey->uuid,
                'journey_code'       => $journey->journey_code,
                'status'             => $journey->status,
                'route'              => [
                    'from' => "{$journey->origin_stop}, {$journey->origin_city}",
                    'to'   => "{$journey->destination_stop}, {$journey->destination_city}",
                ],
                'psv'                => [
                    'company'  => $journey->psv_company_name,
                    'reg_no'   => $journey->psv_reg_no,
                    'seat_no'  => $journey->psv_seat_no,
                ],
                'emergency_contacts' => $journey->emergency_contacts,
                'last_check_in_at'   => $journey->last_check_in_at?->toIso8601String(),
                'recent_updates'     => $journey->updates->map(fn ($u) => [
                    'uuid'          => $u->uuid,
                    'type'          => $u->update_type,
                    'content'       => $u->content,
                    'location_name' => $u->location_name,
                    'created_at'    => $u->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Screen W08/W10: Post In-Transit Note or Milestone Check-In.
     */
    public function postUpdate(WomensJourneyUpdateRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $journey = WomensJourney::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();
        $validated = $request->validated();

        $update = DB::transaction(function () use ($journey, $validated) {
            $up = WomensJourneyUpdate::create([
                'womens_journey_id' => $journey->id,
                'update_type'       => $validated['update_type'],
                'content'           => $validated['content'] ?? null,
                'location_name'     => $validated['location_name'] ?? null,
                'latitude'          => $validated['latitude'] ?? null,
                'longitude'         => $validated['longitude'] ?? null,
                'metadata'          => $validated['metadata'] ?? null,
            ]);

            $journey->update([
                'last_check_in_at' => now(),
                'last_latitude'    => $validated['latitude'] ?? $journey->last_latitude,
                'last_longitude'   => $validated['longitude'] ?? $journey->last_longitude,
            ]);

            return $up;
        });

        return response()->json([
            'success'           => true,
            'message'           => 'Transit milestone recorded. Monitored by 130 Female Desk Operator.',
            'update_id'         => $update->uuid,
            'journey_code'      => $journey->journey_code,
            'last_check_in_at'  => $journey->fresh()->last_check_in_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Screen W11: Trigger Discreet Assistance / Silent Alert.
     */
    public function triggerSilentAlert(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $journey = WomensJourney::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        $now = now();

        DB::transaction(function () use ($journey, $user, $now, $request) {
            $journey->update([
                'status'            => 'alert_triggered',
                'discreet_alert_at' => $now,
            ]);

            WomensJourneyUpdate::create([
                'womens_journey_id' => $journey->id,
                'update_type'       => 'silent_alert',
                'content'           => 'DISCREET EMERGENCY ASSISTANCE TRIGGERED BY PASSENGER.',
                'latitude'          => $request->input('latitude', $journey->last_latitude),
                'longitude'         => $request->input('longitude', $journey->last_longitude),
                'metadata'          => ['silent' => true, 'priority' => 1],
            ]);

            // Create Emergency SOS Incident in 130 CRM
            $emergencyType = CallType::firstOrCreate(
                ['name' => "Women's Safety Emergency"],
                ['description' => "PSV Transit Silent Alert"]
            );

            Call::create([
                'call_number'      => 'WJ-SOS-' . random_int(100, 999),
                'agent_id'         => User::where('role_id', 1)->value('id') ?? 1,
                'commuter_user_id' => $user->id,
                'caller_name'      => $user->full_name,
                'caller_number'    => $user->mobile_no ?? '03000000000',
                'caller_gender'    => 'female',
                'call_type_id'     => $emergencyType->id,
                'caller_lat'       => $request->input('latitude', $journey->last_latitude),
                'caller_lng'       => $request->input('longitude', $journey->last_longitude),
                'vehicle_no'       => $journey->psv_reg_no,
                'details'          => "DISCREET PSV SILENT ALERT: Passenger traveling on {$journey->psv_company_name} (Bus {$journey->psv_reg_no}, Seat {$journey->psv_seat_no}) from {$journey->origin_city} to {$journey->destination_city} has requested urgent police intervention.",
                'status'           => 'pending',
                'priority'         => 1,
                'call_start_time'  => $now,
                'call_pickup_time' => $now,
            ]);
        });

        return response()->json([
            'success'        => true,
            'message'        => 'Priority 1 Discreet Alarm raised at 130 Control Room Female Desk. Highway patrol dispatched for tactical intercept.',
            'journey_code'   => $journey->journey_code,
            'status'         => 'alert_triggered',
            'alert_time'     => $now->toIso8601String(),
        ]);
    }

    /**
     * Screen W12/W13: Confirm Safe Arrival & Conclude Transit Monitoring.
     */
    public function confirmSafeArrival(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $journey = WomensJourney::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        $now = now();

        DB::transaction(function () use ($journey, $now, $request) {
            $journey->update([
                'status'       => 'completed',
                'completed_at' => $now,
            ]);

            WomensJourneyUpdate::create([
                'womens_journey_id' => $journey->id,
                'update_type'       => 'safe_arrival',
                'content'           => 'Passenger confirmed safe destination arrival. Location sharing terminated.',
                'location_name'     => $request->input('arrival_stop', $journey->destination_stop),
            ]);
        });

        return response()->json([
            'success'        => true,
            'message'        => "Safe arrival confirmed. Journey {$journey->journey_code} archived safely. Thank you for traveling with NHMP Hamsafar.",
            'journey_code'   => $journey->journey_code,
            'status'         => 'completed',
            'completed_at'   => $now->toIso8601String(),
        ]);
    }

    /**
     * Specialized 130 Control Room Female Desk Active Queue.
     */
    public function deskQueue(Request $request): JsonResponse
    {
        $journeys = WomensJourney::with(['user', 'updates' => fn ($q) => $q->latest()->limit(3)])
            ->whereIn('status', ['active', 'alert_triggered'])
            ->orderByRaw("FIELD(status, 'alert_triggered', 'active')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($j) => [
                'uuid'               => $j->uuid,
                'journey_code'       => $j->journey_code,
                'status'             => $j->status,
                'passenger'          => [
                    'name'   => $j->user?->full_name,
                    'phone'  => $j->user?->mobile_no,
                    'cnic'   => $j->user?->cnic,
                    'gender' => $j->user?->commuterProfile?->gender,
                ],
                'psv'                => [
                    'company' => $j->psv_company_name,
                    'reg_no'  => $j->psv_reg_no,
                    'seat_no' => $j->psv_seat_no,
                ],
                'route'              => [
                    'origin'      => "{$j->origin_stop}, {$j->origin_city}",
                    'destination' => "{$j->destination_stop}, {$j->destination_city}",
                ],
                'last_check_in_at'   => $j->last_check_in_at?->toIso8601String(),
                'discreet_alert_at'  => $j->discreet_alert_at?->toIso8601String(),
                'emergency_contacts' => $j->emergency_contacts,
                'latest_update'      => $j->updates->first()?->content,
            ]);

        return response()->json([
            'success'             => true,
            'active_journeys_count'=> $journeys->count(),
            'journeys'            => $journeys,
        ]);
    }
}
