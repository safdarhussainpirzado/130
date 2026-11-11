<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PatrolLoginRequest;
use App\Models\PatrolOfficerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PatrolAuthController extends Controller
{
    /**
     * Authenticates official NHMP personnel created administratively in the CRM.
     */
    public function login(PatrolLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $username = trim($validated['username']);

        /** @var User|null $user */
        $user = User::where('username', $username)
            ->with(['role', 'designation', 'patrolOfficerProfile.assignedBeat', 'patrolOfficerProfile.currentDutySession'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            // Track failed attempts if user exists
            if ($user) {
                $user->increment('failed_attempts');
                if ($user->failed_attempts >= 5) {
                    $user->update(['locked_until' => now()->addMinutes(15)]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid official badge number / username or password.',
            ], 401);
        }

        // Check if account is administratively suspended
        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your official account is currently suspended. Please contact 130 Operation Administration.',
            ], 403);
        }

        // Check if security locked
        if ($user->locked_until && $user->locked_until->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is temporarily locked due to excessive failed attempts. Please try again after ' . $user->locked_until->diffForHumans(),
            ], 423);
        }

        // Ensure user is authorized for patrol app (role patrol_officer or beat_operator or has field designation)
        $allowedRoles = ['patrol_officer', 'beat_operator', 'operation_admin', 'super_admin'];
        if (! in_array($user->role?->name, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only authorized NHMP field patrolling personnel may access this application.',
            ], 403);
        }

        // Reset failed attempts & update last login
        $user->update([
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => now(),
            'last_login_ip'   => $request->ip(),
        ]);

        // Sync or create patrol profile
        $profile = PatrolOfficerProfile::firstOrNew(['user_id' => $user->id]);
        if (! empty($validated['device_uuid'])) {
            $profile->device_uuid = $validated['device_uuid'];
        }
        if (! empty($validated['device_fcm_token'])) {
            $profile->device_fcm_token = $validated['device_fcm_token'];
        }

        // If no assigned beat yet, deduce from active user scopes
        if (! $profile->assigned_beat_id) {
            $primaryScope = $user->activeScopes()->with('office')->first();
            if ($primaryScope && $primaryScope->office) {
                $profile->assigned_beat_id = $primaryScope->office->id;
            }
        }
        $profile->save();

        // Issue token with patrol scope
        $token = $user->createToken('patrol_mobile_token', ['role:patrol_officer'])->plainTextToken;

        // Check active duty session
        $activeSession = $profile->currentDutySession;
        $activeDutyData = null;
        if ($activeSession && $activeSession->isActive()) {
            $activeDutyData = [
                'session_uuid'     => $activeSession->uuid,
                'vehicle_callsign' => $activeSession->vehicle_callsign,
                'vehicle_reg_no'   => $activeSession->vehicle_reg_no,
                'status'           => $activeSession->status,
                'started_at'       => $activeSession->started_at?->toIso8601String(),
            ];
        }

        $assignedBeat = $profile->assignedBeat;

        return response()->json([
            'success'      => true,
            'token_type'   => 'Bearer',
            'access_token' => $token,
            'officer'      => [
                'uuid'           => $user->uuid,
                'username'       => $user->username,
                'full_name'      => $user->full_name,
                'badge_number'   => $profile->badge_number ?? $user->username,
                'designation'    => $user->designation?->name ?? 'Patrol Officer',
                'rank_code'      => $user->designation?->short_code ?? 'PO',
                'bps'            => (int) ($user->designation?->bps ?? 14),
                'role'           => $user->role?->name,
                'is_on_duty'     => (bool) ($profile->is_on_duty && $activeDutyData !== null),
                'assigned_beat'  => $assignedBeat ? [
                    'id'             => $assignedBeat->id,
                    'name'           => $assignedBeat->name,
                    'parent_sector'  => $assignedBeat->parent?->name,
                    'zone'           => $assignedBeat->parent?->parent?->name,
                ] : null,
                'active_duty'    => $activeDutyData,
            ],
        ]);
    }

    /**
     * Get details of currently authenticated patrol officer.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['role', 'designation', 'patrolOfficerProfile.assignedBeat', 'patrolOfficerProfile.currentDutySession']);
        $profile = $user->patrolOfficerProfile;

        $activeSession = $profile?->currentDutySession;
        $activeDutyData = null;
        if ($activeSession && $activeSession->isActive()) {
            $activeDutyData = [
                'session_uuid'     => $activeSession->uuid,
                'vehicle_callsign' => $activeSession->vehicle_callsign,
                'vehicle_reg_no'   => $activeSession->vehicle_reg_no,
                'status'           => $activeSession->status,
                'started_at'       => $activeSession->started_at?->toIso8601String(),
            ];
        }

        $assignedBeat = $profile?->assignedBeat;

        return response()->json([
            'success' => true,
            'officer' => [
                'uuid'          => $user->uuid,
                'username'      => $user->username,
                'full_name'     => $user->full_name,
                'badge_number'  => $profile?->badge_number ?? $user->username,
                'designation'   => $user->designation?->name ?? 'Patrol Officer',
                'rank_code'     => $user->designation?->short_code ?? 'PO',
                'bps'           => (int) ($user->designation?->bps ?? 14),
                'is_on_duty'    => (bool) ($profile?->is_on_duty && $activeDutyData !== null),
                'assigned_beat' => $assignedBeat ? [
                    'id'            => $assignedBeat->id,
                    'name'          => $assignedBeat->name,
                    'parent_sector' => $assignedBeat->parent?->name,
                    'zone'          => $assignedBeat->parent?->parent?->name,
                ] : null,
                'active_duty'   => $activeDutyData,
            ],
        ]);
    }
}
