<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SessionManagementController extends Controller
{
    /**
     * Show the active sessions screen.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $currentSessionId = $request->session()->getId();

        $rawSessions = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                $rawSessions = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->orderBy('last_activity', 'desc')
                    ->get();
            }
        } catch (\Throwable $e) {}

        $appKey = (string) config('app.key');
        $sessions = [];

        foreach ($rawSessions as $session) {
            $isCurrent = ($session->id === $currentSessionId);
            // Mask raw session ID using HMAC to prevent raw session cookie leakage into DOM
            $maskedId = hash_hmac('sha256', $session->id, $appKey);

            $sessions[] = [
                'masked_id'     => $maskedId,
                'ip_address'    => htmlspecialchars((string) $session->ip_address, ENT_QUOTES, 'UTF-8'),
                'user_agent'    => htmlspecialchars((string) $session->user_agent, ENT_QUOTES, 'UTF-8'),
                'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'is_current'    => $isCurrent,
            ];
        }

        return view('profile.sessions', [
            'user'             => $user,
            'sessions'         => $sessions,
            'securityVersion'  => $user->security_version,
            'authMethod'       => $request->session()->get('auth_method', 'unknown'),
            'mfaCompletedAt'   => $request->session()->get('mfa_completed_at'),
        ]);
    }

    /**
     * Revoke a single remote session by its masked ID.
     */
    public function revoke(Request $request, string $maskedId): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $appKey = (string) config('app.key');
        $currentSessionId = $request->session()->getId();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                $userSessions = DB::table('sessions')->where('user_id', $user->id)->get();
                foreach ($userSessions as $session) {
                    if (hash_hmac('sha256', $session->id, $appKey) === $maskedId) {
                        // Prevent revoking the current session via this endpoint (use logout instead)
                        if ($session->id === $currentSessionId) {
                            return back()->with('error', 'Cannot revoke current session from this button. Use logout.');
                        }

                        DB::table('sessions')->where('id', $session->id)->delete();

                        AuditLog::create([
                            'user_id'    => $user->id,
                            'action'     => 'session_revoked',
                            'table_name' => 'sessions',
                            'record_id'  => null,
                            'new_values' => ['ip' => $session->ip_address],
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);

                        return back()->with('status', 'Remote session revoked successfully.');
                    }
                }
            }
        } catch (\Throwable $e) {}

        return back()->with('error', 'Target session could not be found or has already expired.');
    }

    /**
     * Terminate all other sessions across all nodes and browsers.
     * Combines session record deletion with atomic security_version increment.
     */
    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (!Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password is required to revoke other active sessions.']);
        }

        $currentSessionId = $request->session()->getId();

        // 1. Invalidate other sessions in database
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }
        } catch (\Throwable $e) {}

        // 2. Increment security_version in DB to invalidate any in-flight/cached sessions on any node
        $user->bumpSecurityVersion();

        // 3. Update current session to match new security version
        $request->session()->put('security_version', (int) $user->security_version);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'logout_other_devices',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => ['new_security_version' => $user->security_version],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'All other active sessions have been successfully revoked across all terminals.');
    }
}
