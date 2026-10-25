<?php

namespace App\Services\TwoFactor;

use App\Models\AuditLog;
use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MfaPolicyResolver
{
    private const CACHE_KEY = 'mfa_role_policies_matrix_v1';

    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Immutable Protected Roles that CANNOT be exempted under any circumstance.
     */
    public const PROTECTED_ROLES = [
        'super_admin',
        'operation_admin',
    ];

    /**
     * Server-side Allowlist of Operational Roles eligible for temporary exemption.
     */
    public const EXEMPTION_ELIGIBLE_ROLES = [
        'beat_operator',
        'sector_admin',
        'zone_admin',
        'agent',
        'agent_supervisor',
    ];

    /**
     * Check if MFA is required for a user account.
     * Centralized resolver for Login, Middleware, Enrollment, and Recovery.
     */
    public function isMfaRequiredForUser(User $user): bool
    {
        // 1. Privileged users (Super Admin, Operation Admin) MUST ALWAYS require MFA (WebAuthn)
        if ($user->isPrivileged()) {
            return true;
        }

        // Check global system setting if configured
        try {
            $globalLevel = SystemSetting::get('2fa_level');
            if ($globalLevel === 'all') {
                return true;
            }
            if ($globalLevel === 'off') {
                return false;
            }
        } catch (\Throwable $e) {
            // Ignore if system_settings table does not exist
        }

        // Check individual user override: if explicitly set to false/0 by an admin, user is exempt
        if ($user->mfa_required === false || $user->mfa_required === 0 || $user->mfa_required === '0') {
            return false;
        }

        // If explicitly set to true/1, individual user is enforced regardless of role exemption
        if ($user->mfa_required === true || $user->mfa_required === 1 || $user->mfa_required === '1') {
            // Check if user has enrolled or needs enrollment
            $rolePolicy = $this->getPolicyForRole($user->role_id);
            if ($rolePolicy && !$rolePolicy->isEnforcedNow() && !$user->hasMfaEnrolled()) {
                // If role is exempt and user has not enrolled yet, check if individual was specifically forced
                return true;
            }
            return true;
        }

        // 3. Resolve role policies for primary role and any assigned scopes/roles
        $roleId = (int) $user->role_id;
        if ($roleId <= 0) {
            return (bool) $user->mfa_required;
        }

        $policy = $this->getPolicyForRole($roleId);

        // Fallback to user-level mfa_required setting if role policy record not present
        if (! $policy) {
            return (bool) $user->mfa_required;
        }

        // 4. If policy says required or scheduled re-enablement has passed
        if ($policy->isEnforcedNow()) {
            return true;
        }

        // 5. Multiple roles check: If user has secondary roles (e.g. scopes),
        // any role requiring MFA takes precedence!
        $allRoleIds = $this->getUserAllRoleIds($user);
        foreach ($allRoleIds as $extraRoleId) {
            if ($extraRoleId === $roleId) {
                continue;
            }
            $extraPolicy = $this->getPolicyForRole($extraRoleId);
            if (! $extraPolicy || $extraPolicy->isEnforcedNow()) {
                return true; // Stronger requirement wins
            }
        }

        // 6. Role is currently exempt
        return false;
    }

    /**
     * Check if a specific role is eligible for temporary MFA exemption.
     */
    public function isRoleEligibleForExemption(Role|int $role): bool
    {
        $roleModel = is_int($role) ? Role::find($role) : $role;
        if (! $roleModel) {
            return false;
        }

        // Privileged roles can NEVER be exempted
        if (in_array($roleModel->name, self::PROTECTED_ROLES, true) || in_array((int) $roleModel->id, [1, 2], true)) {
            return false;
        }

        // Must be in server-side allowlist
        return in_array($roleModel->name, self::EXEMPTION_ELIGIBLE_ROLES, true);
    }

    /**
     * Update MFA policy for a specific role.
     * Enforces eligibility allowlist, prevents self-exemption, audits change, and invalidates cache.
     *
     * @throws AuthorizationException
     */
    public function updateRolePolicy(
        Role $role,
        bool $mfaRequired,
        ?string $reason,
        ?Carbon $scheduledReEnableAt,
        User $actor
    ): array {
        // 1. Immutable protection check
        if (! $this->isRoleEligibleForExemption($role)) {
            throw new AuthorizationException("Role '{$role->display_name}' is a protected privileged role and cannot be exempted from multi-factor authentication.");
        }

        // 2. Prevent actor from exempting their own current primary role
        if (! $mfaRequired && (int) $actor->role_id === (int) $role->id) {
            throw new AuthorizationException('Administrators are prohibited from disabling MFA enforcement on their own role.');
        }

        // 3. Reason is required when disabling enforcement
        if (! $mfaRequired && empty(trim((string) $reason))) {
            throw new \InvalidArgumentException('A documented operational reason is required when disabling MFA enforcement.');
        }

        // 4. Update or create policy inside transaction
        $result = DB::transaction(function () use ($role, $mfaRequired, $reason, $scheduledReEnableAt, $actor) {
            /** @var MfaRolePolicy $policy */
            $policy = MfaRolePolicy::lockForUpdate()->firstOrNew(['role_id' => $role->id]);

            $oldValues = [
                'mfa_required' => $policy->mfa_required,
                'scheduled_re_enable_at' => $policy->scheduled_re_enable_at?->toIso8601String(),
                'reason' => $policy->reason,
                'updated_by' => $policy->updated_by,
            ];

            $policy->mfa_required = $mfaRequired;
            $policy->reason = $reason;
            $policy->scheduled_re_enable_at = $mfaRequired ? null : $scheduledReEnableAt;
            $policy->updated_by = $actor->id;
            $policy->save();

            $newValues = [
                'mfa_required' => $policy->mfa_required,
                'scheduled_re_enable_at' => $policy->scheduled_re_enable_at?->toIso8601String(),
                'reason' => $policy->reason,
                'updated_by' => $policy->updated_by,
            ];

            // 5. Audit Log (Never record authentication secrets)
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'mfa_role_policy_updated',
                'table_name' => 'mfa_role_policies',
                'record_id' => $policy->id,
                'old_values' => $oldValues,
                'new_values' => array_merge($newValues, ['role_name' => $role->name, 'role_display' => $role->display_name]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $policy;
        });

        // 6. Invalidate policy cache across instances
        $this->clearPolicyCache();

        return [
            'success' => true,
            'policy' => $result,
        ];
    }

    /**
     * Retrieve cached policy for a given role ID.
     */
    public function getPolicyForRole(?int $roleId): ?MfaRolePolicy
    {
        if (! $roleId || $roleId <= 0) {
            return null;
        }

        $matrix = $this->getAllPoliciesMatrix();

        return $matrix[$roleId] ?? null;
    }

    /**
     * Retrieve matrix of all role policies with short TTL caching.
     *
     * @return array<int, MfaRolePolicy>
     */
    public function getAllPoliciesMatrix(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return MfaRolePolicy::with('role')->get()->keyBy('role_id')->all();
            });
        } catch (\Throwable $e) {
            Log::warning('MFA policy cache retrieval failed; falling back to direct DB: '.$e->getMessage());
            try {
                return MfaRolePolicy::with('role')->get()->keyBy('role_id')->all();
            } catch (\Throwable $e2) {
                Log::error('MFA policy direct DB lookup failed: '.$e2->getMessage());

                return []; // Fail-secure
            }
        }
    }

    /**
     * Clear policy cache across instances.
     */
    public function clearPolicyCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            Log::warning('Failed to forget MFA policy cache: '.$e->getMessage());
        }
    }

    /**
     * Get all role IDs associated with a user (including multi-scopes if any).
     *
     * @return array<int>
     */
    private function getUserAllRoleIds(User $user): array
    {
        $ids = [(int) $user->role_id];

        try {
            if (method_exists($user, 'roles')) {
                $additional = $user->roles()->pluck('roles.id')->all();
                $ids = array_merge($ids, $additional);
            }
        } catch (\Throwable $e) {
        }

        return array_unique(array_filter($ids));
    }
}
