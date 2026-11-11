<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactor\MfaPolicyResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaRolePolicyController extends Controller
{
    public function __construct(
        private MfaPolicyResolver $policyResolver
    ) {}

    /**
     * Display MFA Enforcement by Role administration screen.
     */
    public function index(): View
    {
        $this->authorize('viewRoleMfaPolicies', User::class);

        $roles = Role::select(['id', 'name', 'display_name', 'scope_level'])->orderBy('id')->get();
        $policies = $this->policyResolver->getAllPoliciesMatrix();

        $activeUserCounts = User::where('is_active', true)
            ->groupBy('role_id')
            ->selectRaw('role_id, count(*) as count')
            ->pluck('count', 'role_id');

        $enrolledUserCounts = User::where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('totp_secret')
                  ->orWhereHas('webAuthnCredentials', fn($w) => $w->whereNull('revoked_at'));
            })
            ->groupBy('role_id')
            ->selectRaw('role_id, count(*) as count')
            ->pluck('count', 'role_id');

        $roleData = [];
        foreach ($roles as $role) {
            $policy = $policies[$role->id] ?? null;

            $activeUsersCount = (int) ($activeUserCounts[$role->id] ?? 0);
            $enrolledUsersCount = (int) ($enrolledUserCounts[$role->id] ?? 0);

            $unenrolledUsersCount = max(0, $activeUsersCount - $enrolledUsersCount);
            $isEligible = $this->policyResolver->isRoleEligibleForExemption($role);

            $roleData[] = [
                'role'                   => $role,
                'policy'                 => $policy,
                'is_enforced'            => $policy ? $policy->isEnforcedNow() : true,
                'is_eligible'            => $isEligible,
                'is_protected'           => !$isEligible,
                'active_users_count'     => $activeUsersCount,
                'enrolled_users_count'   => $enrolledUsersCount,
                'unenrolled_users_count' => $unenrolledUsersCount,
                'scheduled_re_enable_at' => $policy?->scheduled_re_enable_at,
                'last_reason'            => $policy?->reason,
                'updated_by'             => $policy?->updatedBy?->username,
                'updated_at'             => $policy?->updated_at,
            ];
        }

        return view('admin.settings.mfa-roles', [
            'roleData'        => $roleData,
            'protectedRoles'  => MfaPolicyResolver::PROTECTED_ROLES,
            'eligibleRoles'   => MfaPolicyResolver::EXEMPTION_ELIGIBLE_ROLES,
        ]);
    }

    /**
     * Toggle or update MFA enforcement for a role.
     */
    public function toggle(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('manageRoleMfaPolicies', User::class);

        $mfaRequired = $request->boolean('mfa_required');

        $request->validate([
            'mfa_required'           => ['required', 'boolean'],
            'reason'                 => [$mfaRequired ? 'nullable' : 'required', 'string', 'max:500'],
            'scheduled_re_enable_at' => ['nullable', 'date', 'after:now'],
        ]);

        $reason = (string) $request->input('reason');
        $scheduledDate = $request->input('scheduled_re_enable_at')
            ? Carbon::parse($request->input('scheduled_re_enable_at'))
            : null;

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->policyResolver->updateRolePolicy(
                $role,
                $mfaRequired,
                $reason,
                $scheduledDate,
                $actor
            );

            $actionText = $mfaRequired ? 'enabled (mandatory)' : 'temporarily disabled (exempt)';
            return back()->with('status', "MFA enforcement for role '{$role->display_name}' has been {$actionText}. Active password-only sessions have been synchronized.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
