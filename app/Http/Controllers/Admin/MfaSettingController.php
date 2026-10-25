<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactor\MfaPolicyResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaSettingController extends Controller
{
    public function __construct(
        private MfaPolicyResolver $policyResolver
    ) {}

    /**
     * Display the MFA Settings dashboard.
     * Restricted to Super Admin and Operations Manager.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        // 1. Role-based enforcement matrix
        $roles = Role::orderBy('id')->get();
        $policies = $this->policyResolver->getAllPoliciesMatrix();

        $roleData = [];
        foreach ($roles as $role) {
            $policy = $policies[$role->id] ?? null;

            $activeUsersCount = $role->users()->where('is_active', true)->count();
            $enrolledUsersCount = $role->users()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNotNull('totp_secret')
                      ->orWhereHas('webAuthnCredentials', fn($w) => $w->whereNull('revoked_at'));
                })
                ->count();

            $isEligible = $this->policyResolver->isRoleEligibleForExemption($role);

            $roleData[] = [
                'role'                   => $role,
                'policy'                 => $policy,
                'is_enforced'            => $policy ? $policy->isEnforcedNow() : true,
                'is_eligible'            => $isEligible,
                'is_protected'           => !$isEligible,
                'active_users_count'     => $activeUsersCount,
                'enrolled_users_count'   => $enrolledUsersCount,
                'unenrolled_users_count' => max(0, $activeUsersCount - $enrolledUsersCount),
                'scheduled_re_enable_at' => $policy?->scheduled_re_enable_at,
                'last_reason'            => $policy?->reason,
                'updated_by'             => $policy?->updatedBy?->username,
                'updated_at'             => $policy?->updated_at,
            ];
        }

        // 2. Individual User MFA Management
        $search = trim((string) $request->input('search', ''));
        $roleFilter = (int) $request->input('role_id', 0);
        $statusFilter = (string) $request->input('status', 'all');

        $usersQuery = User::with(['role', 'designation', 'webAuthnCredentials', 'activeScopes.office'])
            ->where('is_active', true);

        if ($search !== '') {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('cnic', 'like', "%{$search}%");
            });
        }

        if ($roleFilter > 0) {
            $usersQuery->where('role_id', $roleFilter);
        }

        if ($statusFilter === 'enrolled') {
            $usersQuery->where(function ($q) {
                $q->whereNotNull('totp_secret')
                  ->orWhereHas('webAuthnCredentials', fn($w) => $w->whereNull('revoked_at'));
            });
        } elseif ($statusFilter === 'unenrolled') {
            $usersQuery->whereNull('totp_secret')
                ->whereDoesntHave('webAuthnCredentials', fn($w) => $w->whereNull('revoked_at'));
        } elseif ($statusFilter === 'exempt') {
            $usersQuery->where('mfa_required', 0);
        }

        $users = $usersQuery->orderBy('username')->paginate(15)->withQueryString();

        // 3. Full staff collection for client-side reactive manager (Table & Grid Views)
        $allStaffUsers = User::with(['role', 'designation', 'webAuthnCredentials', 'activeScopes.office'])
            ->where('is_active', true)
            ->get();

        $allStaff = $allStaffUsers->map(function ($u) {
            $hasTotp = !empty($u->totp_secret);
            $hasWebAuthn = $u->webAuthnCredentials->whereNull('revoked_at')->isNotEmpty();
            $isEnrolled = $hasTotp || $hasWebAuthn;
            $isExempt = $u->mfa_required === 0 || $u->mfa_required === false;
            $isPrivileged = $u->isPrivileged();

            $methodLabel = 'Password Only';
            $methodType = 'none';
            if ($hasTotp && $hasWebAuthn) {
                $methodLabel = 'App + Hardware Key';
                $methodType = 'both';
            } elseif ($hasTotp) {
                $methodLabel = 'Authenticator App';
                $methodType = 'totp';
            } elseif ($hasWebAuthn) {
                $methodLabel = 'Hardware Key';
                $methodType = 'webauthn';
            }

            $statusKey = 'unenrolled';
            if ($isExempt) {
                $statusKey = 'exempt';
            } elseif ($isEnrolled) {
                $statusKey = 'enrolled';
            }

            $primaryScope = $u->activeScopes->first();
            $scopeUnitName = $primaryScope?->office?->name ?? 'National';
            $scopeUnitType = $primaryScope?->office?->type ?? 'national';

            return [
                'id'               => $u->id,
                'username'         => $u->username,
                'full_name'        => $u->full_name,
                'email'            => $u->email,
                'cnic'             => $u->cnic ?? 'N/A',
                'mobile_no'        => $u->mobile_no ?? 'N/A',
                'role_id'          => $u->role_id,
                'role_name'        => $u->role?->name ?? 'operative',
                'role_display'     => $u->role?->display_name ?? ($u->role?->name ?? 'Standard Operative'),
                'designation_name' => $u->designation?->name ?? 'Officer',
                'scope_name'       => $scopeUnitName,
                'scope_type'       => $scopeUnitType,
                'is_active'        => (bool) $u->is_active,
                'is_privileged'    => $isPrivileged,
                'has_totp'         => $hasTotp,
                'has_webauthn'     => $hasWebAuthn,
                'is_enrolled'      => $isEnrolled,
                'is_exempt'        => $isExempt,
                'mfa_required'     => (bool) ($u->mfa_required ?? true),
                'status_key'       => $statusKey,
                'method_label'     => $methodLabel,
                'method_type'      => $methodType,
                'enforce_url'      => route('admin.mfa-settings.users.enforce', $u->id),
                'revoke_url'       => route('admin.mfa-settings.users.revoke', $u->id),
                'reset_url'        => route('admin.mfa-settings.users.reset', $u->id),
            ];
        });

        $stats = [
            'total'      => $allStaff->count(),
            'enrolled'   => $allStaff->where('is_enrolled', true)->count(),
            'totp'       => $allStaff->where('has_totp', true)->count(),
            'webauthn'   => $allStaff->where('has_webauthn', true)->count(),
            'exempt'     => $allStaff->where('is_exempt', true)->count(),
            'unenrolled' => $allStaff->where('is_enrolled', false)->where('is_exempt', false)->count(),
        ];

        return view('admin.settings.mfa-settings', [
            'roleData'        => $roleData,
            'protectedRoles'  => MfaPolicyResolver::PROTECTED_ROLES,
            'eligibleRoles'   => MfaPolicyResolver::EXEMPTION_ELIGIBLE_ROLES,
            'users'           => $users,
            'allStaff'        => $allStaff,
            'stats'           => $stats,
            'roles'           => $roles,
            'search'          => $search,
            'roleFilter'      => $roleFilter,
            'statusFilter'    => $statusFilter,
        ]);
    }

    /**
     * Toggle or update MFA requirement for an operational role.
     */
    public function toggleRole(Request $request, Role $role): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeManage();

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

            $actionText = $mfaRequired ? 'enforced' : 'set to exempt';

            if ($request->expectsJson() && ! $request->header('X-PJAX')) {
                return response()->json([
                    'success'     => true,
                    'message'     => "MFA policy for {$role->display_name} has been {$actionText}.",
                    'is_enforced' => $mfaRequired,
                ]);
            }

            return redirect()->back()
                ->with('status', "MFA policy for {$role->display_name} has been {$actionText}.");
        } catch (\Throwable $e) {
            if ($request->expectsJson() && ! $request->header('X-PJAX')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['policy' => $e->getMessage()]);
        }
    }

    /**
     * Turn OFF / Revoke 2FA for a specific user so they can log in with username and password only.
     */
    public function revokeUser(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeManage();

        if ($user->isPrivileged()) {
            if ($request->expectsJson() && ! $request->header('X-PJAX')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Privileged administrators cannot be exempted from multi-factor authentication.',
                ], 422);
            }

            return redirect()->route('admin.mfa-settings.index')->withErrors([
                'user' => 'Privileged administrators cannot be exempted from multi-factor authentication.',
            ]);
        }

        $user->totp_secret = null;
        $user->totp_last_step = null;
        $user->mfa_required = 0; // Explicit individual exemption
        $user->save();

        // Revoke WebAuthn credentials if any
        $user->webAuthnCredentials()->update(['revoked_at' => now()]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'mfa_user_revoked_by_admin',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => [
                'target_user' => $user->username,
                'target_id'   => $user->id,
                'reason'      => 'Admin revoked 2FA; account permitted password-only login.',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $msg = "Two-factor authentication turned off for {$user->username}. User can now sign in with username and password.";

        if ($request->expectsJson() && ! $request->header('X-PJAX')) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->route('admin.mfa-settings.index')->with('status', $msg);
    }

    /**
     * Reset 2FA for an officer (e.g. lost phone). Clears old secret and requires new setup on next login.
     */
    public function resetUser(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeManage();

        $user->totp_secret = null;
        $user->totp_last_step = null;
        $user->mfa_required = 1; // Enforce new setup
        $user->save();

        // Revoke old WebAuthn credentials
        $user->webAuthnCredentials()->update(['revoked_at' => now()]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'mfa_user_reset_by_admin',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => [
                'target_user' => $user->username,
                'target_id'   => $user->id,
                'reason'      => 'Admin reset 2FA device pairing; new QR setup required on next login.',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $msg = "2FA has been reset for {$user->username}. The officer will be prompted to scan a new QR code on their next login.";

        if ($request->expectsJson() && ! $request->header('X-PJAX')) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->route('admin.mfa-settings.index')->with('status', $msg);
    }

    /**
     * Enforce 2FA for an individual user.
     */
    public function enforceUser(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeManage();

        $user->mfa_required = 1;
        $user->save();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'mfa_user_enforced_by_admin',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => [
                'target_user' => $user->username,
                'target_id'   => $user->id,
                'reason'      => 'Admin enforced individual 2FA requirement.',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $msg = "Two-factor authentication is now mandatory for {$user->username}.";

        if ($request->expectsJson() && ! $request->header('X-PJAX')) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->route('admin.mfa-settings.index')->with('status', $msg);
    }

    /**
     * Authorize access: Super Admin and Operations Manager only.
     */
    private function authorizeAccess(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        if ($user->isPrivileged() || $user->hasPermission('security.mfa_roles.view')) {
            return;
        }

        abort(403, 'Access restricted to Operations Manager and Super Admin.');
    }

    /**
     * Authorize management: Super Admin and Operations Manager only.
     */
    private function authorizeManage(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        if ($user->isPrivileged() || $user->hasPermission('security.mfa_roles.manage')) {
            return;
        }

        abort(403, 'Permission denied. Management restricted to Operations Manager and Super Admin.');
    }
}
