<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Support\OrganizationalTransferService;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportRequestPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private OrganizationalTransferService $transferService
    ) {}

    /**
     * Determine whether the user can view the list of administrative support requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('support.view')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin', 'zone_admin', 'sector_admin', 'beat_operator'], true);
    }

    /**
     * Determine whether the user can view a specific support request (Anti-IDOR).
     */
    public function view(User $user, SupportRequest $supportRequest): bool
    {
        // 1. Applicant can always view their own request
        if ($supportRequest->requester_id === $user->id) {
            return true;
        }

        // 2. Super Admin and Operation Manager can view all requests
        if ($user->hasPermission('support.view') || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true)) {
            return true;
        }

        // 3. For transfer requests: Authorized jurisdictional incharge can view
        if ($supportRequest->isTransfer()) {
            if ($supportRequest->source_office_id && $this->transferService->doesUserHaveAuthorityOverOffice($user, (int) $supportRequest->source_office_id)) {
                return true;
            }
            if ($supportRequest->destination_office_id && $this->transferService->doesUserHaveAuthorityOverOffice($user, (int) $supportRequest->destination_office_id)) {
                return true;
            }
            if ($this->transferService->canUserActOnCurrentStep($user, $supportRequest)) {
                return true;
            }
        }

        // 4. For account requests: check requester primary office
        $reqOfficeId = $supportRequest->requester?->activeScopes()->first()?->office_id;
        if ($reqOfficeId && $this->transferService->doesUserHaveAuthorityOverOffice($user, (int) $reqOfficeId)) {
            return true;
        }

        // 5. Assigned approver
        if ($supportRequest->assigned_approver_id && $supportRequest->assigned_approver_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create/submit support requests.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('support.create')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin', 'zone_admin', 'sector_admin', 'beat_operator', 'agent_supervisor', 'agent'], true);
    }

    /**
     * Determine whether the user can apply for an organizational transfer.
     */
    public function applyTransfer(User $user): bool
    {
        return $user->hasPermission('support.transfer_apply')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin', 'zone_admin', 'sector_admin', 'beat_operator', 'agent_supervisor', 'agent'], true);
    }

    /**
     * Determine whether the user can cancel a pending support request.
     */
    public function cancel(User $user, SupportRequest $supportRequest): bool
    {
        if (!$user->hasPermission('support.cancel') && !$user->isPrivileged()) {
            return false;
        }

        return $supportRequest->canBeCancelledBy($user);
    }

    public function approveSource(User $user, SupportRequest $supportRequest): bool
    {
        if ($supportRequest->requester_id === $user->id) {
            return false;
        }

        return $this->transferService->canUserActOnCurrentStep($user, $supportRequest);
    }

    /**
     * Determine whether the user can confirm destination arrival of a transfer.
     */
    public function confirmDestination(User $user, SupportRequest $supportRequest): bool
    {
        if ($supportRequest->requester_id === $user->id) {
            return false;
        }

        return $this->transferService->canUserActOnCurrentStep($user, $supportRequest);
    }

    /**
     * Determine whether the user can request emergency account lock.
     */
    public function lockAccount(User $user): bool
    {
        return $user->hasPermission('support.account_lock') || true; // All authenticated users can lock own account
    }

    /**
     * Determine whether the user can unlock an emergency locked account.
     */
    public function unlockAccount(User $user): bool
    {
        return $user->hasPermission('support.account_unlock')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);
    }

    /**
     * Determine whether the user can authorize password reset and issue recovery tokens.
     */
    public function authorizePasswordReset(User $user): bool
    {
        return $user->hasPermission('support.password_reset_authorize')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);
    }

    /**
     * Determine whether the user can authorize MFA resets and enrollment assistance.
     */
    public function authorizeMfa(User $user): bool
    {
        return $user->hasPermission('support.mfa_assist')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);
    }

    /**
     * Determine whether the user can administratively manage/resolve support requests.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('support.manage')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);
    }

    /**
     * Determine whether the user can view support analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasPermission('support.analytics')
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);
    }
}
