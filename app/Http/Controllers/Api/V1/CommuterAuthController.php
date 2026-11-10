<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommuterLoginRequest;
use App\Http\Requests\Api\V1\CommuterRegisterRequest;
use App\Http\Requests\Api\V1\CommuterUpdateProfileRequest;
use App\Http\Requests\Api\V1\GoogleAuthRequest;
use App\Models\CommuterProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommuterAuthController extends Controller
{
    /**
     * 1-Tap Google Sign-In / Registration.
     */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $idToken = $request->validated('id_token');
        $fcmToken = $request->validated('device_fcm_token');

        // Verify Google ID Token via Google's official tokeninfo endpoint
        try {
            $googleResponse = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (! $googleResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired Google authentication token.',
                ], 401);
            }

            $googleData = $googleResponse->json();

            // Security checks: issuer, email verification, and sub
            $issuer = $googleData['iss'] ?? '';
            $isVerified = ($googleData['email_verified'] ?? false) === 'true' || ($googleData['email_verified'] ?? false) === true;

            if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) || ! $isVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account email must be verified.',
                ], 401);
            }

            $email = strtolower(trim($googleData['email']));
            $googleId = (string) ($googleData['sub'] ?? '');
            $fullName = trim($googleData['name'] ?? explode('@', $email)[0]);
            $avatarUrl = $googleData['picture'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Hamsafar: Google token verification failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify Google credentials at this time.',
            ], 503);
        }

        $commuterRole = Role::firstOrCreate(
            ['name' => 'commuter'],
            ['display_name' => 'Commuter / Road User', 'status' => 'active', 'scope_level' => 'national']
        );

        $user = DB::transaction(function () use ($email, $googleId, $fullName, $avatarUrl, $fcmToken, $commuterRole) {
            // Find existing user by email
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'full_name'  => $fullName,
                    'email'      => $email,
                    'username'   => 'c_' . Str::slug(explode('@', $email)[0]) . '_' . Str::lower(Str::random(5)),
                    'password'   => Hash::make(Str::random(32)),
                    'role_id'    => $commuterRole->id,
                    'is_active'  => true,
                ]);
            }

            // Ensure profile exists and sync Google details
            $profile = CommuterProfile::firstOrNew(['user_id' => $user->id]);
            $profile->google_id = $googleId;
            if ($avatarUrl) {
                $profile->avatar_url = $avatarUrl;
            }
            if ($fcmToken) {
                $profile->device_fcm_token = $fcmToken;
            }
            $profile->save();

            return $user;
        });

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This account has been administratively disabled.',
            ], 403);
        }

        $token = $user->createToken('commuter_mobile_token', ['role:commuter'])->plainTextToken;
        $profile = $user->commuterProfile;

        return response()->json([
            'success'             => true,
            'token_type'          => 'Bearer',
            'access_token'        => $token,
            'is_profile_complete' => $profile ? $profile->isProfileComplete() : false,
            'user'                => [
                'uuid'       => $user->uuid,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'mobile_no'  => $user->mobile_no,
                'role'       => 'commuter',
                'avatar_url' => $profile?->avatar_url,
            ],
            'profile'             => [
                'gender'             => $profile?->gender,
                'blood_group'        => $profile?->blood_group,
                'is_cnic_verified'   => (bool) $profile?->is_cnic_verified,
                'emergency_contacts' => $profile?->emergency_contacts ?? [],
                'preferred_language' => $profile?->preferred_language ?? 'ur',
            ],
        ]);
    }

    /**
     * Standard Email & Password Registration.
     */
    public function register(CommuterRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $commuterRole = Role::firstOrCreate(
            ['name' => 'commuter'],
            ['display_name' => 'Commuter / Road User', 'status' => 'active', 'scope_level' => 'national']
        );

        $user = DB::transaction(function () use ($validated, $commuterRole) {
            $user = User::create([
                'full_name'  => $validated['full_name'],
                'email'      => strtolower(trim($validated['email'])),
                'username'   => 'c_' . Str::slug(explode('@', $validated['email'])[0]) . '_' . Str::lower(Str::random(5)),
                'password'   => Hash::make($validated['password']),
                'role_id'    => $commuterRole->id,
                'is_active'  => true,
            ]);

            CommuterProfile::create([
                'user_id'          => $user->id,
                'device_fcm_token' => $validated['device_fcm_token'] ?? null,
            ]);

            return $user;
        });

        $token = $user->createToken('commuter_mobile_token', ['role:commuter'])->plainTextToken;

        return response()->json([
            'success'             => true,
            'token_type'          => 'Bearer',
            'access_token'        => $token,
            'is_profile_complete' => false,
            'user'                => [
                'uuid'      => $user->uuid,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'role'      => 'commuter',
            ],
        ], 201);
    }

    /**
     * Standard Email & Password Login.
     */
    public function login(CommuterLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password credentials.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This account has been administratively disabled.',
            ], 403);
        }

        // Ensure user has commuter role
        if (! $user->isCommuter() && $user->role?->name !== 'commuter') {
            return response()->json([
                'success' => false,
                'message' => 'Official police personnel must use the Hamsafar Patrol App.',
            ], 403);
        }

        if (! empty($validated['device_fcm_token'])) {
            $user->commuterProfile()->updateOrCreate(
                ['user_id' => $user->id],
                ['device_fcm_token' => $validated['device_fcm_token']]
            );
        }

        $token = $user->createToken('commuter_mobile_token', ['role:commuter'])->plainTextToken;
        $profile = $user->commuterProfile;

        return response()->json([
            'success'             => true,
            'token_type'          => 'Bearer',
            'access_token'        => $token,
            'is_profile_complete' => $profile ? $profile->isProfileComplete() : false,
            'user'                => [
                'uuid'      => $user->uuid,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'mobile_no' => $user->mobile_no,
                'role'      => 'commuter',
            ],
            'profile'             => [
                'gender'             => $profile?->gender,
                'blood_group'        => $profile?->blood_group,
                'is_cnic_verified'   => (bool) $profile?->is_cnic_verified,
                'emergency_contacts' => $profile?->emergency_contacts ?? [],
                'preferred_language' => $profile?->preferred_language ?? 'ur',
                'vehicle_profiles'   => $profile?->vehicle_profiles ?? [],
            ],
        ]);
    }

    /**
     * Retrieve Commuter Profile.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->commuterProfile;

        return response()->json([
            'success'             => true,
            'is_profile_complete' => $profile ? $profile->isProfileComplete() : false,
            'user'                => [
                'uuid'      => $user->uuid,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'mobile_no' => $user->mobile_no,
                'cnic'      => $user->cnic,
                'role'      => 'commuter',
            ],
            'profile'             => [
                'gender'             => $profile?->gender,
                'blood_group'        => $profile?->blood_group,
                'is_cnic_verified'   => (bool) $profile?->is_cnic_verified,
                'emergency_contacts' => $profile?->emergency_contacts ?? [],
                'preferred_language' => $profile?->preferred_language ?? 'ur',
                'vehicle_profiles'   => $profile?->vehicle_profiles ?? [],
                'saved_routes'       => $profile?->saved_routes ?? [],
            ],
        ]);
    }

    /**
     * Complete or Update Commuter Safety Profile.
     */
    public function updateProfile(CommuterUpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            // Update User core fields
            $userUpdates = [];
            if (! empty($validated['full_name'])) {
                $userUpdates['full_name'] = $validated['full_name'];
            }
            if (! empty($validated['mobile_no'])) {
                $userUpdates['mobile_no'] = $validated['mobile_no'];
            }
            if (! empty($validated['cnic'])) {
                $userUpdates['cnic'] = $validated['cnic'];
            }
            if (! empty($userUpdates)) {
                $user->update($userUpdates);
            }

            // Update Profile fields
            $profile = CommuterProfile::firstOrNew(['user_id' => $user->id]);
            if (! empty($validated['gender'])) {
                $profile->gender = $validated['gender'];
            }
            if (! empty($validated['blood_group'])) {
                $profile->blood_group = $validated['blood_group'];
            }
            if (! empty($validated['preferred_language'])) {
                $profile->preferred_language = $validated['preferred_language'];
            }
            if (isset($validated['emergency_contacts'])) {
                $profile->emergency_contacts = $validated['emergency_contacts'];
            }

            // Merge vehicle profile if provided
            if (! empty($validated['primary_vehicle'])) {
                $existingVehicles = $profile->vehicle_profiles ?? [];
                $existingVehicles[] = $validated['primary_vehicle'];
                $profile->vehicle_profiles = array_values(array_unique($existingVehicles, SORT_REGULAR));
            }

            $profile->save();
        });

        $freshProfile = $user->commuterProfile()->first();

        return response()->json([
            'success'             => true,
            'is_profile_complete' => $freshProfile ? $freshProfile->isProfileComplete() : false,
            'message'             => 'Commuter profile updated successfully.',
            'user'                => [
                'uuid'      => $user->uuid,
                'full_name' => $user->full_name,
                'mobile_no' => $user->mobile_no,
                'cnic'      => $user->cnic,
            ],
            'profile'             => [
                'gender'             => $freshProfile?->gender,
                'blood_group'        => $freshProfile?->blood_group,
                'emergency_contacts' => $freshProfile?->emergency_contacts ?? [],
                'vehicle_profiles'   => $freshProfile?->vehicle_profiles ?? [],
            ],
        ]);
    }
}
