<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserListResource;
use App\Models\User;
use App\Models\UserScope;
use App\Models\Role;
use App\Models\Designation;
use App\Models\Office;
use App\Models\PasswordResetRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /* ─────────────────────────────────────────────────────────────────── */
    /*  User CRUD                                                           */
    /* ─────────────────────────────────────────────────────────────────── */

    /**
     * Display a listing of the users.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::select([
                'id', 'uuid', 'username', 'full_name', 'email', 'mobile_no', 'cnic',
                'designation_id', 'role_id', 'is_active', 'is_blocked', 'locked_until', 'failed_attempts',
                'mfa_required', 'mfa_enrolled_at', 'last_login_at'
            ])
            ->with([
                'role:id,name,display_name,scope_level',
                'designation:id,name,bps,short_code',
                'activeScopes:id,user_id,office_id,access_level,label,is_active',
                'activeScopes.office:id,name,type,parent_id',
                'patrolOfficerProfile:id,user_id,badge_number,assigned_beat_id,is_on_duty',
                'commuterProfile:id,user_id,gender,is_cnic_verified',
            ]);

        $this->applyUserFilters($query, $request);

        // Sorting
        $sortBy = in_array($request->input('sort_by'), ['id', 'username', 'full_name', 'last_login_at', 'is_active'])
            ? $request->input('sort_by')
            : 'id';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $paginated = $query->paginate($perPage);

        $users = UserListResource::collection($paginated->items())->resolve();

        $pagination = [
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'from'         => $paginated->firstItem(),
            'to'           => $paginated->lastItem(),
        ];

        $stats = $this->getUserStats();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'items'      => $users,
                'pagination' => $pagination,
                'stats'      => $stats,
            ]);
        }

        $roles            = Role::where('status', 'active')
            ->with(['permissions:id,name,display_name', 'mfaPolicy'])
            ->select(['id', 'name', 'display_name', 'scope_level'])
            ->get();
        $designations     = Designation::where('is_active', true)->orderByDesc('bps')->orderBy('sort_order')->get(['id', 'name', 'bps', 'short_code']);
        $zones            = Office::zones()->where('is_active', true)->get(['id', 'name']);
        $sectors          = Office::sectors()->where('is_active', true)->get(['id', 'name', 'parent_id as zone_id']);
        $beats            = Office::beats()->where('is_active', true)->get(['id', 'name', 'parent_id as sector_id']);

        return view('operations.users.index', compact('users', 'pagination', 'roles', 'designations', 'zones', 'sectors', 'beats', 'stats'));
    }

    /**
     * Apply search, role, status and hierarchy filters to user query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return void
     */
    private function applyUserFilters($query, Request $request): void
    {
        // Search filter (searches across username, full_name, cnic, email, mobile)
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('cnic', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_no', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($roleId = $request->input('role_id')) {
            $query->where('role_id', $roleId);
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true)
                      ->where('is_blocked', false)
                      ->where(function ($lq) {
                          $lq->whereNull('locked_until')
                             ->orWhere('locked_until', '<=', now());
                      });
            } elseif ($status === 'blocked') {
                $query->where('is_blocked', true);
            } elseif ($status === 'suspended') {
                $query->where('is_active', false)
                      ->where('is_blocked', false)
                      ->where(function ($lq) {
                          $lq->whereNull('locked_until')
                             ->orWhere('locked_until', '<=', now());
                      });
            } elseif ($status === 'security_locked' || $status === 'locked') {
                $query->whereNotNull('locked_until')
                      ->where('locked_until', '>', now());
            } elseif ($status === 'inactive') {
                $query->where(function ($iq) {
                    $iq->where('is_active', false)
                       ->orWhere('is_blocked', true)
                       ->orWhere(function ($lq) {
                           $lq->whereNotNull('locked_until')
                              ->where('locked_until', '>', now());
                       });
                });
            } elseif ($status === 'admin') {
                $query->whereHas('role', function ($rq) {
                    $rq->whereIn('name', ['super_admin', 'operation_admin']);
                });
            } elseif ($status === 'ops') {
                $query->whereHas('activeScopes.office', function ($oq) {
                    $oq->whereIn('type', ['sector', 'beat']);
                });
            } elseif ($status === 'call_centre_user' || $status === 'call_center') {
                $query->where(function ($cq) {
                    $cq->whereHas('role', fn($rq) => $rq->whereIn('name', ['agent', 'agent_supervisor']))
                       ->orWhereHas('activeScopes.office', fn($oq) => $oq->whereIn('type', ['call_centre', 'call_center']));
                });
            } elseif ($status === 'zone_user' || $status === 'zone') {
                $query->where(function ($zq) {
                    $zq->whereHas('role', fn($rq) => $rq->where('name', 'zone_admin'))
                       ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'zone'));
                });
            } elseif ($status === 'sector_user' || $status === 'sector') {
                $query->where(function ($sq) {
                    $sq->whereHas('role', fn($rq) => $rq->where('name', 'sector_admin'))
                       ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'sector'));
                });
            } elseif ($status === 'beat_user' || $status === 'beat') {
                $query->where(function ($bq) {
                    $bq->whereHas('role', fn($rq) => $rq->where('name', 'beat_operator'))
                       ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'beat'));
                });
            } elseif ($status === 'patrol_user' || $status === 'patrol' || $status === 'patrol_officer') {
                $query->where(function ($pq) {
                    $pq->whereHas('role', fn($rq) => $rq->where('name', 'patrol_officer'))
                       ->orWhereHas('patrolOfficerProfile');
                });
            } elseif ($status === 'commuter_user' || $status === 'commuter') {
                $query->where(function ($cq) {
                    $cq->whereHas('role', fn($rq) => $rq->where('name', 'commuter'))
                       ->orWhereHas('commuterProfile');
                });
            }
        }

        // Hierarchy filters (beat, sector, zone)
        if ($beatId = $request->input('beat_id')) {
            $query->whereHas('activeScopes', fn($sq) => $sq->where('office_id', $beatId));
        } elseif ($sectorId = $request->input('sector_id')) {
            $query->whereHas('activeScopes.office', fn($oq) => $oq->where('id', $sectorId)->orWhere('parent_id', $sectorId));
        } elseif ($zoneId = $request->input('zone_id')) {
            $query->whereHas('activeScopes.office', function ($oq) use ($zoneId) {
                $oq->where('id', $zoneId)
                   ->orWhere('parent_id', $zoneId)
                   ->orWhereHas('parent', fn($pq) => $pq->where('parent_id', $zoneId));
            });
        }
    }

    /**
     * Compute summary statistics for user management status cards.
     *
     * @return array<string, int>
     */
    private function getUserStats(): array
    {
        return [
            'admins'      => User::whereHas('role', fn($rq) => $rq->whereIn('name', ['super_admin', 'operation_admin']))->count(),
            'zone'        => User::where(function ($zq) {
                $zq->whereHas('role', fn($rq) => $rq->where('name', 'zone_admin'))
                   ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'zone'));
            })->count(),
            'sector'      => User::where(function ($sq) {
                $sq->whereHas('role', fn($rq) => $rq->where('name', 'sector_admin'))
                   ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'sector'));
            })->count(),
            'beat'        => User::where(function ($bq) {
                $bq->whereHas('role', fn($rq) => $rq->where('name', 'beat_operator'))
                   ->orWhereHas('activeScopes.office', fn($oq) => $oq->where('type', 'beat'));
            })->count(),
            'call_center' => User::where(function ($cq) {
                $cq->whereHas('role', fn($rq) => $rq->whereIn('name', ['agent', 'agent_supervisor']))
                   ->orWhereHas('activeScopes.office', fn($oq) => $oq->whereIn('type', ['call_centre', 'call_center']));
            })->count(),
            'patrol'      => User::where(function ($pq) {
                $pq->whereHas('role', fn($rq) => $rq->where('name', 'patrol_officer'))
                   ->orWhereHas('patrolOfficerProfile');
            })->count(),
            'commuter'    => User::where(function ($cq) {
                $cq->whereHas('role', fn($rq) => $rq->where('name', 'commuter'))
                   ->orWhereHas('commuterProfile');
            })->count(),
            'inactive'    => User::where('is_active', false)->orWhere('is_blocked', true)->count(),
            'total'       => User::count(),
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $this->enforceOperationAdminRestrictions($request);

        $validated = $request->validate([
            'username'       => ['required', 'string', 'max:80',  'unique:users'],
            'full_name'      => ['required', 'string', 'max:150'],
            'cnic'           => ['required', 'string', 'regex:/^[0-9]{5}-[0-9]{7}-[0-9]{1}$/', new \App\Rules\UniqueBlindIndex('cnic')],
            'email'          => ['required', 'string', 'email',   'max:100', 'unique:users'],
            'mobile_no'      => ['required', 'string', 'regex:/^03[0-9]{9}$/', new \App\Rules\UniqueBlindIndex('phone')],
            'designation_id' => ['required', 'exists:designations,id'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id'        => ['required', 'exists:roles,id'],
            'is_blocked'     => ['nullable', 'boolean'],
        ]);

        $isBlocked = $request->boolean('is_blocked');
        unset($validated['is_blocked']);
        $validated['password_changed_at'] = now();

        $user = User::create($validated);

        if ($isBlocked) {
            $user->blockAccount('Administrative Block during user provisioning');
        }

        // Attach initial scopes if provided
        if ($request->has('scopes')) {
            $this->syncScopes($user, $request->input('scopes', []));
        }

        if ($request->wantsJson()) {
            $user->load(['role:id,name,display_name,scope_level', 'designation:id,name,bps,short_code', 'activeScopes:id,user_id,office_id,access_level,label,is_active', 'activeScopes.office:id,name,type,parent_id']);
            return response()->json(['success' => true, 'user' => (new UserListResource($user))->resolve()]);
        }

        return redirect()->route('operations.users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $this->enforceOperationAdminRestrictions($request);

        $validated = $request->validate([
            'username'       => ['required', 'string', 'max:80',  'unique:users,username,' . $user->id],
            'full_name'      => ['required', 'string', 'max:150'],
            'cnic'           => ['required', 'string', 'regex:/^[0-9]{5}-[0-9]{7}-[0-9]{1}$/', new \App\Rules\UniqueBlindIndex('cnic', $user->id)],
            'email'          => ['required', 'string', 'email',   'max:100', 'unique:users,email,' . $user->id],
            'mobile_no'      => ['required', 'string', 'regex:/^03[0-9]{9}$/', new \App\Rules\UniqueBlindIndex('phone', $user->id)],
            'designation_id' => ['required', 'exists:designations,id'],
            'password'       => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role_id'        => ['required', 'exists:roles,id'],
            'is_blocked'     => ['nullable', 'boolean'],
        ]);

        // Security Barrier: Prevent blocking own account
        if ($request->has('is_blocked') && $request->boolean('is_blocked') && $user->id === Auth::id()) {
            abort(403, 'You cannot block your own account.');
        }

        $newBlockedState = $request->boolean('is_blocked');
        $currentlyBlocked = (bool) $user->is_blocked;

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        unset($validated['is_blocked']);

        $user->update($validated);

        if ($request->has('is_blocked')) {
            if ($newBlockedState && !$currentlyBlocked) {
                $user->blockAccount('Administrative Block via User Management');
            } elseif (!$newBlockedState && $currentlyBlocked) {
                $newPassword = !empty($validated['password']) ? $validated['password'] : Str::random(16);
                $user->unblockAccount($newPassword);
            }
        }

        // Sync scopes if provided in request
        if ($request->has('scopes')) {
            $this->syncScopes($user, $request->input('scopes', []));
        }

        if ($request->wantsJson()) {
            $user->load(['role:id,name,display_name,scope_level', 'designation:id,name,bps,short_code', 'activeScopes:id,user_id,office_id,access_level,label,is_active', 'activeScopes.office:id,name,type,parent_id']);
            return response()->json(['success' => true, 'user' => (new UserListResource($user))->resolve()]);
        }

        return back()->with('success', 'User configuration updated.');
    }

    public function toggleStatus(User $user)
    {
        $this->authorize('manageStatus', $user);

        // Prevent disabling own account
        abort_if($user->id === Auth::id(), 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => !$user->is_active]);

        $isSecurityLocked = (bool) ($user->locked_until && $user->locked_until->isFuture());
        $statusState = $isSecurityLocked ? 'security_locked' : ($user->is_active ? 'active' : 'suspended');
        $statusLabel = $isSecurityLocked ? 'Security Locked' : ($user->is_active ? 'Active' : 'Suspended');

        return response()->json([
            'success'            => true,
            'status'             => $statusState,
            'status_state'       => $statusState,
            'status_label'       => $statusLabel,
            'is_active'          => (bool)$user->is_active,
            'is_security_locked' => $isSecurityLocked,
            'locked_until'       => $user->locked_until?->toISOString(),
            'message'            => "User {$user->username} status updated to " . ($user->is_active ? 'active' : 'suspended'),
        ]);
    }

    /**
     * Clear security lockout and restore account to active state.
     * Resets locked_until = null, clears failed_attempts = 0, sets is_active = true,
     * and logs an administrative audit event.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function clearLockout(Request $request, User $user)
    {
        $this->authorize('manageStatus', $user);

        // Security Barrier: Prevent modifying security locks on own account
        abort_if($user->id === Auth::id(), 403, 'You cannot modify security locks on your own account.');

        $currentUser = Auth::user();

        $user->forceFill([
            'locked_until'    => null,
            'failed_attempts' => 0,
            'is_active'       => true,
            'is_blocked'      => false,
        ])->save();

        // Flush per-username rate limiter
        try {
            RateLimiter::clear('login_u|' . Str::transliterate(strtolower($user->username)));
        } catch (\Throwable $e) {}

        // Administrative Audit Log
        try {
            AuditLog::create([
                'user_id'    => $currentUser?->id ?? $user->id,
                'action'     => 'ADMIN_SECURITY_LOCKOUT_CLEARED',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => [
                    'action'          => 'Administrative Security Lockout Cleared and Restored by ' . ($currentUser?->username ?? 'Administrator'),
                    'target_user'     => $user->username,
                    'is_active'       => true,
                    'is_blocked'      => false,
                    'locked_until'    => null,
                    'failed_attempts' => 0,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AuditLog creation failed in clearLockout: ' . $e->getMessage());
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'            => true,
                'is_active'          => true,
                'locked_until'       => null,
                'is_security_locked' => false,
                'status_state'       => 'active',
                'status_label'       => 'Active',
                'message'            => "Security lockout cleared. User [{$user->username}] has been restored to Active status.",
            ]);
        }

        return redirect()->back()->with('status', "Security lockout cleared. User [{$user->username}] restored to Active.");
    }

    /**
     * Flush rate limiting throttles for the user account and associated IPs.
     * Clears RateLimiter keys for username and recent failed login IPs from audit trail.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function flushRateLimits(Request $request, User $user)
    {
        $this->authorize('manageStatus', $user);

        // Security Barrier: Prevent modifying own account security counters
        abort_if($user->id === Auth::id(), 403, 'You cannot modify security rate limits on your own account.');

        $currentUser = Auth::user();

        // 1. Clear per-username login throttle
        $userKey = 'login_u|' . Str::transliterate(strtolower($user->username));
        RateLimiter::clear($userKey);

        // 2. Clear IP rate limits from recent audit logs (last 6 hours)
        $clearedIps = [];
        try {
            $recentIps = AuditLog::where('user_id', $user->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('table_name', 'users')->where('record_id', $user->id);
                })
                ->whereNotNull('ip_address')
                ->where('created_at', '>=', now()->subHours(6))
                ->pluck('ip_address')
                ->unique();

            foreach ($recentIps as $ip) {
                if (!empty($ip)) {
                    RateLimiter::clear('login_ip|' . $ip);
                    RateLimiter::clear(sha1($ip));
                    $clearedIps[] = $ip;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed resolving IPs in flushRateLimits: ' . $e->getMessage());
        }

        // 3. Administrative Audit Log
        try {
            AuditLog::create([
                'user_id'    => $currentUser?->id ?? $user->id,
                'action'     => 'ADMIN_RATE_LIMITS_FLUSHED',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => [
                    'action'       => 'Rate limits and throttle cache flushed by ' . ($currentUser?->username ?? 'Administrator'),
                    'target_user'  => $user->username,
                    'user_key'     => $userKey,
                    'cleared_ips'  => $clearedIps,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AuditLog creation failed in flushRateLimits: ' . $e->getMessage());
        }

        $msg = "Rate limits and connection throttles flushed for user [{$user->username}] (" . count($clearedIps) . " IP addresses reset).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'     => true,
                'message'     => $msg,
                'cleared_ips' => $clearedIps,
            ]);
        }

        return redirect()->back()->with('status', $msg);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('managePassword', $user);

        // Security Barrier: Super Admin can NEVER be reset via web application.
        // Internal Note: Root recovery is strictly restricted to server console / SSH infrastructure.
        if ($user->role?->name === Role::SUPER_ADMIN) {
            abort(403, 'Super Administrator credentials cannot be reset via the web application. Please contact system support.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Store old password in history
        try {
            \App\Models\PasswordHistory::create([
                'user_id'  => $user->id,
                'password' => $user->password,
            ]);
        } catch (\Throwable $e) {}

        $user->forceFill([
            'password'            => Hash::make($validated['password']),
            'password_changed_at' => now(),
            'locked_until'        => null,
            'remember_token'      => Str::random(60),
        ]);

        if (array_key_exists('failed_attempts', $user->getAttributes())) {
            $user->setAttribute('failed_attempts', 0);
        }

        $user->save();

        // Invalidate active sessions
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {}

        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        // Fulfill any pending PasswordResetRequests for this user
        PasswordResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'completed',
                'actioned_by' => Auth::id(),
                'actioned_at' => now(),
                'admin_notes' => 'Password reset and authorized by Operation Manager ' . ($currentUser?->username ?? 'admin'),
            ]);

        // Audit Log
        try {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'OP_ADMIN_PASSWORD_RESET',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => ['action' => 'Password reset authorized by Operation Manager ' . ($currentUser?->username ?? 'admin')],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Password for [{$user->full_name}] has been successfully reset and authorized.",
        ]);
    }

    public function getPasswordRequests()
    {
        $this->authorize('viewAny', User::class);

        $requests = PasswordResetRequest::with([
                'user:id,username,full_name,role_id',
                'user.role:id,name,display_name',
                'actionedByUser:id,username,full_name'
            ])
            ->select(['id', 'user_id', 'status', 'actioned_by', 'actioned_at', 'admin_notes', 'created_at'])
            ->orderByRaw("FIELD(status, 'pending', 'completed', 'rejected')")
            ->orderByDesc('created_at')
            ->take(50)
            ->get();

        return response()->json([
            'success'       => true,
            'requests'      => $requests,
            'pending_count' => $requests->where('status', 'pending')->count(),
        ]);
    }

    public function rejectPasswordRequest(Request $request, PasswordResetRequest $resetRequest)
    {
        $this->authorize('viewAny', User::class);

        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        $resetRequest->update([
            'status'      => 'rejected',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
            'admin_notes' => $request->input('notes', 'Request rejected by Operation Manager ' . ($currentUser?->username ?? 'admin')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset request marked as rejected.',
        ]);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Prevent self-deletion
        abort_if($user->id === Auth::id(), 403, 'You cannot delete your own account.');

        DB::transaction(function () use ($user) {
            // Delete user-scoped and authentication-related child records
            $user->scopes()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->delete();
            DB::table('password_recovery_tokens')->where('user_id', $user->id)->delete();
            DB::table('password_histories')->where('user_id', $user->id)->delete();
            DB::table('login_logs')->where('user_id', $user->id)->delete();

            // Check if user has permanent operational audit records (calls, assignments, etc.)
            $hasCallHistory = DB::table('calls')->where('agent_id', $user->id)->orWhere('forwarded_to_user_id', $user->id)->exists();
            $hasSupportHistory = DB::table('support_requests')->where('requester_id', $user->id)->orWhere('assigned_approver_id', $user->id)->exists();

            if ($hasCallHistory || $hasSupportHistory) {
                // Enterprise archival: scramble credentials, purge 2FA, and permanently block
                $user->blockAccount('Administrative Account Deletion & Archival');
            } else {
                $user->delete();
            }
        });

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'User account removed from active registry.']);
        }

        return redirect()->back()->with('success', 'User removed from active registry.');
    }

    /* ─────────────────────────────────────────────────────────────────── */
    /*  Scope Management — AJAX endpoints                                   */
    /* ─────────────────────────────────────────────────────────────────── */

    /**
     * GET /admin/users/{user}/scopes
     * Returns all active scopes for a user as JSON (for admin UI).
     */
    public function getScopes(User $user)
    {
        $this->authorize('manageScopes', $user);

        $scopes = $user->scopes()->with('office')->get()->map(function ($scope) {
            return [
                'id'           => $scope->id,
                'office_id'    => $scope->office_id,
                'unit_type'    => $scope->office?->type ?? 'national',
                'unit_type_label' => $scope->office ? ucfirst($scope->office->type) : 'National',
                'unit_id'      => $scope->office_id,
                'access_level' => $scope->access_level,
                'access_label' => UserScope::ACCESS_LEVELS[$scope->access_level] ?? $scope->access_level,
                'label'        => $scope->label,
                'display_name' => $scope->display_name,
                'is_active'    => $scope->is_active,
            ];
        });

        return response()->json(['scopes' => $scopes]);
    }

    /**
     * POST /admin/users/{user}/scopes
     * Adds a single scope to a user.
     */
    public function addScope(Request $request, User $user)
    {
        $this->authorize('manageScopes', $user);
        $this->enforceOperationAdminRestrictions($request);

        $validated = $request->validate([
            'office_id'    => ['nullable', 'exists:offices,id'],
            'access_level' => ['required', 'in:read_only,read_write,full'],
            'label'        => ['nullable', 'string', 'max:100'],
        ]);

        /* 
        // Enforce exclusivity for specific units if active
        if ($validated['office_id']) {
            $alreadyAssigned = UserScope::where('office_id', $validated['office_id'])
                ->where('user_id', '!=', $user->id)
                ->where('is_active', true)
                ->exists();
            abort_if($alreadyAssigned, 422, "This office is already assigned to another user.");
        }
        */

        $scope = $user->scopes()->updateOrCreate(
            ['office_id' => $validated['office_id']],
            ['access_level' => $validated['access_level'], 'label' => $validated['label'] ?? null, 'is_active' => true]
        );

        return response()->json([
            'success'      => true,
            'scope'        => $scope,
            'display_name' => $scope->display_name,
        ]);
    }

    /**
     * PATCH /admin/users/{user}/scopes/{scope}
     * Updates access_level or label on an existing scope.
     */
    public function updateScope(Request $request, User $user, UserScope $scope)
    {
        $this->authorize('manageScopes', $user);

        abort_if($scope->user_id !== $user->id, 404);

        $validated = $request->validate([
            'access_level' => ['required', 'in:read_only,read_write,full'],
            'label'        => ['nullable', 'string', 'max:100'],
            'is_active'    => ['boolean'],
        ]);

        $scope->update($validated);

        return response()->json(['success' => true, 'scope' => $scope, 'display_name' => $scope->display_name]);
    }

    /**
     * DELETE /admin/users/{user}/scopes/{scope}
     * Removes a scope from a user.
     */
    public function removeScope(User $user, UserScope $scope)
    {
        $this->authorize('manageScopes', $user);

        abort_if($scope->user_id !== $user->id, 404);

        $scope->delete();

        return response()->json(['success' => true]);
    }

    /* ─────────────────────────────────────────────────────────────────── */
    /*  Private Helpers                                                     */
    /* ─────────────────────────────────────────────────────────────────── */

    /**
     * Sync scopes from an array payload.
     * Expected format: [['unit_type'=>'zone','unit_id'=>3,'access_level'=>'read_only'], ...]
     */
    private function syncScopes(User $user, array $scopes): void
    {
        // Remove all existing scopes then re-insert
        $user->scopes()->delete();

        foreach ($scopes as $scopeData) {
            $officeId = $scopeData['office_id'] ?? $scopeData['unit_id'] ?? null;
            $access   = $scopeData['access_level'] ?? null;

            if (!$access) continue;

            /* 
            // Enforce exclusivity - REMOVED: High-level offices like HQ can be shared by multiple admins
            if ($officeId) {
                $alreadyAssigned = UserScope::where('office_id', $officeId)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_active', true)
                    ->exists();
                if ($alreadyAssigned) {
                    abort(422, "Conflict: Office (ID: $officeId) is already assigned to another user.");
                }
            }
            */

            $user->scopes()->create([
                'office_id'    => $officeId,
                'access_level' => $access,
                'label'        => $scopeData['label'] ?? null,
                'is_active'    => true,
            ]);
        }
    }

    /**
     * Validate that the given unit_id exists in the correct table.
     */
    private function validateUnitId(string $unitType, ?int $unitId): void
    {
        if ($unitId === null) return; // null = all of type — always valid

        $exists = match ($unitType) {
            'zone'   => Office::zones()->where('id', $unitId)->exists(),
            'sector' => Office::sectors()->where('id', $unitId)->exists(),
            'beat'   => Office::beats()->where('id', $unitId)->exists(),
            default  => true, // national / call_centre don't need a unit_id
        };

        abort_if(!$exists, 422, "Unit ID {$unitId} does not exist for type '{$unitType}'.");
    }

    /**
     * Enforce restrictions for the Operation Admin role.
     */
    private function enforceOperationAdminRestrictions(Request $request): void
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser || $currentUser->role->name !== Role::IT_ADMIN) {
            return;
        }

        // 1. Role Restrictions: Cannot create/update to super_admin or operation_admin
        if ($request->has('role_id')) {
            $targetRole = Role::find($request->role_id);
            $restricted = [Role::SUPER_ADMIN, Role::IT_ADMIN];
            if ($targetRole && in_array($targetRole->name, $restricted)) {
                abort(403, 'You are not authorized to assign this role authority level.');
            }
        }

        if ($request->has('scopes')) {
            foreach ($request->input('scopes', []) as $scope) {
                $office = isset($scope['office_id']) ? Office::find($scope['office_id']) : null;
                if ($office && $office->type === 'region') { // Region is national-ish
                    abort(403, 'You are not authorized to assign high-level scope.');
                }
            }
        }
    }

    /**
     * Display comprehensive user-wise activity history & audit trail.
     */
    public function audit(Request $request, User $user)
    {
        $this->authorize('users.view');

        $user->load(['role:id,name,display_name', 'activeScopes.office.parent.parent']);

        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }

        $query = AuditLog::with(['user:id,uuid,username,full_name,role_id', 'user.role:id,name,display_name'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($oq) use ($user) {
                      $oq->where('table_name', 'users')
                         ->where('record_id', $user->id);
                  });
            })->orderBy('id', 'desc');

        if ($request->filled('action')) {
            $action = $request->input('action');
            if ($action === 'created') {
                $query->where(function ($aq) {
                    $aq->where('action', 'like', '%create%')
                       ->orWhere('action', 'like', '%enrolled%');
                });
            } elseif ($action === 'updated') {
                $query->where(function ($aq) {
                    $aq->where('action', 'like', '%update%')
                       ->orWhere('action', 'like', '%reset%')
                       ->orWhere('action', 'like', '%cleared%');
                });
            } elseif ($action === 'deleted') {
                $query->where(function ($aq) {
                    $aq->where('action', 'like', '%delete%')
                       ->orWhere('action', 'like', '%revoked%')
                       ->orWhere('action', 'like', '%locked%');
                });
            } else {
                $query->where('action', $action);
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                  ->orWhere('table_name', 'like', "%{$s}%")
                  ->orWhere('ip_address', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate($perPage);

        return view('operations.users.audit', compact('user', 'logs'));
    }
}
