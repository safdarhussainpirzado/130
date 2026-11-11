<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;
use App\Models\Role;

class CallPolicy
{
    /**
     * Check if a user account and role are currently active and eligible.
     */
    private function isUserEligible(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return false;
        }

        if ($user->role && isset($user->role->status) && $user->role->status !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can browse the call listing.
     */
    public function viewAny(User $user): bool
    {
        return $this->isUserEligible($user) && $user->hasPermission('calls.view');
    }

    /**
     * Determine if the user can create a call.
     */
    public function create(User $user): bool
    {
        return $this->isUserEligible($user) && $user->hasPermission('calls.create');
    }

    /**
     * Determine if the user can view the specific call.
     */
    public function view(User $user, Call $call): bool
    {
        if (!$this->isUserEligible($user) || !$user->hasPermission('calls.view')) {
            return false;
        }
        return $this->checkAccess($user, $call, 'read');
    }

    /**
     * Determine if the user can update the call.
     */
    public function update(User $user, Call $call): bool
    {
        if (!$this->isUserEligible($user) || !$user->hasPermission('calls.update')) {
            return false;
        }
        return $this->checkAccess($user, $call, 'write');
    }

    /**
     * Determine if the user can transition/manage the status of the call.
     */
    public function manageStatus(User $user, Call $call): bool
    {
        if (!$this->isUserEligible($user) || !$user->hasPermission('calls.manage_status')) {
            return false;
        }
        return $this->checkAccess($user, $call, 'write');
    }

    /**
     * Determine if the user can dispatch an operational reminder on the call.
     */
    public function reminder(User $user, Call $call): bool
    {
        if (!$this->isUserEligible($user)) {
            return false;
        }

        // 1. National Administrative Roles (Universal access across grid)
        $isNationalAdmin = in_array($user->role?->name, [Role::SUPER_ADMIN, Role::IT_ADMIN, 'operation_admin'], true)
            && ($user->role?->scope_level === 'national' || $user->role?->name === Role::SUPER_ADMIN);

        if ($isNationalAdmin) {
            return true;
        }

        // 2. Call Centre Operations (Helpline Agents and Supervisors)
        // Helpline personnel handling caller callbacks are authorized to dispatch field alerts
        if (in_array($user->role?->name, [Role::AGENT_SUPERVISOR, Role::AGENT], true)) {
            return $user->hasPermission('calls.api_lookup')
                || $user->hasPermission('calls.create')
                || $user->hasPermission('calls.view');
        }

        // 3. Operational Scoped Users (beat_operator, sector_admin, zone_admin)
        if ($user->hasPermission('calls.view') || $user->hasPermission('calls.manage_status') || $user->hasPermission('calls.update')) {
            return $this->checkAccess($user, $call, 'read');
        }

        return false;
    }

    /**
     * Determine if the user can delete the call.
     */
    public function delete(User $user, Call $call): bool
    {
        if (!$this->isUserEligible($user) || !$user->hasPermission('calls.delete')) {
            return false;
        }
        return $this->checkAccess($user, $call, 'delete');
    }

    /**
     * Determine if the user can export calls.
     */
    public function export(User $user): bool
    {
        if (!$this->isUserEligible($user) || !$user->hasPermission('calls.export')) {
            return false;
        }

        // Global national admins (with explicit national scope level) and call centre supervisors
        $isGlobalAdmin = in_array($user->role?->name, [Role::SUPER_ADMIN, Role::IT_ADMIN], true)
            && ($user->role?->scope_level === 'national' || $user->role?->name === Role::SUPER_ADMIN);

        if ($isGlobalAdmin || $user->role?->name === Role::AGENT_SUPERVISOR) {
            return true;
        }

        // Operational users must have at least one active, valid organizational scope
        $scopes = $user->activeScopes;
        if (!$scopes || $scopes->isEmpty()) {
            return false;
        }

        return $scopes->contains(fn($s) => !empty($s->office_id) && (int) $s->office_id > 0);
    }

    /**
     * Internal check for operational scope compatibility.
     * Enforces fail-closed semantics for all missing or invalid scopes.
     */
    private function checkAccess(User $user, Call $call, string $action = 'read'): bool
    {
        // 1. National Administrative Roles (Requires approved national role AND explicit national scope level)
        $isNationalAdmin = in_array($user->role?->name, [Role::SUPER_ADMIN, Role::IT_ADMIN], true)
            && $user->role?->scope_level === 'national';

        if ($isNationalAdmin) {
            return true;
        }

        // 2. Call Centre Operations
        if ($user->role?->name === Role::AGENT_SUPERVISOR) {
            // Supervisors have read-only oversight across call centre helpline records.
            // Implicit nationwide write or delete access is strictly denied.
            if ($action === 'read') {
                return true;
            }
            // For write/delete, supervisor must possess an explicit operational scope with capability
        }

        if ($user->role?->name === Role::AGENT) {
            // Agents can only access calls assigned to them. Unassigned calls are strictly denied.
            return !empty($call->agent_id) && (int) $call->agent_id === (int) $user->id;
        }

        // 3. Operational Scoped Users (zone_admin, sector_admin, beat_operator)
        // Rule: Unassigned calls (office_id is null or <= 0) can NEVER be accessed by operational users.
        if (empty($call->office_id) || (int) $call->office_id <= 0) {
            return false;
        }

        $scopes = $user->activeScopes;
        if (!$scopes || $scopes->isEmpty()) {
            return false; // Fail closed if user has no active scopes
        }

        foreach ($scopes as $scope) {
            // Inactive scope row MUST fail closed
            if (isset($scope->is_active) && !$scope->is_active) {
                continue;
            }

            // Missing or null office_id on an operational scope MUST fail closed
            if (empty($scope->office_id) || (int) $scope->office_id <= 0) {
                continue;
            }

            // Check action capabilities on scope: Missing capability method MUST fail closed (never grant)
            if ($action === 'write') {
                if (!method_exists($scope, 'canWrite') || !$scope->canWrite()) {
                    continue;
                }
            }
            if ($action === 'delete') {
                if (!method_exists($scope, 'canDelete') || !$scope->canDelete()) {
                    continue;
                }
            }

            // Resolve descendant offices via hardened in-memory BFS tree
            $accessibleOfficeIds = \App\Models\Office::getDescendantIdsFor((int) $scope->office_id, true);

            if (empty($accessibleOfficeIds)) {
                continue; // Fail closed if hierarchy cannot be resolved
            }

            if (in_array((int) $call->office_id, $accessibleOfficeIds, true)) {
                return true;
            }
        }

        return false; // Fail closed
    }
}
